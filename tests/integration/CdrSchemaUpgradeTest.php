<?php
/** Run on a PBX. Only in-memory databases are changed; production connections are restored. */
require 'Globals.php';
require_once dirname(__DIR__, 2) . '/Lib/AdditiveSqliteMigration.php';
require_once dirname(__DIR__, 2) . '/Lib/CdrSchemaUpgrade.php';
use Modules\ModuleExtendedCDRs\Lib\CdrSchemaUpgrade;
use Modules\ModuleExtendedCDRs\Lib\MikoPBXVersion;
use Modules\ModuleExtendedCDRs\Lib\Providers\CdrDbProvider;
use MikoPBX\Core\System\Upgrade\UpdateDatabase;
$di = MikoPBXVersion::getDefaultDi();
$di->register(new CdrDbProvider());
$real = $di->getShared(CdrDbProvider::SERVICE_NAME);
$db = new Phalcon\Db\Adapter\Pdo\Sqlite(['dbname'=>':memory:']);
$di->remove(CdrDbProvider::SERVICE_NAME);
        $di->setShared(CdrDbProvider::SERVICE_NAME, $db);
$models = [];
foreach (['CallHistory','CallQueuesHistory','DailyCallStats','OversizedLinkedIds'] as $name) $models[] = 'Modules\\ModuleExtendedCDRs\\Models\\'.$name;
try {
    $upgrade = new CdrSchemaUpgrade();
    $upgrade->upgrade($models); // Fresh install.
    $db->execute("INSERT INTO oversized_linkedids(linkedid,status) VALUES ('migration-test','pending')");
    $db->execute('ALTER TABLE oversized_linkedids DROP COLUMN status');
    $db->execute('DROP INDEX i_oversized_linkedids_linkedid');
    try {
        $upgrade->verifyCoreDoesNotRewrite($db, $models);
        throw new LogicException('Core rewrite guard did not reject an incomplete schema');
    } catch (RuntimeException $e) {
        if (strpos($e->getMessage(), 'Core still requires') === false) throw $e;
    }
    $before = $db->fetchColumn("SELECT rootpage FROM sqlite_master WHERE name='oversized_linkedids'");
    $upgrade->upgrade($models); // Existing rows, trailing field/index missing.
    $upgrade->upgrade($models); // Must be repeatable.
    if ($db->fetchColumn("SELECT rootpage FROM sqlite_master WHERE name='oversized_linkedids'") !== $before
        || $db->fetchColumn('SELECT linkedid FROM oversized_linkedids') !== 'migration-test') throw new RuntimeException('Table/row changed');
    // Runs the actual Core comparison/update with writes blocked, not our own comparison.
    $upgrade->verifyCoreDoesNotRewrite($db, $models);
    echo "CdrSchemaUpgradeTest: OK (fresh, append, preserved rootpage/data, retry, Core no-op)\n";
} finally {
    $di->remove(CdrDbProvider::SERVICE_NAME);
        $di->setShared(CdrDbProvider::SERVICE_NAME, $real);
}
