<?php

declare(strict_types=1);

namespace Modules\ModuleExtendedCDRs\Lib;

use MikoPBX\Common\Providers\ModelsMetadataProvider;
use MikoPBX\Core\System\Upgrade\UpdateDatabase;
use Modules\ModuleExtendedCDRs\Lib\Providers\CdrDbProvider;
use Phalcon\Db\Adapter\Pdo\Sqlite;
use Phalcon\Events\Manager;
use RuntimeException;
use Throwable;

/** Let the installed Core define its own canonical schema, but never rebuild populated CDR tables. */
final class CdrSchemaUpgrade
{
    public function upgrade(array $models): void
    {
        $di = MikoPBXVersion::getDefaultDi();
        $actual = $di->getShared(CdrDbProvider::SERVICE_NAME);
        $canonical = new Sqlite(['dbname' => ':memory:']);
        $di->remove(CdrDbProvider::SERVICE_NAME);
        $di->setShared(CdrDbProvider::SERVICE_NAME, $canonical);
        try {
            foreach ($models as $model) {
                if (!(new UpdateDatabase())->createUpdateDbTableByAnnotations($model)) {
                    throw new RuntimeException('Cannot build canonical schema for ' . $model);
                }
            }
        } finally {
            $di->remove(CdrDbProvider::SERVICE_NAME);
            $di->setShared(CdrDbProvider::SERVICE_NAME, $actual);
            $di->get(ModelsMetadataProvider::SERVICE_NAME)->reset();
        }
        (new AdditiveSqliteMigration())->migrate($actual->getInternalHandler(), $canonical->getInternalHandler());
        $this->verifyCoreDoesNotRewrite($actual, $models);
    }

    /** Execute the normal updater with a query guard: even a proposed rewrite is an error. */
    public function verifyCoreDoesNotRewrite($connection, array $models): void
    {
        $previous = $connection->getEventsManager();
        $events = new Manager();
        $events->attach('db:beforeQuery', static function ($event, $db): void {
            $sql = ltrim($db->getSQLStatement());
            if (!preg_match('/^(SELECT|PRAGMA|BEGIN|COMMIT|ROLLBACK|SAVEPOINT|RELEASE)\b/i', $sql)) {
                throw new RuntimeException('Core still requires a schema change after additive migration: ' . substr($sql, 0, 160));
            }
        });
        $connection->setEventsManager($events);
        try {
            foreach ($models as $model) {
                if (!(new UpdateDatabase())->createUpdateDbTableByAnnotations($model)) {
                    throw new RuntimeException('Core schema verification failed for ' . $model);
                }
            }
        } catch (Throwable $e) {
            if ($connection->isUnderTransaction()) $connection->rollback();
            throw $e;
        } finally {
            $connection->setEventsManager($previous ?? new Manager());
        }
    }
}
