<?php
/**
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 1 2026
 */

namespace Modules\ModuleExtendedCDRs\Models;

use MikoPBX\Common\Models\ModelsBase;
use Modules\ModuleExtendedCDRs\Lib\Providers\CdrDbProvider;

/**
 * Class DailyCallStats
 *
 * Stores pre-aggregated daily call statistics for fast counting.
 * Instead of running expensive GROUP BY queries on millions of CDR records,
 * we store daily totals and sum them up for date range queries.
 *
 * @package Modules\ModuleExtendedCDRs\Models
 *
 * @Indexes(
 *     [name='date', columns=['date'], type='unique']
 * )
 */
class DailyCallStats extends ModelsBase
{
    /**
     * @Primary
     * @Identity
     * @Column(type="integer", nullable=false)
     */
    public $id;

    /**
     * Date in 'YYYY-MM-DD' format.
     * @Column(type="string", nullable=false)
     */
    public ?string $date = '';

    /**
     * Count of inner calls (typeCall=0).
     * @Column(type="integer", nullable=true)
     */
    public ?int $cInner = 0;

    /**
     * Count of outgoing calls (typeCall=1).
     * @Column(type="integer", nullable=true)
     */
    public ?int $cOutgoing = 0;

    /**
     * Count of incoming calls (typeCall=2).
     * @Column(type="integer", nullable=true)
     */
    public ?int $cIncoming = 0;

    /**
     * Count of missed calls (typeCall=3).
     * @Column(type="integer", nullable=true)
     */
    public ?int $cMissed = 0;

    /**
     * Total count of unique calls (linkedid) for this day.
     * @Column(type="integer", nullable=true)
     */
    public ?int $cTotal = 0;

    /**
     * Timestamp of last update.
     * @Column(type="string", nullable=true)
     */
    public ?string $updatedAt = '';

    /**
     * Initialize model.
     */
    public function initialize(): void
    {
        $this->setSource('daily_call_stats');
        parent::initialize();
        $this->useDynamicUpdate(true);
        if (!$this->di->has(CdrDbProvider::SERVICE_NAME)) {
            $this->di->register(new CdrDbProvider());
        }
        $this->setConnectionService(CdrDbProvider::SERVICE_NAME);
    }
}
