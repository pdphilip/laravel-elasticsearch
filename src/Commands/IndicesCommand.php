<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use OmniTerm\HasOmniTerm;
use PDPhilip\Elasticsearch\Connection;

class IndicesCommand extends Command
{
    use HasOmniTerm;

    protected $signature = 'elastic:indices {--all : Show all indices, not just prefixed} {--connection=elasticsearch}';

    protected $description = 'List all Elasticsearch indices';

    public function handle(): int
    {
        $connectionName = $this->option('connection');
        $showAll = $this->option('all');

        $this->newLine();
        $this->omni->titleBar('Elasticsearch Indices', 'teal');
        $this->newLine();

        try {
            /** @var Connection $connection */
            $connection = DB::connection($connectionName);
            $schema = $connection->getSchemaBuilder();

            if ($showAll) {
                $indices = $connection->getPostProcessor()->processTables(
                    $connection->catIndices(['index' => '*', 'format' => 'json'])
                );
            } else {
                $indices = $schema->getTables();
            }
        } catch (Exception $e) {
            $this->omni->statusError('ERROR', $e->getMessage());
            $this->newLine();

            return self::FAILURE;
        }

        $prefix = $connection->getIndexPrefix();

        $this->omni->tableRow('Connection', $connectionName);
        $this->omni->tableRow('Index Prefix', $prefix ?: '(none)');
        if ($showAll) {
            $this->omni->tableRow('Filter', 'All indices');
        }
        $this->newLine();

        if (empty($indices)) {
            $this->omni->warning('No indices found');
            $this->newLine();

            return self::SUCCESS;
        }

        $this->omni->tableHeader('Index', 'Health', 'Docs / Size');

        $totalDocs = 0;

        foreach ($indices as $index) {
            $name = $index['name'];
            $health = $index['health'] ?? 'unknown';
            $docsCount = (int) ($index['docs_count'] ?? 0);
            $storeSize = $index['store_size'] ?? '0b';

            $totalDocs += $docsCount;

            $displayName = $name;
            if (! $showAll && $prefix && str_starts_with($name, $prefix)) {
                $displayName = substr($name, strlen($prefix));
            }

            $details = number_format($docsCount).' docs / '.$storeSize;

            match ($health) {
                'green' => $this->omni->tableRowSuccess($displayName, $details),
                'yellow' => $this->omni->tableRowWarning($displayName, $details),
                'red' => $this->omni->tableRowError($displayName, $details),
                default => $this->omni->tableRow($displayName, $health, $details),
            };
        }

        $this->newLine();
        $this->omni->info(count($indices).' indices, '.number_format($totalDocs).' total documents');
        $this->newLine();

        return self::SUCCESS;
    }
}
