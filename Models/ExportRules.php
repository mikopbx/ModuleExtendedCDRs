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

namespace Modules\ModuleExportRecords\Models;

use MikoPBX\Modules\Models\ModulesModelsBase;

class ExportRules extends ModulesModelsBase
{

    /**
     * @Primary
     * @Identity
     * @Column(type="integer", nullable=false)
     */
    public $id;

    /**
     * Список ID пользователей через запятую.
     * @Column(type="string", default="", nullable=true)
     */
    public $users;

    /**
     * Наименование роли.
     * @Column(type="string", default="", nullable=true)
     */
    public $name;

    /**
     * Список ID пользователей через запятую.
     * @Column(type="string", default="", nullable=true)
     */
    public $dstUrl;

    /**
     * Дополнительные заголовки
     * @Column(type="string", default="", nullable=true)
     */
    public $headers;

    public function initialize(): void
    {
        $this->setSource('m_ExportRules');
        parent::initialize();
    }
}