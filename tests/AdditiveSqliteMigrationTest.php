<?php
require_once dirname(__DIR__) . '/Lib/AdditiveSqliteMigration.php';
use Modules\ModuleExtendedCDRs\Lib\AdditiveSqliteMigration;
$a = new PDO('sqlite::memory:');
$b = new PDO('sqlite::memory:');
$a->exec('CREATE TABLE calls (id INTEGER PRIMARY KEY AUTOINCREMENT, number TEXT)');
$a->exec("INSERT INTO calls(number) VALUES ('204'),('203')");
$b->exec("CREATE TABLE calls (id INTEGER PRIMARY KEY AUTOINCREMENT, number TEXT, answered INTEGER NOT NULL DEFAULT 0)");
$b->exec('CREATE INDEX calls_number ON calls(number)');
$root = $a->query("SELECT rootpage FROM sqlite_master WHERE name='calls'")->fetchColumn();
$m = new AdditiveSqliteMigration();
$m->migrate($a, $b);
$m->migrate($a, $b);
if ($a->query('SELECT number FROM calls ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) !== ['204','203']
    || $a->query('SELECT SUM(answered) FROM calls')->fetchColumn() != 0
    || $a->query("SELECT rootpage FROM sqlite_master WHERE name='calls'")->fetchColumn() !== $root) throw new RuntimeException('Rows/table were rebuilt or defaults lost');
if ($a->query("SELECT COUNT(*) FROM sqlite_master WHERE name='calls_number'")->fetchColumn() != 1) throw new RuntimeException('Missing index');
$c = new PDO('sqlite::memory:');
$c->exec('CREATE TABLE calls (id INTEGER PRIMARY KEY AUTOINCREMENT, inserted TEXT, number TEXT, answered INTEGER NOT NULL DEFAULT 0)');
try { $m->migrate($a, $c); throw new LogicException('Expected non-additive rejection'); }
catch (RuntimeException $e) { if ($e instanceof LogicException) throw $e; }
if (count($a->query('PRAGMA table_info(calls)')->fetchAll()) !== 3) throw new RuntimeException('Failed migration changed table');
echo "AdditiveSqliteMigrationTest: OK\n";
