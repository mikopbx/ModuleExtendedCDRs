<?php

declare(strict_types=1);

namespace Modules\ModuleExtendedCDRs\Lib;

use PDO;
use RuntimeException;
use Throwable;

/** Applies only trailing columns/new tables/indices from a Core-generated empty database. */
final class AdditiveSqliteMigration
{
    public function migrate(PDO $database, PDO $canonical): void
    {
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('PRAGMA busy_timeout=5000');
        $database->exec('BEGIN IMMEDIATE');
        try {
            $tables = $canonical->query("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($tables as $table) {
                $name = $this->quote($table['name']);
                $expected = $canonical->query('PRAGMA table_info(' . $name . ')')->fetchAll(PDO::FETCH_ASSOC);
                $actual = $database->query('PRAGMA table_info(' . $name . ')')->fetchAll(PDO::FETCH_ASSOC);
                if (!$actual) {
                    $database->exec($table['sql']);
                } else {
                    foreach ($actual as $i => $column) {
                        if (!isset($expected[$i]) || $column !== $expected[$i]) {
                            throw new RuntimeException('Non-additive schema change in ' . $table['name'] . '.' . $column['name'] . '; explicit migration required');
                        }
                    }
                    foreach (array_slice($expected, count($actual)) as $column) {
                        if ($column['pk'] || ($column['notnull'] && $column['dflt_value'] === null)) {
                            throw new RuntimeException('Cannot append required column without a default: ' . $column['name']);
                        }
                        $definition = $this->quote($column['name']) . ' ' . $column['type'];
                        if ($column['notnull']) $definition .= ' NOT NULL';
                        if ($column['dflt_value'] !== null) $definition .= ' DEFAULT ' . $column['dflt_value'];
                        $database->exec('ALTER TABLE ' . $name . ' ADD COLUMN ' . $definition);
                    }
                }
                $this->indexes($database, $canonical, $table['name']);
            }
            $database->exec('COMMIT');
        } catch (Throwable $e) {
            $database->exec('ROLLBACK');
            throw $e;
        }
    }

    private function indexes(PDO $database, PDO $canonical, string $table): void
    {
        $actual = $database->query('PRAGMA index_list(' . $this->quote($table) . ')')->fetchAll(PDO::FETCH_ASSOC);
        $actual = array_column($actual, null, 'name');
        foreach ($canonical->query('PRAGMA index_list(' . $this->quote($table) . ')')->fetchAll(PDO::FETCH_ASSOC) as $index) {
            if (strpos($index['name'], 'sqlite_') === 0) continue;
            $name = $this->quote($index['name']);
            if (isset($actual[$index['name']])) {
                if ($actual[$index['name']]['unique'] !== $index['unique']
                    || $actual[$index['name']]['partial'] !== $index['partial']
                    || $database->query('PRAGMA index_info(' . $name . ')')->fetchAll(PDO::FETCH_ASSOC)
                        !== $canonical->query('PRAGMA index_info(' . $name . ')')->fetchAll(PDO::FETCH_ASSOC)) {
                    throw new RuntimeException('Index definition differs: ' . $index['name']);
                }
            } else {
                $query = $canonical->prepare("SELECT sql FROM sqlite_master WHERE type='index' AND name=?");
                $query->execute([$index['name']]);
                $database->exec($query->fetchColumn());
            }
        }
    }

    private function quote(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}
