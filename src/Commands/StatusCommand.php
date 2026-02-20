<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use OmniTerm\HasOmniTerm;
use PDPhilip\Elasticsearch\Connection;

class StatusCommand extends Command
{
    use HasOmniTerm;

    protected $signature = 'elastic:status {--connection=elasticsearch}';

    protected $description = 'Elasticsearch connection health check';

    public function handle(): int
    {
        $connectionName = $this->option('connection');

        $this->newLine();
        $this->omni->roundedBox('Elasticsearch Status', 'text-cyan-500', 'text-cyan-500');
        $this->newLine();

        try {
            /** @var Connection $connection */
            $connection = DB::connection($connectionName);
        } catch (Exception $e) {
            $this->omni->statusError('CONNECTION FAILED', $e->getMessage(), [
                'Check your database config for connection: '.$connectionName,
            ]);
            $this->newLine();

            return self::FAILURE;
        }

        $config = $connection->getConfig();
        $hosts = $config['hosts'] ?? [];
        $prefix = $connection->getIndexPrefix() ?: '(none)';

        $maskedHosts = collect($hosts)->map(function ($host) {
            $parsed = parse_url($host);
            $scheme = $parsed['scheme'] ?? 'http';
            $hostPart = $parsed['host'] ?? $host;
            $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';

            return $scheme.'://'.$hostPart.$port;
        })->implode(', ');

        $this->omni->tableHeader('Connection', 'Value');
        $this->omni->tableRow('Name', $connectionName);
        $this->omni->tableRow('Auth Type', $config['auth_type'] ?? 'unknown');
        $this->omni->tableRow('Hosts', $maskedHosts ?: '(cloud)');
        $this->omni->tableRow('Index Prefix', $prefix);
        $this->newLine();

        $this->omni->newLoader('dots');
        $result = $this->omni->runTask('Connecting to Elasticsearch', function () use ($connection) {
            try {
                $info = $connection->getClientInfo();

                $license = null;
                try {
                    $license = $connection->getLicenseInfo();
                } catch (Exception) {
                    // License endpoint may not be available on OSS
                }

                return [
                    'state' => 'success',
                    'message' => 'Connected',
                    'data' => ['info' => $info, 'license' => $license],
                ];
            } catch (Exception $e) {
                return [
                    'state' => 'error',
                    'message' => 'Connection Failed',
                    'details' => $e->getMessage(),
                ];
            }
        });

        if (empty($result['data'])) {
            $this->newLine();
            $this->omni->statusError(
                'CONNECTION FAILED',
                $result['details'] ?? 'Unable to connect',
                [
                    'Check that Elasticsearch is running',
                    'Verify hosts, credentials, and auth_type in config/database.php',
                ]
            );
            $this->newLine();

            return self::FAILURE;
        }

        $info = $result['data']['info'];
        $license = $result['data']['license'];

        $this->newLine();
        $this->omni->tableHeader('Cluster', 'Value');
        $this->omni->tableRow('Cluster Name', $info['cluster_name'] ?? 'unknown');
        $this->omni->tableRow('Cluster UUID', $info['cluster_uuid'] ?? 'unknown');
        $this->omni->tableRow('Node Name', $info['name'] ?? 'unknown');
        $this->omni->tableRow('Version', $info['version']['number'] ?? 'unknown');

        if ($license) {
            $licenseData = $license['license'] ?? $license;
            $this->newLine();
            $this->omni->tableHeader('License', 'Value');
            $this->omni->tableRow('Type', $licenseData['type'] ?? 'unknown');
            $this->omni->tableRow('Status', $licenseData['status'] ?? 'unknown');

            if (! empty($licenseData['expiry_date_in_millis'])) {
                $expiry = date('Y-m-d', (int) ($licenseData['expiry_date_in_millis'] / 1000));
                $this->omni->tableRow('Expires', $expiry);
            }
        }

        $this->newLine();
        $this->omni->success('Connection OK');
        $this->newLine();

        return self::SUCCESS;
    }
}
