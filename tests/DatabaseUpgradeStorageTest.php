<?php
require_once dirname(__DIR__) . '/Lib/DatabaseUpgradeStorage.php';
use Modules\ModuleExtendedCDRs\Lib\DatabaseUpgradeStorage;
$root = sys_get_temp_dir() . '/cdr-upgrade-' . bin2hex(random_bytes(6));
mkdir($root . '/ModuleExtendedCDRs/db', 0777, true);
$module = $root . '/ModuleExtendedCDRs';
file_put_contents($module . '/db/cdr.db', 'history');
file_put_contents($module . '/db/cdr.db-wal', 'pending');
file_put_contents($module . '/db/module.db', 'settings');
$inode = fileinode($module . '/db/cdr.db');
$storage = new DatabaseUpgradeStorage($module);
$storage->preserve();
$storage->preserve(); // Retry after interruption must preserve the original.
if (file_exists($module . '/db/cdr.db')) throw new RuntimeException('Core would still copy history');
mkdir($module . '/db', 0777, true);
file_put_contents($module . '/db/folder4db', '');
$storage->restore();
$storage->restore();
if (fileinode($module . '/db/cdr.db') !== $inode || file_get_contents($module . '/db/cdr.db-wal') !== 'pending'
    || file_get_contents($module . '/db/module.db') !== 'settings') throw new RuntimeException('Database or sidecars were copied/lost');
$storage->preserve();
mkdir($module . '/db', 0777, true);
file_put_contents($module . '/db/cdr.db', 'different history');
try { $storage->restore(); throw new LogicException('Expected conflict'); }
catch (RuntimeException $e) { if ($e instanceof LogicException) throw $e; }
if (file_get_contents($module . '/db/cdr.db') !== 'different history') throw new RuntimeException('Overwrote conflicting history');
// Legacy Core backup is adopted without a second full copy.
$legacy = $root . '/Legacy';
mkdir($root . '/Backup/Legacy/db', 0777, true);
mkdir($legacy . '/db', 0777, true);
file_put_contents($root . '/Backup/Legacy/db/cdr.db', 'legacy');
$legacyInode = fileinode($root . '/Backup/Legacy/db/cdr.db');
$legacyStorage = new DatabaseUpgradeStorage($legacy);
$legacyStorage->adoptLegacyBackup();
$legacyStorage->restore();
if (fileinode($legacy . '/db/cdr.db') !== $legacyInode) throw new RuntimeException('Legacy restore copied database');

// Real SQLite WAL travels together with the database directory.
$walModule = $root . '/WalModule';
mkdir($walModule . '/db', 0777, true);
$writer = new PDO('sqlite:' . $walModule . '/db/cdr.db');
$writer->exec('PRAGMA journal_mode=WAL');
$writer->exec('CREATE TABLE calls(number TEXT)');
$writer->exec("INSERT INTO calls VALUES ('204')");
if (!file_exists($walModule . '/db/cdr.db-wal')) throw new RuntimeException('WAL fixture missing');
$walStorage = new DatabaseUpgradeStorage($walModule);
$walStorage->preserve();
$walStorage->restore();
$writer = null;
$reader = new PDO('sqlite:' . $walModule . '/db/cdr.db');
if ($reader->query('SELECT number FROM calls')->fetchColumn() !== '204') throw new RuntimeException('WAL data lost');
$reader = null;

// Cleanup only the isolated fixture.
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($it as $entry) { $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname()); }
rmdir($root);
echo "DatabaseUpgradeStorageTest: OK\n";
