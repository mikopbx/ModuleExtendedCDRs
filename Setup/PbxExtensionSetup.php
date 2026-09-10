<?php
/**
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 5 2019
 */

namespace Modules\ModuleExtendedCDRs\Setup;

use MikoPBX\Modules\Setup\PbxExtensionSetupBase;
use MikoPBX\Common\Providers\ModulesDBConnectionsProvider;
use MikoPBX\Core\System\Upgrade\UpdateDatabase;
use MikoPBX\Core\System\Util;
use MikoPBX\Modules\Models\ModulesModelsBase;
use Modules\ModuleExtendedCDRs\Lib\DatabaseUpgradeStorage;
use Modules\ModuleExtendedCDRs\Lib\CdrSchemaUpgrade;
use Modules\ModuleExtendedCDRs\Lib\Providers\CdrDbProvider;
use Throwable;


/**
 * Class PbxExtensionSetup
 * Module installer and uninstaller
 *
 * @package Modules\ModuleExtendedCDRs\Setup
 */
class PbxExtensionSetup extends PbxExtensionSetupBase
{

    /**
     * PbxExtensionSetup constructor.
     *
     * @param string $moduleUniqueID - the unique module identifier
     */
    public function __construct(string $moduleUniqueID)
    {
        parent::__construct($moduleUniqueID);

    }

    /**
     * Creates database structure according to models annotations
     *
     * If it necessary, it fills some default settings, and change sidebar menu item representation for this module
     *
     * After installation it registers module on PbxExtensionModules model
     *
     *
     * @return bool result of installation
     */
    public function installDB(): bool
    {
        $result = $this->createSettingsTableByModelsAnnotations();

        if ($result) {
            $result = $this->registerNewModule();
        }

        if ($result) {
            $result = $this->addToSidebar();
        }

        return $result;
    }

    /**
     * Create folders on PBX system and apply rights
     *
     * @return bool result of installation
     */
    public function installFiles(): bool
    {
        $storage = new DatabaseUpgradeStorage($this->moduleDir);
        // Also avoid the return copy when upgrading from the legacy installer.
        $storage->adoptLegacyBackup();
        if (!parent::installFiles()) {
            return false;
        }
        $storage->restore();
        return true;
    }

    /**
     * Unregister module on PbxExtensionModules,
     * Makes data backup if $keepSettings is true
     *
     * Before delete module we can do some soft delete changes, f.e. change forwarding rules i.e.
     *
     * @param  $keepSettings bool creates backup folder with module settings
     *
     * @return bool uninstall result
     */
    public function unInstallDB($keepSettings = false): bool
    {
        return parent::unInstallDB($keepSettings);
    }

    public function unInstallFiles(bool $keepSettings = false): bool
    {
        if ($keepSettings) {
            $this->preserveDatabaseOrStop();
            Util::mwMkdir($this->moduleDir . '/db');
            touch($this->moduleDir . '/db/folder4db');
        }
        return parent::unInstallFiles($keepSettings);
    }

    public function uninstallModule(bool $keepSettings = false): bool
    {
        try {
            return parent::uninstallModule($keepSettings);
        } finally {
            // Core may skip unInstallFiles when unregistering fails, then force-delete moduleDir.
            if ($keepSettings) $this->preserveDatabaseOrStop();
        }
    }

    public function installModule(): bool
    {
        try {
            $success = parent::installModule();
        } catch (Throwable $e) {
            $this->messages[] = $e->getMessage();
            $success = false;
        }
        if (!$success) {
            // Newer Core removes the module directory after a failed installation.
            $this->preserveDatabaseOrStop();
        }
        return $success;
    }

    private function preserveDatabaseOrStop(): void
    {
        try {
            $di = $this->getDI();
            foreach ([CdrDbProvider::SERVICE_NAME, ModulesModelsBase::getConnectionServiceName($this->moduleUniqueID)] as $service) {
                if ($di->has($service) && $di->getService($service)->isResolved()) {
                    $di->getShared($service)->close();
                    $di->remove($service);
                }
            }
            $storage = new DatabaseUpgradeStorage($this->moduleDir);
            $storage->preserve();
        } catch (Throwable $e) {
            // Core's uninstall finally block force-deletes moduleDir even on false/throw.
            // exit skips that finally block; a stopped update is preferable to lost history.
            try {
                Util::sysLogMsg('ModuleExtendedCDRs-upgrade', 'Database preservation failed; installer stopped: ' . $e->getMessage());
            } finally {
                exit(1);
            }
        }
    }

    public function createSettingsTableByModelsAnnotations(): bool
    {
        ModulesDBConnectionsProvider::recreateModulesDBConnections();
        try {
            $cdrModels = [];
            foreach (glob($this->moduleDir . '/Models/*.php') as $file) {
                $class = 'Modules\\' . $this->moduleUniqueID . '\\Models\\' . pathinfo($file, PATHINFO_FILENAME);
                $model = new $class();
                if ($model->getReadConnectionService() === CdrDbProvider::SERVICE_NAME) {
                    $cdrModels[] = $class;
                } elseif (!(new UpdateDatabase())->createUpdateDbTableByAnnotations($class)) {
                    return false;
                }
            }
            if ($cdrModels) (new CdrSchemaUpgrade())->upgrade($cdrModels);
            return true;
        } catch (Throwable $e) {
            $this->messages[] = $e->getMessage();
            return false;
        } finally {
            ModulesDBConnectionsProvider::recreateModulesDBConnections();
        }
    }

}