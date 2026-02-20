<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use OmniTerm\HasOmniTerm;
use PDPhilip\Elasticsearch\Connection;

class ShowCommand extends Command
{
    use HasOmniTerm;

    protected $signature = 'elastic:show {index} {--connection=elasticsearch}';

    protected $description = 'Inspect an Elasticsearch index';

    public function handle(): int
    {
        $indexName = $this->argument('index');
        $connectionName = $this->option('connection');

        $this->newLine();
        $this->omni->roundedBox('Index: '.$indexName, 'text-cyan-500', 'text-cyan-500');
        $this->newLine();

        try {
            /** @var Connection $connection */
            $connection = DB::connection($connectionName);
            $schema = $connection->getSchemaBuilder();
        } catch (Exception $e) {
            $this->omni->statusError('CONNECTION FAILED', $e->getMessage());
            $this->newLine();

            return self::FAILURE;
        }

        if (! $schema->hasTable($indexName)) {
            $this->omni->statusError('NOT FOUND', 'Index "'.$indexName.'" does not exist', [
                'Run `php artisan elastic:indices` to see available indices',
            ]);
            $this->newLine();

            return self::FAILURE;
        }

        $this->showOverview($schema, $indexName);
        $this->showMappings($schema, $indexName);
        $this->showSettings($schema, $connection, $indexName);

        $this->newLine();

        return self::SUCCESS;
    }

    private function showOverview($schema, string $indexName): void
    {
        try {
            $tableInfo = $schema->getTable($indexName);
            $info = $tableInfo[0] ?? [];
        } catch (Exception) {
            return;
        }

        $health = $info['health'] ?? 'unknown';

        $this->omni->tableHeader('Overview', 'Value');
        $this->omni->tableRow('UUID', $info['uuid'] ?? 'unknown');
        $this->omni->tableRow('Documents', number_format((int) ($info['docs_count'] ?? 0)));
        $this->omni->tableRow('Deleted Docs', number_format((int) ($info['docs_deleted'] ?? 0)));
        $this->omni->tableRow('Store Size', $info['store_size'] ?? '0b');
        $this->omni->tableRow('Status', $info['status'] ?? 'unknown');

        match ($health) {
            'green' => $this->omni->tableRowSuccess('Health', 'green'),
            'yellow' => $this->omni->tableRowWarning('Health', 'yellow'),
            'red' => $this->omni->tableRowError('Health', 'red'),
            default => $this->omni->tableRow('Health', $health),
        };
    }

    private function showMappings($schema, string $indexName): void
    {
        try {
            $mappings = $schema->getMappings($indexName);
        } catch (Exception) {
            return;
        }

        if (empty($mappings)) {
            return;
        }

        $this->newLine();
        $this->omni->tableHeader('Field', 'Type');

        foreach ($mappings as $field => $details) {
            $type = $details['type'] ?? 'object';
            $isSubField = str_contains($field, '.');

            if ($isSubField) {
                $this->omni->tableRow('  '.$field, $type, null, 'text-gray-500');
            } else {
                $this->omni->tableRow($field, $type);
            }
        }
    }

    private function showSettings($schema, Connection $connection, string $indexName): void
    {
        try {
            $fullSettings = $schema->getSettings($indexName);
            $fullIndexName = $connection->getIndexPrefix().$indexName;
            $indexSettings = Arr::get($fullSettings, $fullIndexName.'.settings.index', []);
        } catch (Exception) {
            return;
        }

        if (empty($indexSettings)) {
            return;
        }

        $this->newLine();
        $this->omni->tableHeader('Setting', 'Value');
        $this->omni->tableRow('Shards', $indexSettings['number_of_shards'] ?? 'N/A');
        $this->omni->tableRow('Replicas', $indexSettings['number_of_replicas'] ?? 'N/A');

        if (! empty($indexSettings['creation_date'])) {
            $this->omni->tableRow('Created', date('Y-m-d H:i:s', (int) ($indexSettings['creation_date'] / 1000)));
        }

        if (empty($indexSettings['analysis'])) {
            return;
        }

        $this->newLine();
        $this->omni->tableHeader('Analysis', 'Config');

        $analysis = $indexSettings['analysis'];

        if (! empty($analysis['analyzer'])) {
            foreach ($analysis['analyzer'] as $name => $config) {
                $this->omni->tableRow('Analyzer: '.$name, $this->summarizeConfig($config));
            }
        }

        if (! empty($analysis['filter'])) {
            foreach ($analysis['filter'] as $name => $config) {
                $this->omni->tableRow('Filter: '.$name, $this->summarizeConfig($config));
            }
        }

        if (! empty($analysis['normalizer'])) {
            foreach ($analysis['normalizer'] as $name => $config) {
                $this->omni->tableRow('Normalizer: '.$name, $this->summarizeConfig($config));
            }
        }
    }

    private function summarizeConfig(array $config): string
    {
        return collect($config)->map(function ($val, $key) {
            if (is_array($val)) {
                return $key.': ['.implode(', ', $val).']';
            }

            return $key.': '.$val;
        })->implode(', ');
    }
}
