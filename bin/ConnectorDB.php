<?php
/*
 * MikoPBX - free phone system for small business
 * Copyright © 2017-2023 Alexey Portnov and Nikolay Beketov
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <https://www.gnu.org/licenses/>.
 */

namespace Modules\ModuleExtendedCDRs\bin;

use MikoPBX\Common\Models\CallQueues;
use MikoPBX\Core\System\Util;
use MikoPBX\Core\Workers\WorkerBase;
use MikoPBX\Core\System\BeanstalkClient;
use Modules\ModuleExtendedCDRs\Lib\CacheManager;
use Modules\ModuleExtendedCDRs\Lib\HistoryParser;
use Modules\ModuleExtendedCDRs\Lib\Logger;
use Modules\ModuleExtendedCDRs\Lib\Mp3TagService;
use Modules\ModuleExtendedCDRs\Lib\CdrQueryBuilder;
use Modules\ModuleExtendedCDRs\Lib\CheckpointPolicy;
use Modules\ModuleExtendedCDRs\Lib\SyncPolicy;
use Exception;
use Modules\ModuleExtendedCDRs\Lib\MikoPBXVersion;
use Modules\ModuleExtendedCDRs\Lib\Providers\CdrDbProvider;
use Modules\ModuleExtendedCDRs\Models\CallHistory;
use Modules\ModuleExtendedCDRs\Models\CallQueuesHistory;
use Modules\ModuleExtendedCDRs\Models\DailyCallStats;
use Modules\ModuleExtendedCDRs\Models\ModuleExtendedCDRs;
use Modules\ModuleExtendedCDRs\Models\OversizedLinkedIds;
use Phalcon\Db\Enum;
use DateTime;
use Throwable;

require_once 'Globals.php';
require_once(dirname(__DIR__).'/vendor/autoload.php');

class ConnectorDB extends WorkerBase
{
    private Logger $logger;

    public int $cdrOffset = 1;
    public string $referenceDate = '';

    private int $lastSyncTime = 0;
    private int $nextSyncDelay = SyncPolicy::NORMAL_DELAY_SECONDS;
    private bool $catchUpMode = false;
    private Mp3TagService $mp3TagService;

    /** @var string[] Кэш списка "раздутых" linkedid, исключённых из синхронизации. */
    private array $oversizedCache = [];
    /** @var string[] linkedid, добавленные в память при неуспешной записи в БД (session-only). */
    private array $oversizedPending = [];
    private int $oversizedCacheTime = 0;
    private int $oversizedPruneTime = 0;

    /**
     * Белый список методов, разрешённых для вызова через onEvents/invoke.
     */
    private array $allowedInvokeMethods = [
        'getCdr',
        'getCdrQueue',
        'getCdrQueueIDs',
        'getCountCdr',
        'getRecordingPathByID',
        'updateSettings',
        'syncCdrData',
    ];


    /**
     * Handles the received signal.
     *
     * @param int $signal The signal to handle.
     *
     * @return void
     */
    public function signalHandler(int $signal): void
    {
        parent::signalHandler($signal);
        cli_set_process_title('SHUTDOWN_'.cli_get_process_title());
        $this->logger->writeError("Get signal SHUTDOWN: $signal");
        $this->needRestart = true;
    }

    /**
     * Старт работы листнера.
     *
     * @param $argv
     */
    public function start($argv):void
    {
        $this->logger   = new Logger('ConnectorDB', 'ModuleExtendedCDRs');
        $this->mp3TagService = new Mp3TagService(dirname(__DIR__));
        $this->logger->writeInfo('Starting...');
        $this->ensureDailyStatsTableExists();
        $this->ensureOversizedTableExists();
        $this->updateSettings();
        $beanstalk      = new BeanstalkClient(self::class);
        $beanstalk->subscribe(self::class, [$this, 'onEvents']);
        $beanstalk->subscribe($this->makePingTubeName(self::class), [$this, 'pingCallBack']);
        while ($this->needRestart === false) {
            try {
                $this->syncCdrData(true);
                $this->pruneOversizedLinkedIds();
            }catch (Throwable $exception){
                $this->logger->writeError("Throwable:".$exception->getMessage(). ' Line: '.$exception->getLine());
                $this->nextSyncDelay = SyncPolicy::ERROR_DELAY_SECONDS;
            }
            $beanstalk->wait(max(1, $this->nextSyncDelay));
            $this->logger->rotate();
        }
    }


    /**
     * Получение настроек модуля.
     * @param int $newCdrOffset
     * @return void
     */
    public function updateSettings(int $newCdrOffset=0):void
    {
        $settings  = ModuleExtendedCDRs::findFirst();
        if(!$settings){
            $settings = new ModuleExtendedCDRs();
        }
        if($newCdrOffset > 0){
            $minOffset = HistoryParser::getMinCdrId();
            $settings->cdrOffset = max($newCdrOffset,$minOffset);
            $settings->save();
        }
        if(empty($settings->referenceDate) || (($settings->cdrOffset === null || $settings->cdrOffset === '') && $settings->referenceDate !== '0') ){
            $settings->cdrOffset = 1;
            $settings->referenceDate = date("Y-m-d H:i:s.0", strtotime("-1 days"));
            $settings->save();
        }
        $this->cdrOffset     = (int)$settings->cdrOffset;
        $this->referenceDate = $settings->referenceDate;
    }

    /**
     * Ответ на запрос состояния сервиса.
     * @param BeanstalkClient $message
     * @return void
     */
    public function pingCallBack(BeanstalkClient $message): void
    {
        parent::pingCallBack($message);
        $this->updateSettings();
        $this->syncCdrData();
    }

    /**
     * Получение запросов на идентификацию номера телефона.
     * @param $tube
     * @return void
     */
    public function onEvents($tube): void
    {
        $data = [];
        try {
            $pathToData = $tube->getBody();
            if(file_exists($pathToData)) {
                $data = json_decode(file_get_contents($pathToData), true, 512, JSON_THROW_ON_ERROR);
                unlink($pathToData);
            }
            $this->logger->writeInfo(['data'=> $data, 'pathToData' => $pathToData], 'onEvents');
        }catch (Throwable $exception){
            $this->logger->writeError("Throwable:".$exception->getMessage(). ' Line: '.$exception->getLine());
            return;
        }
        $action = $data['action']??'';
        try {
            if($action === 'invoke'){

                $res_data = [];
                $funcName = $data['function']??'';
                if(in_array($funcName, $this->allowedInvokeMethods, true) && method_exists($this, $funcName)){
                    if(count($data['args']) === 0){
                        $res_data = $this->$funcName();
                    }else{
                        $res_data = $this->$funcName(...$data['args']??[]);
                    }
                }else{
                    $this->logger->writeError($data);
                }
                if(isset($data['need-ret'])){
                    $res_data = self::saveInTmpFile($res_data);
                    $tube->reply($res_data);
                }
                $this->logger->writeInfo(['data'=> $data, 'result' => $res_data], 'invoke');
            }
        }catch (Throwable $exception){
            $this->logger->writeError($data, "Throwable:".$exception->getMessage(). ' Line: '.$exception->getLine());
            return;
        }
    }

    /**
     * Сериализует данные и сохраняет их во временный файл.
     * @param array $data
     * @return string
     */
    public static function saveInTmpFile(array $data):string
    {
        try {
            $resData = json_encode($data, JSON_THROW_ON_ERROR);
        }catch (\JsonException $e){
            return '';
        }
        $downloadCacheDir = '';
        $tmpDir           = '/tmp/';
        $di = MikoPBXVersion::getDefaultDi();
        if ($di) {
            $dirsConfig = $di->getShared('config');
            $tmpDirName = $dirsConfig->path('core.tempDir') . '/ModuleExtendedCDRs';
            Util::mwMkdir($tmpDirName);
            chown($tmpDirName, 'www');
            if (file_exists($tmpDirName)) {
                $tmpDir = $tmpDirName;
            }

            $downloadCacheDir = $dirsConfig->path('www.downloadCacheDir');
            if (!file_exists($downloadCacheDir)) {
                $downloadCacheDir = '';
            }
        }
        $fileBaseName = md5(microtime(true));
        // "temp-" in the filename is necessary for the file to be automatically deleted after 5 minutes.
        $filename = $tmpDir . '/temp-' . $fileBaseName;
        file_put_contents($filename, $resData);
        if (!empty($downloadCacheDir)) {
            $linkName = $downloadCacheDir . '/' . $fileBaseName;
            // For automatic file deletion.
            // A file with such a symlink will be deleted after 5 minutes by cron.
            Util::createUpdateSymlink($filename, $linkName, true);
        }
        chown($filename, 'www');
        return $filename;
    }

    /**
     * Метод следует вызывать при работе с API из прочих процессов.
     * @param string $function
     * @param array $args
     * @param bool $retVal
     * @return array
     */
    public static function invoke(string $function, array $args = [], bool $retVal = true):array
    {
        $req = [
            'action'   => 'invoke',
            'function' => $function,
            'args'     => $args
        ];
        $client = new BeanstalkClient(self::class);
        $object = [];
        try {
            if($retVal){
                $req['need-ret'] = true;
                $pathToData = self::saveInTmpFile($req);
                $result = $client->request($pathToData, 20);
            }else{
                $pathToData = self::saveInTmpFile($req);
                $client->publish($pathToData);
                return [];
            }
            if(file_exists($result)){
                $object = json_decode(file_get_contents($result), true, 512, JSON_THROW_ON_ERROR);
                unlink($result);
            }
        } catch (\Throwable $e) {
            $object = [];
        }
        return $object;
    }

    /**
     * Возвращает усеченный слева номер телефона.
     *
     * @param string $number
     * @return string
     */
    public static function getPhoneIndex(string $number): string
    {
        $number = preg_replace('/\D+/', '', $number);
        return substr($number, -9) ?: '';
    }

    /**
     * Запускаем парсер истории звонков. Парсер сохраняет кэш, кто последний говорил с клиентом.
     * @param bool $force
     * @return void
     */
    public function syncCdrData(bool $force = false):void
    {
        if($force === false && time() - $this->lastSyncTime < 10){
            return;
        }
        $this->lastSyncTime = time();
        $oldOffset = $this->cdrOffset;
        $this->logger->writeInfo('...Start sync with offset...'. $oldOffset);

        $sourceState = HistoryParser::getLastCdrState();
        $sourceLastId = (int)($sourceState['data']['id'] ?? $oldOffset);
        $policy = SyncPolicy::decide($oldOffset, $sourceLastId, $sourceState['ok'], false, $this->catchUpMode);
        $this->nextSyncDelay = $policy['delay'];
        $this->catchUpMode = $policy['mode'] === SyncPolicy::MODE_CATCH_UP;
        if (!$sourceState['ok']) {
            $this->publishSyncState($oldOffset, $sourceLastId, $policy, 'source_last_id_failed');
            $this->logger->writeError('batch_failed: source_last_id_failed');
            return;
        }

        $historyResult = HistoryParser::getHistoryData(
            $this->cdrOffset,
            $this->loadOversizedLinkedIds(),
            $policy['batchLinkedIds']
        );
        if (!$historyResult['ok']) {
            $this->nextSyncDelay = SyncPolicy::ERROR_DELAY_SECONDS;
            $this->publishSyncState($oldOffset, $sourceLastId, $policy, $historyResult['error']);
            $this->logger->writeError('batch_failed: ' . $historyResult['error']);
            return;
        }
        $cdrData = $historyResult['data'];
        $parsedOffset = $historyResult['newOffset'];
        $totalRows = array_sum(array_map(fn($cdr) => count($cdr['rows'] ?? []), $cdrData));
        $this->logger->writeInfo("Parsed offset $parsedOffset. linkedIds:" . count($cdrData) . ", totalRows:$totalRows");

        $arrKeys = (new CallHistory())->toArray();
        unset($arrKeys['id']);

        $CallHistorySaveTime = 0;
        $CallQueuesHistorySaveTime = 0;

        // Разделяем linkedId на нормальные (<=100 rows) и тяжёлые (>100 rows)
        $incomingLinkedIds = [];
        $normalLinkedIds = [];
        $heavyLinkedIds = [];
        $newOversizedLinkedIds = []; // linkedid, достигшие потолка 5000 строк (зависшие каналы)
        $normalUniqueIds = [];
        $allRowIds = []; // Все id для расчёта offset

        foreach ($cdrData as $linkedId => $cdr) {
            if (in_array($cdr['typeCall'], [CallHistory::CALL_TYPE_INCOMING, CallHistory::CALL_TYPE_MISSED], true)) {
                $incomingLinkedIds[] = $linkedId;
            }
            $rowCount = count($cdr['rows'] ?? []);
            if ($rowCount >= HistoryParser::MAX_LINKEDID_ROWS) {
                // Раздутый linkedid: сохраним его первые 5000 строк этим циклом,
                // занесём в служебную таблицу и исключим из последующих выборок.
                $newOversizedLinkedIds[] = $linkedId;
            }
            if ($rowCount > 100) {
                $heavyLinkedIds[] = $linkedId;
            } else {
                $normalLinkedIds[] = $linkedId;
                foreach ($cdr['rows'] as $row) {
                    $normalUniqueIds[] = $row['UNIQUEID'];
                }
            }
            // Собираем все id ДО merge
            foreach ($cdr['rows'] ?? [] as $row) {
                $rowId = (int)($row['id'] ?? 0);
                if ($rowId > 0) {
                    $allRowIds[] = $rowId;
                }
            }
        }

        $heavyLinkedIdsMap = array_flip($heavyLinkedIds);
        if (!empty($heavyLinkedIds)) {
            $this->logger->writeInfo("Heavy linkedIds (>100 rows): " . count($heavyLinkedIds));
        }

        // Фиксируем "раздутые" linkedid, чтобы исключить их из следующих выборок.
        if (!empty($newOversizedLinkedIds)) {
            $this->persistOversizedLinkedIds($newOversizedLinkedIds, $cdrData);
        }

        // Batch загрузка CallQueuesHistory (1 запрос вместо N)
        $start = microtime(true);
        $existingQueues = [];
        if (!empty($incomingLinkedIds)) {
            $queuesResult = CallQueuesHistory::find([
                'linkedid IN ({ids:array})',
                'bind' => ['ids' => $incomingLinkedIds]
            ]);
            foreach ($queuesResult as $q) {
                $existingQueues[$q->linkedid] = $q;
            }
        }
        $CallQueuesHistoryFindTime = microtime(true) - $start;
        $this->logger->writeInfo(sprintf("CallQueuesHistoryFindTime: %.4f", $CallQueuesHistoryFindTime));

        // Batch загрузка CallHistory только для нормальных linkedId
        $start = microtime(true);
        $existingHistory = [];
        if (!empty($normalLinkedIds)) {
            $historyResult = CallHistory::find([
               'linkedid IN ({ids:array})',
               'bind' => ['ids' => $normalLinkedIds]
            ]);
            foreach ($historyResult as $h) {
                $existingHistory[$h->UNIQUEID] = $h;
            }
        }
        $CallHistoryFindTime = microtime(true) - $start;
        $this->logger->writeInfo(sprintf("CallHistoryFindTime: %.4f, normalIds: %d", $CallHistoryFindTime, count($normalUniqueIds)));

        $Mp3TagsTime = 0;
        $SetCallTypeTime = 0;
        $rowsToSave = [];

        // Основной цикл — поиск O(1) по массиву
        foreach ($cdrData as $linkedId => $cdr) {
            $isHeavy = isset($heavyLinkedIdsMap[$linkedId]);

            // Для тяжёлых linkedId предобработка и загрузка данных
            if ($isHeavy) {
                $heavyStart = microtime(true);
                $originalCount = count($cdr['rows'] ?? []);

                // Объединяем дублирующиеся NOANSWER записи
                $cdr['rows'] = $this->mergeNoAnswerRows($cdr['rows']);
                $mergedCount = count($cdr['rows']);

                $heavyHistory = [];
                $heavyResult = CallHistory::find([
                    'linkedid = :linkedid:',
                    'bind' => ['linkedid' => $linkedId]
                ]);
                foreach ($heavyResult as $h) {
                    $heavyHistory[$h->UNIQUEID] = $h;
                }
                $heavyTime = microtime(true) - $heavyStart;
                $this->logger->writeInfo(sprintf("Heavy linkedId: %s, original: %d, merged: %d, loadTime: %.4f", $linkedId, $originalCount, $mergedCount, $heavyTime));
            }

            if (in_array($cdr['typeCall'], [CallHistory::CALL_TYPE_INCOMING, CallHistory::CALL_TYPE_MISSED], true)) {
                $cdrQueue = $existingQueues[$linkedId] ?? null;
                if (!$cdrQueue) {
                    $cdrQueue = new CallQueuesHistory();
                    $cdrQueue->linkedid = $linkedId;
                }

                $dateParts = explode(' ', $cdr['firstQueue']['start'] ?? $cdr['rows'][0]['start'] ?? '');
                $cdrQueue->date          = $dateParts[0] ?? '';
                $cdrQueue->time          = $dateParts[1] ?? '';
                $cdrQueue->queueId       = $cdr['firstQueue']['id'] ?? '';
                $cdrQueue->answered      = $cdr['answered'];
                $cdrQueue->answeredQueue = $cdr['firstQueue']['answered'] ?? 0;
                $cdrQueue->waitTimeQueue = $cdr['firstQueue']['queueWait'] ?? 0;
                $cdrQueue->waitTime      = ($cdr['answered'] === 1)
                    ? ($cdr['q_answer'] - $cdr['q_start'])
                    : ($cdr['q_endtime'] - $cdr['q_start']);

                $start = microtime(true);
                $cdrQueue->save();
                $CallQueuesHistorySaveTime += microtime(true) - $start;
            }

            // Выбираем источник данных: для тяжёлых — отдельный кеш
            $historySource = $isHeavy ? ($heavyHistory ?? []) : $existingHistory;

            foreach ($cdr['rows'] as $row) {
                /** @var CallHistory $dbData */
                $isNew = !isset($historySource[$row['UNIQUEID']]);
                $dbData = $historySource[$row['UNIQUEID']] ?? new CallHistory();

                foreach ($row as $key => $value) {
                    if (!array_key_exists($key, $arrKeys)) {
                        continue;
                    }
                    $dbData->$key = $value;
                }
                foreach ($cdr as $key => $value) {
                    if (!array_key_exists($key, $arrKeys)) {
                        continue;
                    }
                    $dbData->$key = $value;
                }

                $start = microtime(true);
                $this->mp3TagService->updateTags($dbData);
                $Mp3TagsTime += microtime(true) - $start;

                $start = microtime(true);
                $this->setBasicCallType($dbData, $isNew);
                $SetCallTypeTime += microtime(true) - $start;

                // Собираем для batch save
                $rowsToSave[] = $dbData;
            }
        }

        // Batch save через raw SQL
        $start = microtime(true);
        [$insertCount, $updateCount] = $this->batchSaveCallHistory($rowsToSave, $arrKeys);
        $CallHistorySaveTime = microtime(true) - $start;
        $this->logger->writeInfo("BatchSave: insert=$insertCount, update=$updateCount");

        // Определяем recall/transfer состояния после сохранения
        $start = microtime(true);
        $this->updateRecallTransferStates($rowsToSave);
        $recallTransferTime = microtime(true) - $start;

        // Если в этом цикле обнаружены новые "раздутые" linkedid — удерживаем offset.
        // Потолок в 5000 строк был съеден зависшим звонком, поэтому обычные linkedid
        // могли быть вытеснены из выборки. На следующем цикле запрос уже исключит
        // oversized и продвинется без потери обычных звонков.
        if (!empty($newOversizedLinkedIds)) {
            $this->cdrOffset = $oldOffset;
            $this->logger->writeInfo("Holding offset at $oldOffset: detected " . count($newOversizedLinkedIds) . " new oversized linkedId(s)");
            $this->logger->writeInfo("End sync with offset {$this->cdrOffset} (+0)");
            return;
        }

        $this->cdrOffset = CheckpointPolicy::nextOffset([
            'oldOffset' => $oldOffset,
            'parsedOffset' => $parsedOffset,
            'requestOk' => true,
            'saveOk' => true,
            'newQuarantine' => false,
            'rowIds' => $allRowIds,
        ]);

        $this->logger->writeInfo([
            'CallHistoryFindTime' => round($CallHistoryFindTime, 4),
            'CallHistorySaveTime' => round($CallHistorySaveTime, 4),
            'CallQueuesHistoryFindTime' => round($CallQueuesHistoryFindTime, 4),
            'CallQueuesHistorySaveTime' => round($CallQueuesHistorySaveTime, 4),
            'Mp3TagsTime' => round($Mp3TagsTime, 4),
            'SetCallTypeTime' => round($SetCallTypeTime, 4),
            'RecallTransferTime' => round($recallTransferTime, 4)],
        "Timing");
        if($oldOffset !== $this->cdrOffset){
            $this->logger->writeInfo("Update progress, offset $oldOffset to new value $this->cdrOffset ");
            $this->updateSettings($this->cdrOffset);
        }
        $policy = SyncPolicy::decide(
            $this->cdrOffset,
            $sourceLastId,
            true,
            $historyResult['limitReached'],
            $this->catchUpMode
        );
        $this->nextSyncDelay = $policy['delay'];
        $this->catchUpMode = $policy['mode'] === SyncPolicy::MODE_CATCH_UP;
        $this->publishSyncState($this->cdrOffset, $sourceLastId, $policy, '');
        $offsetDelta = $this->cdrOffset - $oldOffset;
        $this->logger->writeInfo("End sync with offset {$this->cdrOffset} (+$offsetDelta)");
    }

    private function publishSyncState(int $offset, int $sourceLastId, array $policy, string $error): void
    {
        $previous = CacheManager::getCacheData(HistoryParser::CDR_SYNC_PROGRESS_KEY);
        $previous = is_array($previous) ? $previous : [];
        CacheManager::setCacheData(HistoryParser::CDR_SYNC_PROGRESS_KEY, [
            'lastId' => $sourceLastId,
            'nowId' => $offset,
            'offset' => $offset,
            'sourceLastId' => $sourceLastId,
            'lag' => max(0, $sourceLastId - $offset),
            'mode' => $policy['mode'],
            'lastDate' => $previous['lastDate'] ?? '',
            'lastSuccessAt' => $error === '' ? date('c') : ($previous['lastSuccessAt'] ?? ''),
            'lastError' => $error,
        ]);
    }

    /**
     * Возвращает путь к файлу записи по ID.
     * @param string $id
     * @return string
     */
    public function getRecordingPathByID(string $id):array
    {
        $dbData = CallHistory::findFirst([
            'conditions' => 'UNIQUEID = :id:',
            'bind' => ['id' => $id],
            'columns' => 'recordingfile'
        ]);
        if($dbData){
            return [$dbData->recordingfile];
        }
        return [''];
    }


    /**
     * Объединяет NOANSWER записи с одинаковыми src_num/dst_num и близким временем (<20 сек)
     * @param array $rows
     * @return array
     */
    private function mergeNoAnswerRows(array $rows): array
    {
        if (empty($rows)) {
            return $rows;
        }

        // Группируем по src_num + dst_num + disposition=NOANSWER
        $groups = [];
        $otherRows = [];

        foreach ($rows as $row) {
            if (($row['disposition'] ?? '') === 'NOANSWER') {
                $key = ($row['src_num'] ?? '') . '|' . ($row['dst_num'] ?? '');
                $groups[$key][] = $row;
            } else {
                $otherRows[] = $row;
            }
        }

        // Обрабатываем каждую группу NOANSWER
        $mergedRows = [];
        foreach ($groups as $key => $groupRows) {
            // Сортируем по start
            usort($groupRows, fn($a, $b) => strtotime($a['start'] ?? 0) - strtotime($b['start'] ?? 0));

            $merged = null;
            foreach ($groupRows as $row) {
                if ($merged === null) {
                    $merged = $row;
                    continue;
                }

                $mergedEnd = strtotime($merged['endtime'] ?? $merged['start'] ?? 0);
                $rowStart = strtotime($row['start'] ?? 0);

                // Если разница < 20 секунд — объединяем
                if (($rowStart - $mergedEnd) < 20) {
                    $rowEnd = strtotime($row['endtime'] ?? $row['start'] ?? 0);
                    $mergedStart = strtotime($merged['start'] ?? 0);

                    // Берём min/max значения
                    $newStart = min($mergedStart, $rowStart);
                    $newEnd = max($mergedEnd, $rowEnd);

                    $merged['start'] = date('Y-m-d H:i:s', $newStart);
                    $merged['endtime'] = date('Y-m-d H:i:s', $newEnd);
                    $merged['duration'] = $newEnd - $newStart;
                    $merged['UNIQUEID'] = min($merged['UNIQUEID'] ?? '', $row['UNIQUEID'] ?? '');
                    $merged['dst_chan'] = min($merged['dst_chan'] ?? '', $row['dst_chan'] ?? '');
                } else {
                    // Разрыв > 20 сек — сохраняем текущую и начинаем новую
                    $mergedRows[] = $merged;
                    $merged = $row;
                }
            }
            if ($merged !== null) {
                $mergedRows[] = $merged;
            }
        }

        return array_merge($mergedRows, $otherRows);
    }

    /**
     * Устанавливает базовый stateCall на основе typeCall/billsec (без запросов к БД).
     * @param CallHistory $dbData
     * @param bool $isNew
     * @return void
     */
    private function setBasicCallType(CallHistory $dbData, bool $isNew = true):void
    {
        // Для существующих записей с установленным stateCall — пропускаем
        if (!$isNew && !empty($dbData->stateCall)) {
            return;
        }
        if($dbData->typeCall === CallHistory::CALL_TYPE_OUTGOING){
            if($dbData->billsec === '0'){
                $dbData->stateCall = CallHistory::CALL_STATE_OUTGOING_FAIL;
            }else{
                $dbData->stateCall = CallHistory::CALL_STATE_OK;
            }
        }elseif($dbData->typeCall === CallHistory::CALL_TYPE_INCOMING && $dbData->is_app === '1'){
            $dbData->stateCall = CallHistory::CALL_STATE_APPLICATION;
        }elseif($dbData->typeCall === CallHistory::CALL_TYPE_MISSED){
            $dbData->stateCall = CallHistory::CALL_STATE_MISSED;
        }elseif ($dbData->typeCall === CallHistory::CALL_TYPE_INCOMING){
            $dbData->stateCall = CallHistory::CALL_STATE_OK;
        }elseif($dbData->billsec === '0'){
            $dbData->stateCall = CallHistory::CALL_STATE_OUTGOING_FAIL;
        }else{
            $dbData->stateCall = CallHistory::CALL_STATE_OK;
        }
    }

    /**
     * Определяет recall/transfer состояния после batch save.
     * Обрабатывает только записи со stateCall=OK и непустым номером.
     * @param array $records
     * @return void
     */
    private function updateRecallTransferStates(array $records):void
    {
        if (!$this->di->has(CdrDbProvider::SERVICE_NAME)) {
            $this->di->register(new CdrDbProvider());
        }
        $db = $this->di->getShared(CdrDbProvider::SERVICE_NAME);

        foreach ($records as $dbData) {
            if ($dbData->stateCall !== CallHistory::CALL_STATE_OK) {
                continue;
            }
            $number = '';
            if ($dbData->typeCall === CallHistory::CALL_TYPE_OUTGOING) {
                $number = $dbData->dst_num;
            } elseif ($dbData->typeCall === CallHistory::CALL_TYPE_INCOMING) {
                $number = $dbData->src_num;
            }
            if (empty($number)) {
                continue;
            }

            try {
                $dateTime = new DateTime($dbData->start);
            } catch (Exception $e) {
                continue;
            }
            $dateTime->modify('-60 minutes');
            $oldStart = $dateTime->format('Y-m-d H:i:s');
            $phoneIndex = self::getPhoneIndex($number);

            // Проверяем recall: был ли пропущенный звонок от/к этому номеру за последний час
            $sql = "SELECT typeCall FROM cdr_general
                    WHERE (dstIndex = :phoneIndex OR srcIndex = :phoneIndex)
                      AND start BETWEEN :oldStart AND :currentStart
                      AND linkedid <> :linkedid
                    ORDER BY start DESC LIMIT 1";
            $result = $db->query($sql, [
                'phoneIndex'   => $phoneIndex,
                'oldStart'     => $oldStart,
                'currentStart' => $dbData->start,
                'linkedid'     => $dbData->linkedid,
            ]);
            $result->setFetchMode(Enum::FETCH_ASSOC);
            $oldCdr = $result->fetch();

            if ($oldCdr) {
                $oldTypeCall = intval($oldCdr['typeCall']);
                if ($oldTypeCall === CallHistory::CALL_TYPE_MISSED
                    && $dbData->typeCall === CallHistory::CALL_TYPE_INCOMING) {
                    $dbData->stateCall = CallHistory::CALL_STATE_RECALL_CLIENT;
                } elseif ($oldTypeCall === CallHistory::CALL_TYPE_MISSED
                    && $dbData->typeCall === CallHistory::CALL_TYPE_OUTGOING) {
                    $dbData->stateCall = CallHistory::CALL_STATE_RECALL_USER;
                }
            }

            // Проверяем transfer: был ли успешный звонок ранее в том же linkedid
            $sql = "SELECT id FROM cdr_general
                    WHERE billsec > 0 AND start < :currentStart AND linkedid = :linkedid
                    LIMIT 1";
            $result = $db->query($sql, [
                'currentStart' => $dbData->start,
                'linkedid'     => $dbData->linkedid,
            ]);
            $result->setFetchMode(Enum::FETCH_ASSOC);
            if ($result->fetch()) {
                $dbData->stateCall = CallHistory::CALL_STATE_TRANSFER;
            }

            // Сохраняем только если stateCall изменился
            if ($dbData->stateCall !== CallHistory::CALL_STATE_OK) {
                $dbData->save();
            }
        }
    }

    /**
     * Batch save CallHistory records
     * @param array $records
     * @param array $columns
     * @return array [insertCount, updateCount]
     */
    private function batchSaveCallHistory(array $records, array $columns): array
    {
        if (empty($records)) {
            return [0, 0];
        }

        $newRecords = [];
        $existingRecords = [];

        foreach ($records as $record) {
            if (empty($record->id)) {
                $newRecords[] = $record;
            } else {
                $existingRecords[] = $record;
            }
        }

        // Batch INSERT для новых записей
        $insertedCount = 0;
        if (!empty($newRecords)) {
            if (!$this->di->has(CdrDbProvider::SERVICE_NAME)) {
                $this->di->register(new CdrDbProvider());
            }
            $db = $this->di->getShared(CdrDbProvider::SERVICE_NAME);

            $columnNames = array_keys($columns);
            $columnsStr = implode(', ', $columnNames);
            $placeholders = '(' . implode(', ', array_fill(0, count($columnNames), '?')) . ')';

            foreach (array_chunk($newRecords, 100) as $chunk) {
                $allPlaceholders = [];
                $allValues = [];

                foreach ($chunk as $record) {
                    $allPlaceholders[] = $placeholders;
                    foreach ($columnNames as $col) {
                        $allValues[] = $record->$col ?? '';
                    }
                }

                $sql = "INSERT INTO cdr_general ($columnsStr) VALUES " . implode(', ', $allPlaceholders);
                try {
                    $db->execute($sql, $allValues);
                    $insertedCount += count($chunk);
                } catch (Throwable $e) {
                    $this->logger->writeError("Batch INSERT failed (chunk of " . count($chunk) . "): " . $e->getMessage());
                }
            }
        }

        // UPDATE для существующих — только если есть изменения
        $actualUpdates = 0;
        foreach ($existingRecords as $record) {
            if ($record->hasChanged()) {
                try {
                    $record->save();
                    $actualUpdates++;
                } catch (Throwable $e) {
                    $this->logger->writeError("UPDATE failed for UNIQUEID={$record->UNIQUEID}: " . $e->getMessage());
                }
            }
        }

        return [$insertedCount, $actualUpdates];
    }

    public function getCdr(array $filter = []): array
    {
        $res_data = [];
        if ($this->filterNotValid($filter)) {
            return $res_data;
        }
        try {
            $res = CallHistory::find($filter);
            $res_data = $res->toArray();
        } catch (\Throwable $e) {
            $res_data = [];
        }

        return $res_data;
    }

    public function getCdrQueue(array $filter = [], array $additionalFilter = []): array
    {
        $res_data = [];
        if ($this->filterNotValid($filter)) {
            return $res_data;
        }
        try {
            $res = CallQueuesHistory::find($filter);
            $res_data = $res->toArray();

            $queues = CallQueues::find(['columns'=> 'uniqid,name'])->toArray();
            $queues = array_column($queues, 'name', 'uniqid');
            foreach ($res_data as &$rowResult){
                $rowResult['queueName'] = $queues[$rowResult['queueId']]??'';
            }
            unset($rowResult);

        } catch (\Throwable $e) {
            $res_data = [];
        }
        return $res_data;
    }

    public function getCdrQueueIDs(array $filter = []): array
    {
        $res_data = [];
        if ($this->filterNotValid($filter)) {
            return $res_data;
        }
        try {
            $res_data = CallQueuesHistory::find($filter)->toArray();
        } catch (\Throwable $e) {
            $res_data = [];
        }
        return $res_data;
    }


    /**
     * Возвращает количество записей за период с отбором по номерам.
     * Использует lazy-кэш для запросов без фильтров.
     * @param string $start
     * @param string $end
     * @param array  $numbers
     * @param array  $additionalNumbers
     * @param array  $additionalFilter
     * @param int  $minBilSec
     * @param array  $ids
     * @return array
     */
    public function getCountCdr(string $start, string $end, array $numbers, array $additionalNumbers, array $additionalFilter, int $minBilSec = 0, array $ids = []): array
    {
        // Проверяем возможность использования lazy-кэша
        if ($this->canUseDailyStatsCache($numbers, $additionalNumbers, $additionalFilter, $minBilSec, $ids)) {
            return $this->getCountCdrCached($start, $end);
        }

        // Прямой запрос для фильтрованных данных
        $queryBuilder = (new CdrQueryBuilder())
            ->whereDateRange($start, $end)
            ->whereNumbers($numbers, 'Index')
            ->whereNumbers($additionalNumbers, 'IndexAdd')
            ->whereFilteredExtensions($additionalFilter)
            ->whereLinkedIds($ids)
            ->whereMinBillSec($minBilSec);

        $condition  = $queryBuilder->getCondition();
        $bindParams = $queryBuilder->getBindParams();

        if (!$this->di->has(CdrDbProvider::SERVICE_NAME)) {
            $this->di->register(new CdrDbProvider());
        }

        $db = $this->di->getShared(CdrDbProvider::SERVICE_NAME);
        $sql = "
            SELECT
                COALESCE(SUM(IIF(t.typeCall=0,1,0)),0) AS cINNER,
                COALESCE(SUM(IIF(t.typeCall=1,1,0)),0) AS cOUTGOING,
                COALESCE(SUM(IIF(t.typeCall=2,1,0)),0) AS cINCOMING,
                COALESCE(SUM(IIF(t.typeCall=3,1,0)),0) AS cMISSED,
                COUNT(t.linkedid) AS cCalls
            FROM (
                SELECT
                    MIN(cdr_general.id) AS id,
                    MAX(cdr_general.typeCall) AS typeCall,
                    cdr_general.linkedid AS linkedid
                FROM cdr_general
                WHERE {$condition}
                GROUP BY cdr_general.linkedid
            ) AS t
        ";
        try {
            $result = $db->query($sql, $bindParams);
            $result->setFetchMode(Enum::FETCH_ASSOC);
            $row = $result->fetch();
        } catch (Throwable $e) {
            $row = [];
            Util::sysLogMsg('ERROR- EXTENDED CDR', $sql . PHP_EOL . print_r($bindParams, true));
        }

        return is_array($row) ? $row : [];
    }


    /**
     * Создаёт таблицу daily_call_stats если она не существует.
     * @return void
     */
    private function ensureDailyStatsTableExists(): void
    {
        if (!$this->di->has(CdrDbProvider::SERVICE_NAME)) {
            $this->di->register(new CdrDbProvider());
        }
        $db = $this->di->getShared(CdrDbProvider::SERVICE_NAME);

        $sql = "CREATE TABLE IF NOT EXISTS daily_call_stats (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            date TEXT NOT NULL UNIQUE,
            cInner INTEGER DEFAULT 0,
            cOutgoing INTEGER DEFAULT 0,
            cIncoming INTEGER DEFAULT 0,
            cMissed INTEGER DEFAULT 0,
            cTotal INTEGER DEFAULT 0,
            updatedAt TEXT DEFAULT ''
        )";
        $db->execute($sql);
        $this->logger->writeInfo('Ensured daily_call_stats table exists');
    }

    /**
     * Создаёт служебную таблицу oversized_linkedids если она не существует.
     * @return void
     */
    private function ensureOversizedTableExists(): void
    {
        OversizedLinkedIds::ensureTableExists();
        $this->logger->writeInfo('Ensured oversized_linkedids table exists');
    }

    /**
     * Возвращает список "раздутых" linkedid (кэш обновляется не чаще раза в минуту).
     * @return string[]
     */
    private function loadOversizedLinkedIds(): array
    {
        if (time() - $this->oversizedCacheTime > 60) {
            try {
                $rows = OversizedLinkedIds::find([
                    "status IS NULL OR status <> 'resolved'",
                    'columns' => 'linkedid'
                ]);
                $dbList = array_column($rows->toArray(), 'linkedid');
                // Кэш = записи из БД ∪ session-only (не потерянные при неуспешном save()).
                $this->oversizedCache = array_values(array_unique(array_merge($dbList, $this->oversizedPending)));
                // Стамп времени только при успешной загрузке — иначе повторим на след. цикле.
                $this->oversizedCacheTime = time();
            } catch (Throwable $e) {
                $this->logger->writeError("loadOversizedLinkedIds: " . $e->getMessage());
            }
        }
        return $this->oversizedCache;
    }

    /**
     * Фиксирует новые "раздутые" linkedid в служебной таблице и в кэше.
     * @param string[] $linkedIds
     * @param array    $cdrData Данные текущей выборки (для rowCount/maxId).
     * @return void
     */
    private function persistOversizedLinkedIds(array $linkedIds, array $cdrData): void
    {
        foreach ($linkedIds as $linkedId) {
            if (in_array($linkedId, $this->oversizedCache, true)) {
                continue;
            }
            $rows = $cdrData[$linkedId]['rows'] ?? [];
            $rowCount = count($rows);
            $minId = 0;
            $maxId = 0;
            foreach ($rows as $row) {
                $rowId = (int)($row['id'] ?? 0);
                $maxId = max($maxId, $rowId);
                $minId = $minId === 0 ? $rowId : min($minId, $rowId);
            }

            $record = new OversizedLinkedIds();
            $record->linkedid   = $linkedId;
            $record->rowCount   = $rowCount;
            $record->maxId      = $maxId;
            $record->detectedAt = date('Y-m-d H:i:s');
            $record->minId = $minId;
            $record->maxRangeId = $maxId;
            $record->reason = 'row_limit';
            $record->attempts = 0;
            $record->firstFailureAt = $record->detectedAt;
            $record->lastFailureAt = $record->detectedAt;
            $record->nextRetryAt = date('Y-m-d H:i:s', time() + 60);
            $record->status = 'pending';
            $saved = $record->save();

            // В любом случае исключаем linkedid в пределах текущей сессии воркера,
            // иначе сбой save() (блокировка БД, UNIQUE-коллизия) приведёт к бесконечному
            // повторному детекту и вечному удержанию offset.
            if (!in_array($linkedId, $this->oversizedCache, true)) {
                $this->oversizedCache[] = $linkedId;
            }
            if ($saved) {
                // Успешно записан — убираем из session-only набора, если был там.
                $this->oversizedPending = array_values(array_diff($this->oversizedPending, [$linkedId]));
                $this->logger->writeInfo("Oversized linkedId excluded from sync: $linkedId (rows=$rowCount, maxId=$maxId)");
            } else {
                // Запись не удалась — держим в session-only наборе, чтобы кэш не потерял
                // его при обновлении из БД (иначе трэшинг offset). Повторная запись —
                // после перезапуска воркера через повторный детект.
                if (!in_array($linkedId, $this->oversizedPending, true)) {
                    $this->oversizedPending[] = $linkedId;
                }
                $this->logger->writeError("Failed to persist oversized linkedId (excluded in-memory only): $linkedId (" . implode('; ', $record->getMessages()) . ")");
            }
        }
    }

    /**
     * Периодически (не чаще раза в час) удаляет из списка исключений те linkedid,
     * у которых больше нет CDR-строк с id > offset — т.е. завершённые звонки,
     * оставшиеся позади текущей позиции синхронизации. Это ограничивает размер
     * списка NOT IN активными "зависшими" звонками и не даёт ему расти бесконечно.
     * @return void
     */
    private function pruneOversizedLinkedIds(): void
    {
        if (time() - $this->oversizedPruneTime < 3600) {
            return;
        }
        $this->oversizedPruneTime = time();

        $stored = array_column(OversizedLinkedIds::find(['columns' => 'linkedid'])->toArray(), 'linkedid');
        if (empty($stored)) {
            return;
        }

        // Активные — те, у кого ещё есть строки за текущим offset.
        // null означает сбой запроса к ядру (Beanstalk timeout) — в этом случае НЕ удаляем
        // ничего, чтобы не разкарантинить активные зависшие звонки и не вызвать повторный стопор.
        // Пустой массив [] означает, что запрос выполнился и активных действительно нет —
        // тогда все устаревшие записи можно удалить.
        $active = HistoryParser::getActiveLinkedIds($stored, $this->cdrOffset);
        if ($active === null) {
            return;
        }
        $toDelete = array_diff($stored, $active);
        if (empty($toDelete)) {
            return;
        }

        $records = OversizedLinkedIds::find([
            'linkedid IN ({ids:array})',
            'bind' => ['ids' => array_values($toDelete)],
        ]);
        foreach ($records as $record) {
            $record->delete();
        }

        // Кэш = активные из БД ∪ session-only записи (последние в БД отсутствуют).
        $this->oversizedCache = array_values(array_unique(array_merge($active, $this->oversizedPending)));
        $this->oversizedCacheTime = time();
        $this->logger->writeInfo("Pruned oversized linkedIds: removed " . count($toDelete) . ", kept " . count($active));
    }

    /**
     * Вычисляет статистику звонков за один день.
     * @param string $date Дата в формате 'YYYY-MM-DD'
     * @return array ['cInner'=>X, 'cOutgoing'=>Y, 'cIncoming'=>Z, 'cMissed'=>W, 'cTotal'=>N]
     */
    private function calculateStatsForDay(string $date): array
    {
        if (!$this->di->has(CdrDbProvider::SERVICE_NAME)) {
            $this->di->register(new CdrDbProvider());
        }
        $db = $this->di->getShared(CdrDbProvider::SERVICE_NAME);

        $startOfDay = $date . ' 00:00:00';
        $endOfDay = $date . ' 23:59:59';

        $sql = "
            SELECT
                COALESCE(SUM(IIF(t.typeCall=0,1,0)),0) AS cInner,
                COALESCE(SUM(IIF(t.typeCall=1,1,0)),0) AS cOutgoing,
                COALESCE(SUM(IIF(t.typeCall=2,1,0)),0) AS cIncoming,
                COALESCE(SUM(IIF(t.typeCall=3,1,0)),0) AS cMissed,
                COUNT(t.linkedid) AS cTotal
            FROM (
                SELECT
                    MIN(cdr_general.id) AS id,
                    MAX(cdr_general.typeCall) AS typeCall,
                    cdr_general.linkedid AS linkedid
                FROM cdr_general
                WHERE start BETWEEN :startOfDay AND :endOfDay
                GROUP BY cdr_general.linkedid
            ) AS t
        ";

        try {
            $result = $db->query($sql, [
                'startOfDay' => $startOfDay,
                'endOfDay' => $endOfDay,
            ]);
            $result->setFetchMode(Enum::FETCH_ASSOC);
            $row = $result->fetch();
            return is_array($row) ? $row : [
                'cInner' => 0,
                'cOutgoing' => 0,
                'cIncoming' => 0,
                'cMissed' => 0,
                'cTotal' => 0,
            ];
        } catch (Throwable $e) {
            $this->logger->writeError("calculateStatsForDay error: " . $e->getMessage());
            return [
                'cInner' => 0,
                'cOutgoing' => 0,
                'cIncoming' => 0,
                'cMissed' => 0,
                'cTotal' => 0,
            ];
        }
    }

    /**
     * Получает закэшированную статистику для списка дат.
     * @param array $dates Массив дат в формате 'YYYY-MM-DD'
     * @return array Массив [date => ['cInner'=>X, ...]]
     */
    private function getCachedStats(array $dates): array
    {
        if (empty($dates)) {
            return [];
        }

        $cached = DailyCallStats::find([
            'date IN ({dates:array})',
            'bind' => ['dates' => $dates],
        ]);

        $result = [];
        foreach ($cached as $row) {
            $result[$row->date] = [
                'cInner' => (int)$row->cInner,
                'cOutgoing' => (int)$row->cOutgoing,
                'cIncoming' => (int)$row->cIncoming,
                'cMissed' => (int)$row->cMissed,
                'cTotal' => (int)$row->cTotal,
            ];
        }
        return $result;
    }

    /**
     * Сохраняет статистику за день в кэш.
     * @param string $date Дата в формате 'YYYY-MM-DD'
     * @param array $stats Статистика ['cInner'=>X, ...]
     * @return void
     */
    private function saveDailyStats(string $date, array $stats): void
    {
        $existing = DailyCallStats::findFirst([
            'date = :date:',
            'bind' => ['date' => $date],
        ]);

        if ($existing) {
            $record = $existing;
        } else {
            $record = new DailyCallStats();
            $record->date = $date;
        }

        $record->cInner = (int)($stats['cInner'] ?? 0);
        $record->cOutgoing = (int)($stats['cOutgoing'] ?? 0);
        $record->cIncoming = (int)($stats['cIncoming'] ?? 0);
        $record->cMissed = (int)($stats['cMissed'] ?? 0);
        $record->cTotal = (int)($stats['cTotal'] ?? 0);
        $record->updatedAt = date('Y-m-d H:i:s');

        if (!$record->save()) {
            $this->logger->writeError("Failed to save DailyCallStats for $date");
        }
    }

    /**
     * Проверяет, можно ли использовать lazy-кэш для данных параметров.
     * Кэш НЕ используется при наличии фильтров.
     * @param array $numbers
     * @param array $additionalNumbers
     * @param array $additionalFilter
     * @param int $minBilSec
     * @param array $ids
     * @return bool
     */
    private function canUseDailyStatsCache(array $numbers, array $additionalNumbers, array $additionalFilter, int $minBilSec, array $ids): bool
    {
        return empty($numbers)
            && empty($additionalNumbers)
            && empty($additionalFilter)
            && empty($ids)
            && $minBilSec === 0;
    }

    /**
     * Возвращает количество записей за период с использованием lazy-кэша.
     * @param string $start
     * @param string $end
     * @return array
     */
    private function getCountCdrCached(string $start, string $end): array
    {
        $startDate = substr($start, 0, 10); // 'YYYY-MM-DD'
        $endDate = substr($end, 0, 10);
        $today = date('Y-m-d');

        // Генерируем список дат в периоде
        $allDates = [];
        $current = new DateTime($startDate);
        $endDt = new DateTime($endDate);
        while ($current <= $endDt) {
            $allDates[] = $current->format('Y-m-d');
            $current->modify('+1 day');
        }

        // Разделяем на завершённые дни (можно кэшировать) и сегодня (всегда живой)
        $completedDates = array_filter($allDates, fn($d) => $d < $today);
        $todayInRange = in_array($today, $allDates, true);

        // Получаем кэш для завершённых дней
        $cachedStats = $this->getCachedStats($completedDates);

        // Находим отсутствующие даты
        $missingDates = array_diff($completedDates, array_keys($cachedStats));

        // Вычисляем и кэшируем отсутствующие
        foreach ($missingDates as $date) {
            $stats = $this->calculateStatsForDay($date);
            $this->saveDailyStats($date, $stats);
            $cachedStats[$date] = $stats;
        }

        // Суммируем статистику из кэша
        $totals = [
            'cINNER' => 0,
            'cOUTGOING' => 0,
            'cINCOMING' => 0,
            'cMISSED' => 0,
            'cCalls' => 0,
        ];

        foreach ($cachedStats as $stats) {
            $totals['cINNER'] += (int)$stats['cInner'];
            $totals['cOUTGOING'] += (int)$stats['cOutgoing'];
            $totals['cINCOMING'] += (int)$stats['cIncoming'];
            $totals['cMISSED'] += (int)$stats['cMissed'];
            $totals['cCalls'] += (int)$stats['cTotal'];
        }

        // Добавляем живой подсчёт за сегодня
        if ($todayInRange) {
            $todayStats = $this->calculateStatsForDay($today);
            $totals['cINNER'] += (int)$todayStats['cInner'];
            $totals['cOUTGOING'] += (int)$todayStats['cOutgoing'];
            $totals['cINCOMING'] += (int)$todayStats['cIncoming'];
            $totals['cMISSED'] += (int)$todayStats['cMissed'];
            $totals['cCalls'] += (int)$todayStats['cTotal'];
        }

        return $totals;
    }

    /**
     * Check if the filter has any invalid bind parameters.
     *
     * @param array $filter The filter to validate.
     * @return bool True if the filter has invalid bind parameters, false otherwise.
     */
    private function filterNotValid(array $filter): bool
    {
        $haveErrors = false;
        $validValue = ['0', ''];
        if (isset($filter['bind'])) {
            if (is_array($filter['bind'])) {
                foreach ($filter['bind'] as $bindValue) {
                    if (empty($bindValue) && !in_array($bindValue, $validValue, true)) {
                        $haveErrors = true;
                    }
                }
            } else {
                $haveErrors = true;
            }
        }
        return $haveErrors;
    }

}

if(isset($argv) && count($argv) !== 1
    && Util::getFilePathByClassName(ConnectorDB::class) === $argv[0]){
    ini_set('memory_limit', '2048M');
    ConnectorDB::startWorker($argv??[]);
}
