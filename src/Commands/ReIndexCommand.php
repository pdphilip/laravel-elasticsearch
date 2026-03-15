<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Commands;

use Closure;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use OmniTerm\HasOmniTerm;
use PDPhilip\Elasticsearch\Connection;
use PDPhilip\Elasticsearch\Eloquent\Model;
use PDPhilip\Elasticsearch\Schema\Blueprint;
use PDPhilip\Elasticsearch\Schema\Builder as SchemaBuilder;
use PDPhilip\Elasticsearch\Utils\Helpers;

class ReIndexCommand extends Command
{
    use HasOmniTerm;

    protected $signature = 'elastic:re-index {model : The Elasticsearch model class name} {--force : Skip confirmation prompts}';

    protected $description = 'Re-index an Elasticsearch index with updated field mappings';

    private string $indexName;

    private string $tempIndexName;

    private string $connectionName;

    private Connection $connection;

    private SchemaBuilder $schema;

    private Closure $mappingDefinition;

    private int $originalCount = 0;

    private int $tempCount = 0;

    private int $finalCount = 0;

    private float $tolerance = 0.001;

    private int $maxRetries = 3;

    private float $startTime = 0;

    // ======================================================================
    // Handle
    // ======================================================================

    public function handle(): int
    {
        $this->startTime = microtime(true);

        $model = $this->resolveModel();
        if (! $model) {
            return self::FAILURE;
        }

        $this->setupFromModel($model);

        $this->newLine();
        $this->omni->titleBar('ES Re-Index: '.$this->indexName, 'pink');
        $this->newLine();

        if (! $this->option('force') && ! $this->confirmSettings()) {
            return self::SUCCESS;
        }

        $resumeAt = $this->validate();
        if ($resumeAt === false) {
            return self::FAILURE;
        }

        // Safe zone: create temp, copy, verify
        if ($resumeAt === 'CREATE_TEMP') {
            if (! $this->confirmContinue('Phase 2: Create Temp Index')) {
                return self::SUCCESS;
            }
            if (! $this->createTempIndex()) {
                return self::FAILURE;
            }
            if (! $this->confirmContinue('Phase 3: Copy to Temp')) {
                return self::SUCCESS;
            }
            if (! $this->copyToTemp()) {
                return self::FAILURE;
            }
            if (! $this->confirmContinue('Phase 4: Verify Temp')) {
                return self::SUCCESS;
            }
            if (! $this->verifyTemp()) {
                return self::FAILURE;
            }
        } elseif ($resumeAt === 'VERIFY_TEMP') {
            if (! $this->verifyTemp()) {
                return self::FAILURE;
            }
        }

        // Confirmation gate before danger zone
        if ($resumeAt !== 'CREATE_ORIGINAL') {
            $this->omni->hr();
            $this->omni->warning('About to enter the danger zone — original index will be dropped');
            $this->omni->tableRow('Original', $this->indexName.' ('.$this->originalCount.' docs)');
            $this->omni->tableRow('Temp', $this->tempIndexName.' ('.$this->tempCount.' docs)');
            $this->omni->hr();

            if (! $this->option('force') && ! $this->promptYesNo('Drop original and swap?')) {
                $this->omni->info('Aborted. Temp index preserved for manual inspection.');
                $this->newLine();

                return self::SUCCESS;
            }

            if (! $this->dropOriginal()) {
                return self::FAILURE;
            }
        } else {
            $this->omni->warning('Resuming in danger zone — original already gone, temp is source of truth');
        }

        if (! $this->confirmContinue('Phase 6: Create Original (New Mapping)')) {
            return self::SUCCESS;
        }
        if (! $this->createOriginal()) {
            return self::FAILURE;
        }

        if (! $this->confirmContinue('Phase 7: Copy Back')) {
            return self::SUCCESS;
        }
        if (! $this->copyBack()) {
            return self::FAILURE;
        }

        if (! $this->confirmContinue('Phase 8: Verify Final')) {
            return self::SUCCESS;
        }
        if (! $this->verifyFinal()) {
            return self::FAILURE;
        }

        if (! $this->confirmContinue('Phase 9: Cleanup')) {
            return self::SUCCESS;
        }
        $this->cleanup();
        $this->summary();

        return self::SUCCESS;
    }

    // ======================================================================
    // Model Resolution
    // ======================================================================

    private function resolveModel(): ?Model
    {
        $input = $this->argument('model');

        // Try direct resolution: FQCN, App\Models\, App\
        $class = $this->resolveDirectClass($input);

        // Scan app/Models if direct resolution failed
        if (! $class) {
            $matches = $this->scanForElasticsearchModel($input);

            if (count($matches) === 1) {
                $class = $matches[0];
            } elseif (count($matches) > 1) {
                $this->newLine();
                $this->omni->statusError('Multiple models match "'.$input.'"', 'Be more specific', array_map(
                    fn ($c) => 'elastic:re-index "'.$c.'"',
                    $matches
                ));
                $this->newLine();

                return null;
            }
        }

        if (! $class) {
            $this->newLine();
            $this->omni->statusError('Model not found', $input, [
                'Provide a fully qualified class name or a short class name:',
                'Example: elastic:re-index "App\\Models\\Indexes\\SignalEvent"',
                'Example: elastic:re-index SignalEvent',
            ]);
            $this->newLine();

            return null;
        }

        if (! $class::hasMappingDefinition()) {
            $this->newLine();
            $this->omni->statusError('Missing mapping definition', $class, [
                'Your model must override mappingDefinition():',
            ]);
            $this->omni->render(<<<'HTML'
<code line="3" start-line="1">
use PDPhilip\Elasticsearch\Schema\Blueprint;

public static function mappingDefinition(Blueprint $index): void
{
    $index->keyword('status');
    $index->geoPoint('location');
}
</code>
HTML);
            $this->newLine();

            return null;
        }

        return new $class;
    }

    private function resolveDirectClass(string $input): ?string
    {
        $candidates = [
            $input,
            'App\\Models\\'.$input,
            'App\\'.$input,
        ];

        foreach ($candidates as $class) {
            if (class_exists($class) && Model::isElasticsearchModel($class)) {
                return $class;
            }
        }

        return null;
    }

    private function scanForElasticsearchModel(string $name): array
    {
        $modelsPath = app_path('Models');
        if (! is_dir($modelsPath)) {
            return [];
        }

        $matches = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($modelsPath, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php' || $file->getBasename('.php') !== $name) {
                continue;
            }

            $relativePath = str_replace($modelsPath, '', $file->getPathname());
            $class = 'App\\Models'.str_replace(['/', '\\', '.php'], ['\\', '\\', ''], $relativePath);

            if (class_exists($class) && Model::isElasticsearchModel($class)) {
                $matches[] = $class;
            }
        }

        return $matches;
    }

    private function setupFromModel(Model $model): void
    {
        $this->indexName = $model->getTable();
        $this->tempIndexName = $this->indexName.'_temp';
        $this->connectionName = $model->getConnectionName();

        $this->connection = DB::connection($this->connectionName);
        $this->schema = $this->connection->getSchemaBuilder();

        $this->mappingDefinition = fn (Blueprint $index) => $model::mappingDefinition($index);
    }

    // ======================================================================
    // Phase 1: Validate
    // ======================================================================

    private function validate(): string|false
    {
        $this->omni->divider('Phase 1: Validate');

        $originalExists = $this->schema->hasTable($this->indexName);
        $tempExists = $this->schema->hasTable($this->tempIndexName);

        if (! $originalExists && ! $tempExists) {
            $this->omni->error('Both indices missing — catastrophic state, manual recovery needed');
            $this->newLine();

            return false;
        }

        if ($tempExists) {
            return $this->validateWithExistingTemp($originalExists);
        }

        if (! $originalExists) {
            $this->omni->error('Index "'.$this->indexName.'" does not exist');
            $this->newLine();

            return false;
        }

        $this->originalCount = $this->countDocs($this->indexName);

        if ($this->originalCount === 0) {
            return $this->handleEmptyIndex();
        }

        $analysis = $this->mappingAnalysis();

        if (empty($analysis['mismatches'])) {
            $this->omni->success('Mapping already matches — nothing to re-index');
            $this->newLine();

            return false;
        }

        $fieldsToUpdate = [];
        foreach ($analysis['mismatches'] as $field => $info) {
            $fieldsToUpdate[$field] = $info['current'].' → '.$info['desired'];
        }
        $this->omni->dataList($fieldsToUpdate, 'Fields to Update', 'text-emerald-500');

        if (! empty($analysis['unmapped'])) {
            $this->omni->dataList($analysis['unmapped'], 'Unmapped Fields', 'text-rose-500');
        }

        return 'CREATE_TEMP';
    }

    private function validateWithExistingTemp(bool $originalExists): string|false
    {
        $this->tempCount = $this->countDocs($this->tempIndexName);

        if (! $originalExists) {
            $this->omni->warning('Original missing, temp has '.$this->tempCount.' docs');
            $this->omni->info('Resuming from danger zone — temp is source of truth');
            $this->originalCount = $this->tempCount;

            return 'CREATE_ORIGINAL';
        }

        $this->originalCount = $this->countDocs($this->indexName);

        if ($this->tempCount === 0) {
            $this->omni->info('Empty temp from previous run — dropping, starting fresh');
            $this->schema->dropIfExists($this->tempIndexName);

            return 'CREATE_TEMP';
        }

        if ($this->countsMatch($this->originalCount, $this->tempCount)) {
            $this->omni->info('Temp has matching data ('.$this->tempCount.' docs) — resuming at verify');

            return 'VERIFY_TEMP';
        }

        $this->omni->info('Partial temp ('.$this->tempCount.'/'.$this->originalCount.') — dropping, starting fresh');
        $this->schema->dropIfExists($this->tempIndexName);

        return 'CREATE_TEMP';
    }

    private function handleEmptyIndex(): string|false
    {
        $this->omni->warning('Index has 0 records — just drop and recreate');

        if (! $this->option('force') && ! $this->promptYesNo('Drop and recreate with new mapping?')) {
            $this->newLine();

            return false;
        }

        $this->schema->drop($this->indexName);
        $this->schema->create($this->indexName, $this->mappingDefinition);
        $this->omni->success('Recreated empty index with new mapping');
        $this->showMappings($this->indexName);
        $this->newLine();

        return false;
    }

    // ======================================================================
    // Phase 2: Create Temp Index
    // ======================================================================

    private function createTempIndex(): bool
    {
        $this->omni->divider('Phase 2: Create Temp Index');

        try {
            $this->schema->create($this->tempIndexName, $this->mappingDefinition);
            $this->omni->success($this->tempIndexName.' created with new mapping');
            $this->showMappings($this->tempIndexName);

            return true;
        } catch (Exception $e) {
            $this->omni->statusError('Failed to create temp index', $e->getMessage());
            $this->newLine();

            return false;
        }
    }

    // ======================================================================
    // Phase 3: Copy to Temp
    // ======================================================================

    private function copyToTemp(): bool
    {
        $this->omni->divider('Phase 3: Copy to Temp');
        $this->omni->info('Reindexing '.$this->indexName.' → '.$this->tempIndexName.'...');

        try {
            $result = $this->schema->reindex($this->indexName, $this->tempIndexName);

            $failures = $result['failures'] ?? [];
            if (! empty($failures)) {
                $this->omni->error('Reindex reported '.count($failures).' failures');
                $this->rollbackTemp('Reindex had failures');

                return false;
            }

            $this->tempCount = (int) ($result['created'] ?? 0);
            $this->omni->success('Copy complete — '.$this->tempCount.' docs created');

            return true;
        } catch (Exception $e) {
            $this->omni->statusError('Reindex failed', $e->getMessage());
            $this->rollbackTemp($e->getMessage());

            return false;
        }
    }

    // ======================================================================
    // Phase 4: Verify Temp
    // ======================================================================

    private function verifyTemp(): bool
    {
        $this->omni->divider('Phase 4: Verify Temp');

        $this->omni->tableHeader('Metric', 'Value');
        $this->omni->tableRow('Original count', number_format($this->originalCount));
        $this->omni->tableRow('Temp count', number_format($this->tempCount));

        if (! $this->countsMatch($this->originalCount, $this->tempCount)) {
            $this->omni->error('Count mismatch beyond tolerance ('.($this->tolerance * 100).'%)');
            $this->rollbackTemp('Count mismatch: '.$this->tempCount.' vs '.$this->originalCount);

            return false;
        }

        $this->omni->tableRowSuccess('Counts match');
        $this->showMappings($this->tempIndexName);
        $this->omni->success('Temp verified');

        return true;
    }

    // ======================================================================
    // Phase 5: Drop Original
    // ======================================================================

    private function dropOriginal(): bool
    {
        $this->omni->divider('Phase 5: Drop Original');

        try {
            $this->schema->drop($this->indexName);
            sleep(1);
            $this->omni->success($this->indexName.' dropped');

            return true;
        } catch (Exception $e) {
            $this->omni->statusError('Failed to drop original', $e->getMessage());
            $this->omni->info('Safe state — original still exists. Drop temp manually if needed.');
            $this->newLine();

            return false;
        }
    }

    // ======================================================================
    // Phase 6: Create Original (New Mapping)
    // ======================================================================

    private function createOriginal(): bool
    {
        $this->omni->divider('Phase 6: Create Original (New Mapping)');

        if ($this->schema->hasTable($this->indexName)) {
            $this->omni->info('Partial original exists — dropping before recreate');
            try {
                $this->schema->drop($this->indexName);
                sleep(1);
            } catch (Exception $e) {
                $this->critical('Cannot drop partial original: '.$e->getMessage());

                return false;
            }
        }

        try {
            $this->schema->create($this->indexName, $this->mappingDefinition);
            $this->omni->success($this->indexName.' created with new mapping');

            return true;
        } catch (Exception $e) {
            $this->critical('Failed to create original: '.$e->getMessage());

            return false;
        }
    }

    // ======================================================================
    // Phase 7: Copy Back
    // ======================================================================

    private function copyBack(): bool
    {
        $this->omni->divider('Phase 7: Copy Back');

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            $this->omni->info('Reindexing '.$this->tempIndexName.' → '.$this->indexName.' (attempt '.$attempt.'/'.$this->maxRetries.')');

            try {
                $result = $this->schema->reindex($this->tempIndexName, $this->indexName);

                $failures = $result['failures'] ?? [];
                if (empty($failures)) {
                    $this->finalCount = (int) ($result['created'] ?? 0);
                    $this->omni->success('Copy back complete — '.$this->finalCount.' docs');

                    return true;
                }

                $this->omni->warning('Attempt '.$attempt.' had '.count($failures).' failures');
            } catch (Exception $e) {
                $this->omni->warning('Attempt '.$attempt.' failed: '.$e->getMessage());
            }

            if ($attempt < $this->maxRetries) {
                sleep(2);
            }
        }

        $this->critical('Copy back failed after '.$this->maxRetries.' attempts');

        return false;
    }

    // ======================================================================
    // Phase 8: Verify Final
    // ======================================================================

    private function verifyFinal(): bool
    {
        $this->omni->divider('Phase 8: Verify Final');

        $this->omni->tableHeader('Metric', 'Value');
        $this->omni->tableRow('Original snapshot', number_format($this->originalCount));
        $this->omni->tableRow('Temp count', number_format($this->tempCount));
        $this->omni->tableRow('Final count', number_format($this->finalCount));

        if ($this->countsMatch($this->tempCount, $this->finalCount)) {
            $this->omni->tableRowSuccess('Counts match');
            $this->showMappings($this->indexName);

            return true;
        }

        $this->omni->warning('Final count mismatch ('.$this->finalCount.' vs '.$this->tempCount.') — running catch-up');

        try {
            $result = $this->schema->reindex($this->tempIndexName, $this->indexName);
            $this->finalCount = (int) ($result['created'] ?? 0) + (int) ($result['updated'] ?? 0);

            if ($this->countsMatch($this->tempCount, $this->finalCount)) {
                $this->omni->success('Catch-up resolved the mismatch');

                return true;
            }
        } catch (Exception $e) {
            $this->omni->warning('Catch-up failed: '.$e->getMessage());
        }

        $this->critical('Final verification failed — '.$this->finalCount.' vs '.$this->tempCount);

        return false;
    }

    // ======================================================================
    // Phase 9: Cleanup
    // ======================================================================

    private function cleanup(): void
    {
        $this->omni->divider('Phase 9: Cleanup');

        try {
            $this->schema->dropIfExists($this->tempIndexName);
            $this->omni->success('Temp index dropped');
        } catch (Exception $e) {
            $this->omni->warning('Failed to drop temp (non-critical): '.$e->getMessage());
        }
    }

    // ======================================================================
    // Summary
    // ======================================================================

    private function summary(): void
    {
        $duration = round(microtime(true) - $this->startTime, 2);

        $data = [
            'Index' => $this->indexName,
            'Original count' => number_format($this->originalCount),
            'Final count' => number_format($this->finalCount),
            'Duration' => $duration.'s',

        ];
        $this->omni->dataList($data, 'Re-Index Complete', 'text-emerald-500');
        $this->newLine();
    }

    // ======================================================================
    // Helpers
    // ======================================================================

    private function countsMatch(int $expected, int $actual): bool
    {
        if ($expected === 0) {
            return $actual === 0;
        }

        return $actual >= $expected * (1 - $this->tolerance);
    }

    private function countDocs(string $index): int
    {
        return DB::connection($this->connectionName)->table($index)->count();
    }

    private function rollbackTemp(string $reason): void
    {
        $this->omni->warning('Rolling back — dropping temp index');
        $this->omni->info('Reason: '.$reason);

        try {
            $this->schema->dropIfExists($this->tempIndexName);
            $this->omni->info('Temp dropped, original untouched');
        } catch (Exception $e) {
            $this->omni->error('Failed to drop temp during rollback: '.$e->getMessage());
        }
    }

    private function critical(string $message): void
    {
        $this->omni->hrError();
        $this->omni->error('CRITICAL: '.$message);
        $this->omni->error('Data lives in temp index: '.$this->tempIndexName);
        $this->omni->error('DO NOT drop '.$this->tempIndexName.' — it is the only complete copy');
        $this->omni->hrError();
        $this->newLine();
    }

    private function showMappings(string $index): void
    {
        if (! $this->schema->hasTable($index)) {
            return;
        }

        $mapping = $this->schema->getFieldsMapping($index);
        $this->omni->dataList($mapping, $index.' mapping');
    }

    private function mappingAnalysis(): array
    {
        $currentMapping = $this->schema->getFieldsMapping($this->indexName);

        $blueprint = Helpers::getLaravelCompatabilityVersion() >= 12
            ? new Blueprint($this->connection, $this->indexName)
            : new Blueprint($this->indexName); // @phpstan-ignore arguments.count
        ($this->mappingDefinition)($blueprint);

        $definedFields = [];
        $mismatches = [];
        foreach ($blueprint->getAddedColumns() as $column) {
            $field = $column->name;
            $desiredType = $column->type;
            $definedFields[] = $field;
            $currentType = $currentMapping[$field] ?? null;

            if ($currentType !== $desiredType) {
                $mismatches[$field] = [
                    'current' => $currentType ?? 'missing',
                    'desired' => $desiredType,
                ];

                continue;
            }

            $subMismatch = $this->detectSubFieldMismatch($column, $field, $currentMapping);
            if ($subMismatch) {
                $mismatches[$field] = $subMismatch;
            }
        }

        $unmapped = [];
        foreach ($currentMapping as $field => $type) {
            if (in_array($field, $definedFields)) {
                continue;
            }
            if ($this->isSubFieldOfDefined($field, $definedFields)) {
                continue;
            }
            $unmapped[$field] = $type;
        }

        return [
            'mismatches' => $mismatches,
            'unmapped' => $unmapped,
        ];
    }

    private function detectSubFieldMismatch($column, string $field, array $currentMapping): ?array
    {
        $expectedSubs = $this->getExpectedSubFields($column);
        $currentSubs = $this->getCurrentSubFields($field, $currentMapping);

        if ($expectedSubs === $currentSubs) {
            return null;
        }

        $format = fn (array $subs) => empty($subs)
            ? $column->type
            : $column->type.' [+'.implode(', ', array_keys($subs)).']';

        return [
            'current' => $format($currentSubs),
            'desired' => $format($expectedSubs),
        ];
    }

    private function getExpectedSubFields($column): array
    {
        if (! ($column->fields instanceof Closure)) {
            return [];
        }

        $subBlueprint = Helpers::getLaravelCompatabilityVersion() >= 12
            ? new Blueprint($this->connection, '_sub')
            : new Blueprint('_sub'); // @phpstan-ignore arguments.count
        ($column->fields)($subBlueprint);

        $subs = [];
        foreach ($subBlueprint->getAddedColumns() as $subCol) {
            $subs[$subCol->name] = $subCol->type;
        }

        return $subs;
    }

    private function getCurrentSubFields(string $field, array $mapping): array
    {
        $prefix = $field.'.';
        $subs = [];
        foreach ($mapping as $key => $type) {
            if (! str_starts_with($key, $prefix)) {
                continue;
            }
            $subName = substr($key, strlen($prefix));
            if (! str_contains($subName, '.')) {
                $subs[$subName] = $type;
            }
        }

        return $subs;
    }

    private function isSubFieldOfDefined(string $field, array $definedFields): bool
    {
        foreach ($definedFields as $defined) {
            if (str_starts_with($field, $defined.'.')) {
                return true;
            }
        }

        return false;
    }

    private function confirmSettings(): bool
    {
        $this->omni->dataList([
            'Model' => $this->argument('model'),
            'Index' => $this->indexName,
            'Connection' => $this->connectionName,
            'Tolerance' => ($this->tolerance * 100).'%',
            'Max retries' => $this->maxRetries,
        ], 'Re-Index Settings');

        $answer = '';
        $valid = ['yes', 'y', 'edit', 'e', 'cancel', 'c', 'no', 'n'];

        while (! in_array(strtolower($answer), $valid)) {
            $answer = $this->omni->ask('Continue with these settings?', ['yes', 'edit', 'cancel']);
        }

        $answer = strtolower($answer);

        if (in_array($answer, ['cancel', 'c', 'no', 'n'])) {
            $this->omni->info('Cancelled.');
            $this->newLine();

            return false;
        }

        if (in_array($answer, ['edit', 'e'])) {
            $this->editSettings();
        }

        return true;
    }

    private function editSettings(): void
    {
        $tolerance = $this->omni->ask('Tolerance %', [0.1, 0.5, 1, 5], $this->tolerance * 100);
        if (is_numeric($tolerance) && $tolerance >= 0) {
            $this->tolerance = (float) $tolerance / 100;
        }

        $retries = $this->omni->ask('Max retries', [1, 3, 5, 10], $this->maxRetries);
        if (is_numeric($retries) && $retries >= 1) {
            $this->maxRetries = (int) $retries;
        }

        $this->omni->success('Settings updated — tolerance: '.($this->tolerance * 100).'%, retries: '.$this->maxRetries);
    }

    private function confirmContinue(string $nextPhase): bool
    {
        if ($this->option('force')) {
            return true;
        }

        return $this->promptYesNo('Continue to '.$nextPhase.'?');
    }

    private function promptYesNo(string $question): bool
    {
        $answer = '';
        $valid = ['yes', 'y', 'n', 'no'];

        while (! in_array(strtolower($answer), $valid)) {
            $answer = $this->omni->ask($question, ['yes', 'no']);
        }

        return in_array(strtolower($answer), ['yes', 'y']);
    }
}
