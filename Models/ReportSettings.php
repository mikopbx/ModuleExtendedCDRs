<?php
/**
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 2 2019
 */

/*
 * https://docs.phalcon.io/4.0/en/db-models
 *
 */

namespace Modules\ModuleExtendedCDRs\Models;

use MikoPBX\Modules\Models\ModulesModelsBase;

class ReportSettings extends ModulesModelsBase
{
    public const REPORT_MAIN = 'CallDetails';
    public const REPORT_OUTGOING_EMPLOYEE_CALLS = 'OutgoingEmployeeCalls';

    /**
     * @Primary
     * @Identity
     * @Column(type="integer", nullable=false)
     */
    public $id;

    /**
     * Идентификатор отчета
     * @Column(type="string", default="", nullable=true)
     */
    public $reportNameID;

    /**
     * Идентификатор пользователя
     * @Column(type="string", default="", nullable=true)
     */
    public $userID;

    /**
     * Идентификатор варианта отчета
     * @Column(type="string", default="", nullable=true)
     */
    public $variantId;

    /**
     * Список ID пользователей через запятую.
     * @Column(type="string", default="", nullable=true)
     */
    public $variantName;

    /**
     * Список ID пользователей через запятую.
     * @Column(type="string", default="", nullable=true)
     */
    public $searchText;

    /**
     * Список ID пользователей через запятую.
     * @Column(type="integer", default="0", nullable=true)
     */
    public $minBillSec;

    /**
     * Список ID пользователей через запятую.
     * @Column(type="integer", default="0", nullable=true)
     */
    public $isMain = 0;

    /**
     * @Column(type="integer", default="0", nullable=true)
     */
    public $sendingScheduledReport = 0;

    /**
     * @Column(type="string", default="", nullable=true)
     */
    public $dateMonth;

    /**
     * @Column(type="string", default="", nullable=true)
     */
    public $day;

    /**
     * @Column(type="string", default="", nullable=true)
     */
    public $time;

    /**
     * @Column(type="string", default="", nullable=true)
     */
    public $email;

    public function initialize(): void
    {
        $this->setSource('m_ReportSettings');
        parent::initialize();
    }
}