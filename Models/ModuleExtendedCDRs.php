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

class ModuleExtendedCDRs extends ModulesModelsBase
{
    /**
     * @Primary
     * @Identity
     * @Column(type="integer", nullable=false)
     */
    public $id;

    /**
     * @Column(type="integer", default=1, nullable=true)
     */
    public $cdrOffset = 1;

    /**
     * @Column(type="string", nullable=true)
     */
    public $referenceDate;

    /**
     * @Column(type="string", nullable=true)
     */
    public $searchSettings;





    /**
     * TextArea field example
     *
     * @Column(type="string", nullable=true)
     */
    public $text_area_field;

    /**
     * Password field example
     *
     * @Column(type="string", nullable=true)
     */
    public $password_field;

    /**
     * CheckBox
     *
     * @Column(type="integer", default="1", nullable=true)
     */
    public $checkbox_field;

    /**
     * Toggle
     *
     * @Column(type="integer", default="1", nullable=true)
     */
    public $toggle_field;

    /**
     * Dropdown menu
     *
     * @Column(type="string", nullable=true)
     */
    public $dropdown_field;

    public function initialize(): void
    {
        $this->setSource('m_ModuleExtendedCDRs');
        parent::initialize();
    }

}