<?php
namespace MikoPBX\Core\System {
    class Util {
        public static function mwMkdir($path) { if (!is_dir($path)) mkdir($path, 0777, true); }
        public static function sysLogMsg($context, $message) { if ($GLOBALS['loggerFails'] ?? false) throw new \RuntimeException('Logger failed'); fwrite(STDERR, $message . "\n"); }
    }
}
namespace MikoPBX\Modules\Models {
    class ModulesModelsBase { public static function getConnectionServiceName($id) { return 'settings'; } }
}
namespace Modules\ModuleExtendedCDRs\Lib\Providers {
    class CdrDbProvider { const SERVICE_NAME = 'cdr'; }
}
namespace MikoPBX\Modules\Setup {
    class PbxExtensionSetupBase {
        protected $moduleDir;
        protected $moduleUniqueID = 'ModuleExtendedCDRs';
        protected $messages = [];
        public function __construct($dir) { $this->moduleDir = $dir; }
        public function getDI() { return new class { public function has($key) { return false; } }; }
        public function uninstallModule(bool $keepSettings = false): bool {
            if (($GLOBALS['unregisterFails'] ?? false)) return false;
            return $this->unInstallFiles($keepSettings);
        }
        public function unInstallFiles(bool $keepSettings = false): bool {
            // Simulates Core copying db, then removing module files.
            \copyTree($this->moduleDir . '/db', dirname($this->moduleDir) . '/Backup/ModuleExtendedCDRs/db');
            \removeTree($this->moduleDir);
            return true;
        }
        public function installFiles(): bool {
            \copyTree(dirname($this->moduleDir) . '/Backup/ModuleExtendedCDRs/db', $this->moduleDir . '/db');
            return true;
        }
        public function installModule(): bool { $this->installFiles(); return false; } // Failure after restoring db.
    }
}
namespace {
    require dirname(__DIR__) . '/Lib/DatabaseUpgradeStorage.php';
    require dirname(__DIR__) . '/Setup/PbxExtensionSetup.php';
    function removeTree($dir) {
        if (is_link($dir)) { unlink($dir); return; }
        if (!is_dir($dir)) return;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() && !$f->isLink() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($dir);
    }
    function copyTree($source, $dest) {
        if (is_link($source)) {
            if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0777, true);
            symlink(readlink($source), $dest);
            return;
        }
        if (!is_dir($source)) return;
        if (!is_dir($dest)) mkdir($dest, 0777, true);
        foreach (glob($source . '/*') as $file) {
            if (is_dir($file)) copyTree($file, $dest . '/' . basename($file));
            else copy($file, $dest . '/' . basename($file));
        }
    }
    $root = $argv[2] ?? (sys_get_temp_dir() . '/cdr-lifecycle-' . bin2hex(random_bytes(6)));
    $module = $root . '/ModuleExtendedCDRs';
    if (in_array($argv[1] ?? '', ['conflict', 'logger-failure'], true)) {
        $GLOBALS['loggerFails'] = $argv[1] === 'logger-failure';
        try {
            (new \Modules\ModuleExtendedCDRs\Setup\PbxExtensionSetup($module))->uninstallModule(true);
        } finally {
            removeTree($module); // Core cleanup MUST NOT execute on failed preservation.
        }
        exit(0);
    }
    mkdir($module . '/db', 0777, true);
    $pdo = new PDO('sqlite:' . $module . '/db/cdr.db');
    $pdo->exec('CREATE TABLE calls(id INTEGER PRIMARY KEY, number TEXT)');
    $pdo->exec("INSERT INTO calls VALUES (1,'204')");
    $pdo = null;
    $inode = fileinode($module . '/db/cdr.db');
    $setup = new \Modules\ModuleExtendedCDRs\Setup\PbxExtensionSetup($module);
    $setup->unInstallFiles(true);
    if (file_exists($root . '/Backup/ModuleExtendedCDRs/db/cdr.db')) throw new RuntimeException('Core copied history');
    mkdir($module . '/db', 0777, true);
    file_put_contents($module . '/db/folder4db', '');
    if ($setup->installModule()) throw new RuntimeException('Fixture installation should fail');
    removeTree($module); // New Core cleans up failed installation.
    mkdir($module . '/db', 0777, true);
    $setup->installFiles(); // Retry restores original after failure cleanup.
    if (fileinode($module . '/db/cdr.db') !== $inode) throw new RuntimeException('Database copied/lost during failure recovery');
    $pdo = new PDO('sqlite:' . $module . '/db/cdr.db');
    if ($pdo->query('SELECT number FROM calls')->fetchColumn() !== '204') throw new RuntimeException('Row lost');
    $pdo = null;
    mkdir($root . '/.ModuleExtendedCDRs-upgrade/db', 0777, true);
    file_put_contents($root . '/.ModuleExtendedCDRs-upgrade/db/cdr.db', 'older preserved copy');
    foreach (['conflict', 'logger-failure'] as $scenario) {
        $command = escapeshellarg(PHP_BINARY) . ' -n ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg($scenario) . ' ' . escapeshellarg($root);
        exec($command . ' 2>&1', $output, $code);
        if ($code !== 1 || !file_exists($module . '/db/cdr.db')) throw new RuntimeException('Core deleted source after preservation failure: ' . $scenario);
    }
    unlink($root . '/.ModuleExtendedCDRs-upgrade/db/cdr.db');
    rmdir($root . '/.ModuleExtendedCDRs-upgrade/db');
    $GLOBALS['unregisterFails'] = true;
    $setup->uninstallModule(true);
    removeTree($module); // Even unregister failure must preserve data before Core cleanup.
    mkdir($module . '/db', 0777, true);
    $setup->installFiles();
    if (fileinode($module . '/db/cdr.db') !== $inode) throw new RuntimeException('Unregister failure lost database');
    removeTree($root);
    echo "DatabaseUpgradeLifecycleTest: OK\n";
}
