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

/**
 * @package Modules\ExportResults\Models
 * @Indexes(
 *     [name='cdrId', columns=['cdrId'], type=''],
 *     [name='result', columns=['result'], type='']
 * )
 */
class ExportResults extends ModulesModelsBase
{

    /**
     * @Primary
     * @Identity
     * @Column(type="integer", nullable=false)
     */
    public $id;

    /**
     * id строки cdr.
     * @Column(type="string", default="", nullable=true)
     */
    public $cdrId;

    /**
     * Имя файла для выгрузки на сервер.
     * @Column(type="string", default="", nullable=true)
     */
    public $resFilename;

    /**
     * Полный путь к исходному файлу.
     * @Column(type="string", default="", nullable=true)
     */
    public $srcFilename;

    /**
     * Результат
     * @Column(type="integer", default="0", nullable=true)
     */
    public $result = 0;

    public function initialize(): void
    {
        $this->setSource('m_ExportResults');
        parent::initialize();
    }
}