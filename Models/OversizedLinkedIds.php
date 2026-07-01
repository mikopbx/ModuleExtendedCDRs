<?php
/**
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 7 2026
 */

namespace Modules\ModuleExtendedCDRs\Models;

use MikoPBX\Common\Models\ModelsBase;
use Modules\ModuleExtendedCDRs\Lib\MikoPBXVersion;
use Modules\ModuleExtendedCDRs\Lib\Providers\CdrDbProvider;

/**
 * Class OversizedLinkedIds
 *
 * Служебный список "раздутых" linkedid — звонков, у которых число CDR-строк
 * достигает потолка выборки ядра (MikoPBX SelectCDR MAX_QUERY_LIMIT = 5000).
 * Обычно это зависшие каналы (конференции, парковки, MOH, подвисший local-канал),
 * которые бесконечно генерируют строки под одним linkedid и блокируют продвижение
 * offset синхронизации. Такие linkedid исключаются из запроса истории: их первые
 * 5000 строк сохраняются один раз, остальные игнорируются.
 *
 * @package Modules\ModuleExtendedCDRs\Models
 *
 * @Indexes(
 *     [name='linkedid', columns=['linkedid'], type='unique']
 * )
 */
class OversizedLinkedIds extends ModelsBase
{
    /**
     * @Primary
     * @Identity
     * @Column(type="integer", nullable=false)
     */
    public $id;

    /**
     * Идентификатор звонка (linkedid), исключённый из синхронизации.
     * @Column(type="string", nullable=false)
     */
    public ?string $linkedid = '';

    /**
     * Число строк на момент обнаружения (достигает потолка 5000).
     * @Column(type="integer", nullable=true)
     */
    public ?int $rowCount = 0;

    /**
     * Максимальный id среди сохранённых (первых 5000) строк.
     * @Column(type="integer", nullable=true)
     */
    public ?int $maxId = 0;

    /**
     * Дата обнаружения в формате 'YYYY-MM-DD HH:MM:SS'.
     * @Column(type="string", nullable=true)
     */
    public ?string $detectedAt = '';

    /**
     * Создаёт служебную таблицу oversized_linkedids, если её ещё нет.
     * Единый источник схемы: вызывается воркерами ConnectorDB и SyncRecords при старте.
     * @return void
     */
    public static function ensureTableExists(): void
    {
        $di = MikoPBXVersion::getDefaultDi();
        if ($di === null) {
            return;
        }
        if (!$di->has(CdrDbProvider::SERVICE_NAME)) {
            $di->register(new CdrDbProvider());
        }
        $db = $di->getShared(CdrDbProvider::SERVICE_NAME);
        $db->execute("CREATE TABLE IF NOT EXISTS oversized_linkedids (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            linkedid TEXT NOT NULL UNIQUE,
            rowCount INTEGER DEFAULT 0,
            maxId INTEGER DEFAULT 0,
            detectedAt TEXT DEFAULT ''
        )");
    }

    /**
     * Initialize model.
     */
    public function initialize(): void
    {
        $this->setSource('oversized_linkedids');
        parent::initialize();
        $this->useDynamicUpdate(true);
        if (!$this->di->has(CdrDbProvider::SERVICE_NAME)) {
            $this->di->register(new CdrDbProvider());
        }
        $this->setConnectionService(CdrDbProvider::SERVICE_NAME);
    }
}
