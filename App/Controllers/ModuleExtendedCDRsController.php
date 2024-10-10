<?php
/**
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 11 2018
 */
namespace Modules\ModuleExtendedCDRs\App\Controllers;
use MikoPBX\AdminCabinet\Controllers\BaseController;
use MikoPBX\Common\Models\CallQueues;
use MikoPBX\Common\Models\Extensions;
use MikoPBX\Common\Models\Sip;
use MikoPBX\Common\Providers\PBXConfModulesProvider;
use MikoPBX\Core\System\Util;
use MikoPBX\Modules\Config\CDRConfigInterface;
use MikoPBX\Modules\PbxExtensionUtils;
use Modules\ModuleExtendedCDRs\App\Forms\ModuleExtendedCDRsForm;
use Modules\ModuleExtendedCDRs\bin\ConnectorDB;
use Modules\ModuleExtendedCDRs\Lib\CacheManager;
use Modules\ModuleExtendedCDRs\Lib\HistoryParser;
use Modules\ModuleExtendedCDRs\Models\CallHistory;
use Modules\ModuleExtendedCDRs\Models\ExportRules;
use Modules\ModuleExtendedCDRs\Models\ModuleExtendedCDRs;
use MikoPBX\Common\Models\Providers;
use Modules\ModuleUsersGroups\Models\GroupMembers;
use Modules\ModuleUsersGroups\Models\UsersGroups;
use DateTime;

class ModuleExtendedCDRsController extends BaseController
{
    private $moduleUniqueID = 'ModuleExtendedCDRs';
    private $moduleDir;

    /**
     * Basic initial class
     */
    public function initialize(): void
    {
        $this->moduleDir = PbxExtensionUtils::getModuleDir($this->moduleUniqueID);
        $this->view->logoImagePath = "{$this->url->get()}assets/img/cache/{$this->moduleUniqueID}/logo.svg";
        $this->view->submitMode = null;
        parent::initialize();
    }

    public function getTablesDescriptionAction(): void
    {
        $this->view->data = $this->getTablesDescription();
    }

    public function getNewRecordsAction(): void
    {
        $currentPage                 = $this->request->getPost('draw');
        $table                       = $this->request->get('table');
        $this->view->draw            = $currentPage;
        $this->view->recordsTotal    = 0;
        $this->view->recordsFiltered = 0;
        $this->view->data            = [];

        $descriptions = $this->getTablesDescription();
        if(!isset($descriptions[$table])){
            return;
        }
        $className = $this->getClassName($table);
        if(!empty($className)){
            $filter = [];
            if(isset($descriptions[$table]['cols']['priority'])){
                $filter = ['order' => 'priority'];
            }
            $allRecords = $className::find($filter)->toArray();
            $records    = [];
            $emptyRow   = [
                'rowIcon'  =>  $descriptions[$table]['cols']['rowIcon']['icon']??'',
                'DT_RowId' => 'TEMPLATE'
            ];
            foreach ($descriptions[$table]['cols'] as $key => $metadata) {
                if('rowIcon' !== $key){
                    $emptyRow[$key] = '';
                }
            }
            $records[] = $emptyRow;
            foreach ($allRecords as $rowData){
                $tmpData = [];
                $tmpData['DT_RowId'] =  $rowData['id'];
                foreach ($descriptions[$table]['cols'] as $key => $metadata){
                    if('rowIcon' === $key){
                        $tmpData[$key] = $metadata['icon']??'';
                    }elseif('delButton' === $key){
                        $tmpData[$key] = '';
                    }elseif(isset($rowData[$key])){
                        $tmpData[$key] =  $rowData[$key];
                    }
                }
                $records[] = $tmpData;
            }
            $this->view->data      = $records;
        }
    }

    /**
     * Index page controller
     */
    public function indexAction(): void
    {
        $footerCollection = $this->assets->collection('footerJS');
        $footerCollection->addJs('js/pbx/main/form.js', true);
        $footerCollection->addJs('js/vendor/datatable/dataTables.semanticui.js', true);
        $footerCollection->addJs("js/cache/{$this->moduleUniqueID}/module-export-records-index.js", true);
        $footerCollection->addJs("js/cache/{$this->moduleUniqueID}/xlsx.full.min.js", true);
        $footerCollection->addJs("js/cache/{$this->moduleUniqueID}/pdfmake.min.js", true);
        $footerCollection->addJs("js/cache/{$this->moduleUniqueID}/vfs_fonts.js", true);
        $footerCollection->addJs('js/vendor/jquery.tablednd.min.js', true);
        $footerCollection->addJs('js/vendor/semantic/modal.min.js', true);
        $footerCollection->addJs('js/pbx/CallDetailRecords/call-detail-records-player.js', true);
        $footerCollection->addJS('js/vendor/moment/moment.min.js', true);
        $footerCollection->addJS('js/vendor/datepicker/daterangepicker.js', true);
        $footerCollection->addJs('js/vendor/range/range.min.js', true);
        $footerCollection->addJs('js/vendor/semantic/progress.min.js', true);


        $headerCollectionCSS = $this->assets->collection('headerCSS');
        $headerCollectionCSS->addCss("css/cache/{$this->moduleUniqueID}/module-export-records.css", true);
        $headerCollectionCSS->addCss('css/vendor/semantic/progress.min.css', true);
        $headerCollectionCSS->addCss('css/vendor/datatable/dataTables.semanticui.min.css', true);
        $headerCollectionCSS->addCss('css/vendor/semantic/modal.min.css', true);

        $headerCollectionCSS->addCss('css/vendor/range/range.min.css', true);
        $headerCollectionCSS->addCss('css/vendor/datepicker/daterangepicker.css', true);

        $settings = ModuleExtendedCDRs::findFirst();
        if ($settings === null) {
            $settings = new ModuleExtendedCDRs();
        }

        $providers = Providers::find();
        $providersList = [];
        foreach ($providers as $provider){
            $providersList[ $provider->uniqid ] = $provider->getRepresent();
        }
        $options['providers']=$providersList;

        $this->view->form = new ModuleExtendedCDRsForm($settings, $options);
        $this->view->pick("{$this->moduleDir}/App/Views/index");

        // Список выбора очередей.
        $this->view->queues = CallQueues::find(['columns' => ['id', 'name']]);
        $this->view->groups = [];

        if(class_exists('\Modules\ModuleUsersGroups\Models\UsersGroups')){
            $this->view->groups = UsersGroups::find(['columns' => ['id', 'name']])->toArray();
        }
        $filterNumbers = [];
        PBXConfModulesProvider::hookModulesMethod(CDRConfigInterface::APPLY_ACL_FILTERS_TO_CDR_QUERY, [&$filterNumbers]);
        $filteredExtensions = $filterNumbers['bind']['filteredExtensions']??[];
        if(!empty($filteredExtensions)){
            $filterUsers = [
                "number IN ({filteredExtensions:array}) AND type = 'SIP'",
                'columns' => ['number', 'callerid', 'userid'],
                'bind' => ['filteredExtensions' => $filteredExtensions],
                'order' => 'number'
            ];
            $this->view->users  = Extensions::find($filterUsers)->toArray();
        }else{
            $this->view->users  = Extensions::find(["type = 'SIP'", 'order' => 'number DESC', 'columns' => ['number', 'callerid', 'userid']])->toArray();
        }
        $this->view->rules = ExportRules::find()->toArray();

        try {
            $searchSettings = json_decode($settings->searchSettings, true, 512, JSON_THROW_ON_ERROR);
        }catch (\Exception $e){
            $searchSettings = [];
        }
        $filterNumbers = explode(' ', $searchSettings['additionalFilter']??'');
        $additionalFilter = [];
        foreach ($this->view->groups as $group){
            foreach ($filterNumbers as $number){
                if('group_'.$group['id'] === $number){
                    $additionalFilter[] = [
                        'name'   => $group['name'],
                        'number' => $number
                    ];
                }
            }
        }

        foreach ($this->view->users as $user){
            foreach ($filterNumbers as $number){
                if($user['number'] === $number){
                    $additionalFilter[] = [
                        'name'   => $user['callerid'],
                        'number' => $number
                    ];
                }
            }
        }
        $this->view->dateRangeSelector      = $searchSettings['dateRangeSelector']??'';
        $this->view->additionalFilter       = $additionalFilter;
        $this->view->additionalFilterString = implode(',',array_column($additionalFilter, 'number'));
    }

    /**
     * Save settings AJAX action
     */
    public function saveAction() :void
    {
        $data       = $this->request->getPost();
        $ruleSaveResult = [];
        if(isset($data['rules'])){
            foreach ($data['rules'] as $ruleData ){
                $rule = ExportRules::findFirst("id='{$ruleData['id']}'");
                if(!$rule){
                    $rule = new ExportRules();
                }
                $rule->name = $ruleData['name'];
                $rule->users = $ruleData['users'];
                $rule->dstUrl = $ruleData['dstUrl'];
                $rule->headers = $ruleData['headers'];
                if($rule->save()){
                    $ruleSaveResult[$ruleData['id']] = $rule->id;
                }
            }
        }
        $this->flash->success($this->translation->_('ms_SuccessfulSaved'));
        $this->view->success = true;
        $this->view->ruleSaveResult = $ruleSaveResult;
    }

    /**
     * Delete phonebook record
     */
    public function deleteAction(): void
    {
        $table     = $this->request->get('table');
        $className = $this->getClassName($table);
        if(empty($className)) {
            $this->view->success = false;
            return;
        }
        $id     = $this->request->get('id');
        $record = $className::findFirstById($id);
        if ($record !== null && ! $record->delete()) {
            $this->flash->error(implode('<br>', $record->getMessages()));
            $this->view->success = false;
            return;
        }
        $this->view->success = true;
    }

    /**
     * Возвращает метаданные таблицы.
     * @return array
     */
    private function getTablesDescription():array
    {
        $description['PhoneBook'] = [
            'cols' => [
                'rowIcon'    => ['header' => '',                        'class' => 'collapsing', 'icon' => 'user'],
                'priority'   => ['header' => '',                        'class' => 'collapsing'],
                'call_id'    => ['header' => 'Представление абонента',  'class' => 'ten wide'],
                'number_rep' => ['header' => 'Номер телефона',          'class' => 'four wide'],
                'queueId'    => ['header' => 'Номер числом',            'class' => 'collapsing', 'select' => 'queues-list'],
                'delButton'  => ['header' => '',                        'class' => 'collapsing']
            ],
            'ajaxUrl' => '/getNewRecords',
            'icon' => 'user',
            'needDelButton' => true
        ];
        return $description;
    }

    /**
     * Обновление данных в таблице.
     */
    public function saveTableDataAction():void
    {
        $data       = $this->request->getPost();
        $tableName  = $data['pbx-table-id']??'';

        $className = $this->getClassName($tableName);
        if(empty($className)){
            return;
        }
        $rowId      = $data['pbx-row-id']??'';
        if(empty($rowId)){
            $this->view->success = false;
            return;
        }
        $this->db->begin();
        /** @var PhoneBook $rowData */
        $rowData = $className::findFirst('id="'.$rowId.'"');
        if(!$rowData){
            $rowData = new $className();
        }
        foreach ($rowData as $key => $value) {
            if($key === 'id'){
                continue;
            }
            if (array_key_exists($key, $data)) {
                $rowData->writeAttribute($key, $data[$key]);
            }
        }
        // save action
        if ($rowData->save() === FALSE) {
            $errors = $rowData->getMessages();
            $this->flash->error(implode('<br>', $errors));
            $this->view->success = false;
            $this->db->rollback();
            return;
        }
        $this->view->data = ['pbx-row-id'=>$rowId, 'newId'=>$rowData->id, 'pbx-table-id' => $data['pbx-table-id']];
        $this->view->success = true;
        $this->db->commit();

    }

    /**
     * Получение имени класса по имени таблицы
     * @param $tableName
     * @return string
     */
    private function getClassName($tableName):string
    {
        if(empty($tableName)){
            return '';
        }
        $className = "Modules\ModuleExtendedCDRs\Models\\$tableName";
        if(!class_exists($className)){
            $className = '';
        }
        return $className;
    }

    /**
     * Changes rules priority
     *
     */
    public function changePriorityAction(): void
    {
        $this->view->disable();
        $result = true;

        if ( ! $this->request->isPost()) {
            return;
        }
        $priorityTable = $this->request->getPost();
        $tableName     = $this->request->get('table');
        $className = $this->getClassName($tableName);
        if(empty($className)){
            echo "table not found -- ы$tableName --";
            return;
        }
        $rules = $className::find();
        foreach ($rules as $rule){
            if (array_key_exists ( $rule->id, $priorityTable)){
                $rule->priority = $priorityTable[$rule->id];
                $result         .= $rule->update();
            }
        }
        echo json_encode($result);
    }

    public function saveSearchSettingsAction(): void
    {
        $searchPhrase = $this->request->getPost('search');
        $this->view->searchPhrase = $searchPhrase['value'];
        $settings = ModuleExtendedCDRs::findFirst();
        if(!$settings){
            $settings = new ModuleExtendedCDRs();
        }
        $settings->searchSettings = $searchPhrase['value']??'';
        $settings->save();
    }

    /**
     * Returns the synchronization progress of the call history.
     * @return void
     */
    public function getStateAction(): void
    {
        $this->view->stateData = CacheManager::getCacheData(HistoryParser::CDR_SYNC_PROGRESS_KEY);
    }

    /**
     * Requests new package of call history records for DataTable JSON.
     *
     * @return void
     */
    public function getHistoryAction(): void
    {
        $currentPage    = $this->request->getPost('draw');
        $position       = $this->request->getPost('start');
        $recordsPerPage = $this->request->getPost('length');
        $searchPhrase   = $this->request->getPost('search');

        $this->view->draw = $currentPage;
        $this->view->recordsFiltered = 0;
        $this->view->data = [];
        $this->view->searchPhrase = $searchPhrase['value'];

        $parameters = [];
        $parameters['columns'] = 'COUNT(DISTINCT(linkedid)) as rows';
        // Count the number of unique calls considering filters
        if (empty($searchPhrase['value'])) {
            return;
        }
        [$start, $end, $numbers, $additionalNumbers] = $this->prepareConditionsForSearchPhrases($searchPhrase['value'], $parameters);
        // If we couldn't understand the search phrase, return empty result
        if (empty($parameters['conditions'])) {
            $this->view->conditions = 'empty';

            return;
        }
        $recordsFilteredReq = ConnectorDB::invoke('getCountCdr', [$start, $end, $numbers, $additionalNumbers]);
        $this->view->recordsFiltered = $recordsFilteredReq['cCalls'] ?? 0;
        $this->view->recordsInner    = $recordsFilteredReq['cINNER'] ?? 0;
        $this->view->recordsOutgoing = $recordsFilteredReq['cOUTGOING'] ?? 0;
        $this->view->recordsIncoming = $recordsFilteredReq['cINCOMING'] ?? 0;
        $this->view->recordsMissed   = $recordsFilteredReq['cMISSED'] ?? 0;

        // Find all LinkedIDs that match the specified filter
        $parameters['columns'] = 'DISTINCT(linkedid) as linkedid';
        $parameters['order']   = ['start desc'];

        if(!empty($recordsPerPage)){
            $parameters['limit']   = $recordsPerPage;
        }
        if(!empty($position)){
            $parameters['offset']  = $position;
        }

        $selectedLinkedIds = $this->selectCDRRecordsWithFilters($parameters);
        $arrIDS = [];
        foreach ($selectedLinkedIds as $item) {
            $arrIDS[] = $item['linkedid'];
        }
        if (empty($arrIDS)) {
            return;
        }

        // Retrieve all detailed records for processing and merging
        if (count($arrIDS) === 1) {
            $parameters = [
                'conditions' => 'linkedid = :ids:',
                'columns' => 'id,disposition,start,answer,endtime,src_num,dst_num,billsec,recordingfile,did,dst_chan,linkedid,is_app,verbose_call_id,typeCall,waitTime,line,stateCall',
                'bind' => [
                    'ids' => $arrIDS[0],
                ],
                'order' => ['linkedid desc', 'start asc', 'id asc'],
            ];
        } else {
            $parameters = [
                'conditions' => 'linkedid IN ({ids:array})',
                'columns' => 'id,disposition,start,answer,endtime,src_num,dst_num,billsec,recordingfile,did,dst_chan,linkedid,is_app,verbose_call_id,typeCall,waitTime,line,stateCall',
                'bind' => [
                    'ids' => $arrIDS,
                ],
                'order' => ['linkedid desc', 'start asc', 'id asc'],
            ];
        }

        $selectedRecords = $this->selectCDRRecordsWithFilters($parameters);
        $arrCdr = [];
        $objectLinkedCallRecord = (object)[
            'linkedid'      => '',
            'disposition'   => '',
            'start'         => '',
            'endtime'       => '',
            'src_num'       => '',
            'dst_num'       => '',
            'billsec'       => 0,
            'typeCall'      => '',
            'stateCall'     => '',
            'waitTime'      => '',
            'line'          => '',
            'answered'      => [],
            'detail'        => [],
            'ids'           => [],
            'did'           => ''
        ];

        $providers = Sip::find("type='friend'");
        $providerName = [];
        foreach ($providers as $provider){
            $providerName[$provider->uniqid] = $provider->description;
        }
        unset($providers);

        $statsCall = [
            CallHistory::CALL_STATE_OK              => Util::translate('repModuleExtendedCDRs_cdr_CALL_STATE_OK', false),
            CallHistory::CALL_STATE_TRANSFER        => Util::translate('repModuleExtendedCDRs_cdr_CALL_STATE_TRANSFER', false),
            CallHistory::CALL_STATE_MISSED          => Util::translate('repModuleExtendedCDRs_cdr_CALL_STATE_MISSED', false),
            CallHistory::CALL_STATE_OUTGOING_FAIL   => Util::translate('repModuleExtendedCDRs_cdr_CALL_STATE_OUTGOING_FAIL', false),
            CallHistory::CALL_STATE_RECALL_CLIENT   => Util::translate('repModuleExtendedCDRs_cdr_CALL_STATE_RECALL_CLIENT', false),
            CallHistory::CALL_STATE_RECALL_USER     => Util::translate('repModuleExtendedCDRs_cdr_CALL_STATE_RECALL_USER', false),
            CallHistory::CALL_STATE_APPLICATION     => Util::translate('repModuleExtendedCDRs_cdr_CALL_STATE_APPLICATION', false),
        ];
        $typeCallNames = [
            CallHistory::CALL_TYPE_INNER =>  Util::translate('repModuleExtendedCDRs_cdr_CALL_TYPE_INNER', false),
            CallHistory::CALL_TYPE_OUTGOING =>  Util::translate('repModuleExtendedCDRs_cdr_CALL_TYPE_OUTGOING', false),
            CallHistory::CALL_TYPE_INCOMING =>  Util::translate('repModuleExtendedCDRs_cdr_CALL_TYPE_INCOMING', false),
            CallHistory::CALL_TYPE_MISSED =>  Util::translate('repModuleExtendedCDRs_cdr_CALL_TYPE_MISSED', false),
        ];

        foreach ($selectedRecords as $arrRecord) {
            $record = (object)$arrRecord;
            if (!array_key_exists($record->linkedid, $arrCdr)) {
                $arrCdr[$record->linkedid] = clone $objectLinkedCallRecord;
            }
            if ($record->is_app !== '1'
                && $record->billsec > 0
                && ($record->disposition === 'ANSWER' || $record->disposition === 'ANSWERED')) {
                $disposition = 'ANSWERED';
            } else {
                $disposition = 'NOANSWER';
            }
            $linkedRecord = $arrCdr[$record->linkedid];
            $linkedRecord->linkedid     = $record->linkedid;
            $linkedRecord->typeCall     = $record->typeCall;
            $linkedRecord->typeCallDesc = $typeCallNames[$record->typeCall];
            $linkedRecord->waitTime     = 1*$record->waitTime;
            $linkedRecord->line         = $providerName[$record->line]??$record->line;
            $linkedRecord->disposition = $linkedRecord->disposition !== 'ANSWERED' ? $disposition : 'ANSWERED';
            $linkedRecord->start = $linkedRecord->start === '' ? $record->start : $linkedRecord->start;
            if($record->stateCall === CallHistory::CALL_STATE_OK){
                $linkedRecord->stateCall = $statsCall[$record->stateCall];
                $linkedRecord->stateCallIndex = $record->stateCall;
            }elseif($record->stateCall !== CallHistory::CALL_STATE_APPLICATION){
                $linkedRecord->stateCall = ($linkedRecord->stateCall === '') ? $statsCall[$record->stateCall] : $linkedRecord->stateCall;
                $linkedRecord->stateCallIndex = $linkedRecord->stateCall === '' ? $record->stateCall : $linkedRecord->stateCall;
            }

            if($linkedRecord->typeCall === CallHistory::CALL_TYPE_INCOMING){
                $linkedRecord->did =  ($linkedRecord->did === '') ? $record->did : $linkedRecord->did;
            }

            $linkedRecord->endtime = max($record->endtime , $linkedRecord->endtime);
            $linkedRecord->src_num = $linkedRecord->src_num === '' ? $record->src_num : $linkedRecord->src_num;
            $linkedRecord->dst_num = $linkedRecord->dst_num === '' ? $record->dst_num : $linkedRecord->dst_num;
            $linkedRecord->billsec += (int)$record->billsec;
            $isAppWithRecord = ($record->is_app === '1' && file_exists($record->recordingfile));
            if ($disposition === 'ANSWERED' || $isAppWithRecord) {
                if($record->is_app === '1'){
                    $waitTime = 0;
                }else{
                    $waitTime = strtotime($record->answer) - strtotime($record->start);
                }

                $formattedDate  = date('Y-m-d-H_i', strtotime($linkedRecord->start));
                $uid            = str_replace('mikopbx-', '', $linkedRecord->linkedid);
                $prettyFilename = "$uid-$formattedDate-$linkedRecord->src_num-$linkedRecord->dst_num";

                $linkedRecord->answered[] = [
                    'id'            => $record->id,
                    'start'         => date('d-m-Y H:i:s', strtotime($record->start)),
                    'waitTime'      => gmdate( $waitTime < 3600 ? 'i:s' : 'G:i:s', $waitTime),
                    'billsec'       => gmdate( $record->billsec < 3600 ? 'i:s' : 'G:i:s', $record->billsec),
                    'src_num'       => $record->src_num,
                    'dst_num'       => $record->dst_num,
                    'recordingfile' => $record->recordingfile,
                    'prettyFilename'=> $prettyFilename,
                    'stateCall'     => $statsCall[$record->stateCall],
                    'stateCallIndex'=> $record->stateCall,
                ];
            }else{
                $waitTime = strtotime($record->endtime) - strtotime($record->start);
                $linkedRecord->answered[] = [
                    'id'            => $record->id,
                    'start'         => date('d-m-Y H:i:s', strtotime($record->start)),
                    'waitTime'      => gmdate( $waitTime < 3600 ? 'i:s' : 'G:i:s', $waitTime),
                    'billsec'       => gmdate( 'i:s', 0),
                    'src_num'       => $record->src_num,
                    'dst_num'       => $record->dst_num,
                    'recordingfile' => '',
                    'stateCall'     => $statsCall[$record->stateCall],
                    'stateCallIndex'=> $record->stateCall,
                ];
            }
            $linkedRecord->detail[] = $record;
            if (!empty($record->verbose_call_id)) {
                $linkedRecord->ids[] = $record->verbose_call_id;
            }
        }
        $output = [];
        foreach ($arrCdr as $cdr) {
            $timing = gmdate($cdr->billsec < 3600 ? 'i:s' : 'G:i:s', $cdr->billsec);
            if('ANSWERED' !== $cdr->disposition){
                // Это пропущенный.
                $cdr->waitTime = strtotime($cdr->endtime) - strtotime($cdr->start);
            }
            $additionalClass = (empty($cdr->answered))?'ui':'detailed';
            $output[] = [
                date('d-m-Y H:i:s', strtotime($cdr->start)),
                $cdr->src_num,
                $cdr->dst_num,
                $timing === '00:00' ? '' : $timing,
                $cdr->answered,
                $cdr->disposition,
                'waitTime'    => gmdate( $cdr->waitTime < 3600 ? 'i:s' : 'G:i:s', $cdr->waitTime),
                'stateCall'   => $cdr->stateCall,
                'typeCall'    => $cdr->typeCall,
                'typeCallDesc'=> $cdr->typeCallDesc,
                'line'        => $cdr->line,
                'did'         => $cdr->did,
                'DT_RowId'    => $cdr->linkedid,
                'DT_RowClass' => trim($additionalClass.' '.('NOANSWER' === $cdr->disposition ? 'negative' : '')),
                'ids'         => rawurlencode(implode('&', array_unique($cdr->ids))),
            ];
        }

        $this->view->data = $output;
    }

    /**
     * Prepares query parameters for filtering CDR records.
     *
     * @param string $searchPhrase The search phrase entered by the user.
     * @param array $parameters The CDR query parameters.
     * @return array
     */
    private function prepareConditionsForSearchPhrases(string &$searchPhrase, array &$parameters): array
    {
        $searchPhrase = json_decode($searchPhrase, true);
        $dateRangeSelector = $searchPhrase['dateRangeSelector']??'';
        $typeCall = $searchPhrase['typeCall']??'';

        $parameters['conditions'] = '';
        $start = ''; $end = '';
        // Search date ranges
        if (preg_match_all("/\d{2}\/\d{2}\/\d{4}/", $dateRangeSelector, $matches)) {
            if (count($matches[0]) === 1) {
                $date = DateTime::createFromFormat('d/m/Y', $matches[0][0]);
                $start = $date->format('Y-m-d');
                $end = $date->modify('+1 day')->format('Y-m-d');
                $parameters['conditions'] .= 'start BETWEEN :dateFromPhrase1: AND :dateFromPhrase2:';
                $parameters['bind']['dateFromPhrase1'] = $start;
                $parameters['bind']['dateFromPhrase2'] = $end;
                $searchPhrase = str_replace($matches[0][0], "", $searchPhrase);
            } elseif (count($matches[0]) === 2) {
                $parameters['conditions'] .= 'start BETWEEN :dateFromPhrase1: AND :dateFromPhrase2:';
                $date = DateTime::createFromFormat('d/m/Y', $matches[0][0]);
                $start = $date->format('Y-m-d');
                $parameters['bind']['dateFromPhrase1'] = $start;
                $date = DateTime::createFromFormat('d/m/Y', $matches[0][1]);
                $end = $date->modify('+1 day')->format('Y-m-d');
                $parameters['bind']['dateFromPhrase2'] = $end;
                $searchPhrase = str_replace(
                    [$matches[0][0], $matches[0][1]],
                    '',
                    $searchPhrase
                );
            }
        }
        if(stripos($typeCall, 'incoming') === 0){
            if($parameters['conditions'] !== ''){
                $parameters['conditions'] .= ' AND ';
            }
            $parameters['conditions'].= "typeCall=:typeCall:";
            $parameters['bind']['typeCall'] = CallHistory::CALL_TYPE_INCOMING;
        }elseif (stripos($typeCall, 'missed') === 0){
            if($parameters['conditions'] !== ''){
                $parameters['conditions'] .= ' AND ';
            }
            $parameters['conditions'].= "typeCall=:typeCall:";
            $parameters['bind']['typeCall'] = CallHistory::CALL_TYPE_MISSED;
        }elseif (stripos($typeCall, 'outgoing') === 0){
            if($parameters['conditions'] !== ''){
                $parameters['conditions'] .= ' AND ';
            }
            $parameters['conditions'].= "typeCall=:typeCall:";
            $parameters['bind']['typeCall'] = CallHistory::CALL_TYPE_OUTGOING;
        }

        $additionalFilter = $searchPhrase['additionalFilter']??'';
        // Search phone numbers
        $searchPhrase = str_replace(['(', ')', '-', '+'], '', $searchPhrase);
        $groupNumber = [];
        if( class_exists('\Modules\ModuleUsersGroups\Models\UsersGroups')
            && preg_match_all('/(?:\s|^)group_(\d+)\b/', $additionalFilter, $matches)){
            $filter = [
                'group_id IN ({group_id:array})',
                'bind' => [
                    'group_id' => array_unique($matches[1])
                ],
                'columns' => 'user_id'
            ];
            $uIds = array_column(GroupMembers::find($filter)->toArray(), 'user_id');
            if(!empty($uIds)){
                $filter = [
                    'type=:type: AND userid IN ({userid:array})',
                    'bind' => [
                        'type'    => Extensions::TYPE_SIP,
                        'userid'  => $uIds,
                    ],
                    'columns' => 'number,userid'

                ];
                $groupNumber = array_column(Extensions::find($filter)->toArray(), 'number');
            }
        }

        $additionalNumbers = [];
        if(preg_match_all('/(?<=\s|^)\d+(?=\s|$)/', $additionalFilter, $matches)){
            $additionalNumbers = $matches[0];
        }
        $additionalNumbers = array_merge($additionalNumbers, $groupNumber);
        foreach ($additionalNumbers as $index => $value){
            $additionalNumbers[$index] = ConnectorDB::getPhoneIndex($value);
        }

        $globalNumbers = [];
        $globalSearch = $searchPhrase['globalSearch']??'';
        if(preg_match_all('/(?<=\s|^)\d+(?=\s|$)/', $globalSearch, $matches)){
            $globalNumbers = $matches[0];
        }
        foreach ($globalNumbers as $index => $value){
            $globalNumbers[$index] = ConnectorDB::getPhoneIndex($value);
        }

        if(!empty($globalNumbers)){
            if($parameters['conditions'] !== ''){
                $parameters['conditions'] .= ' AND ';
            }
            $parameters['conditions'] .= '(srcIndex IN ({globalNumbers:array}) OR dstIndex IN ({globalNumbers:array}))';
            $parameters['bind']['globalNumbers'] = array_unique($globalNumbers);
        }

        if(!empty($additionalNumbers)){
            if($parameters['conditions'] !== ''){
                $parameters['conditions'] .= ' AND ';
            }
            $parameters['conditions'] .= '(srcIndex IN ({additionslNumbers:array}) OR dstIndex IN ({additionslNumbers:array}))';
            $parameters['bind']['additionslNumbers'] = array_unique($additionalNumbers);
        }

        return [$start, $end, $globalNumbers, $additionalNumbers];
    }

    /**
     * Select CDR records with filters based on the provided parameters.
     *
     * @param array $parameters The parameters for filtering CDR records.
     * @return array The selected CDR records.
     */
    private function selectCDRRecordsWithFilters(array $parameters): array
    {
        // Apply ACL filters to CDR query using hook method
        PBXConfModulesProvider::hookModulesMethod(CDRConfigInterface::APPLY_ACL_FILTERS_TO_CDR_QUERY, [&$parameters]);

        // Retrieve CDR records based on the filtered parameters
        return ConnectorDB::invoke('getCdr', [$parameters]);;
    }

}