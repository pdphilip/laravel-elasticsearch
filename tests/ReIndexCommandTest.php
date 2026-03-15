<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use PDPhilip\Elasticsearch\Schema\Blueprint;
use PDPhilip\Elasticsearch\Schema\Schema;
use PDPhilip\Elasticsearch\Tests\Models\ReIndexTarget;

function refreshIndex(string $index): void
{
    DB::connection('elasticsearch')->getClient()->indices()->refresh(['index' => $index]);
}

beforeEach(function () {
    $schema = Schema::connection('elasticsearch');
    $schema->dropIfExists('re_index_targets');
    $schema->dropIfExists('re_index_targets_temp');

    $schema->create('re_index_targets', function (Blueprint $index) {
        $index->text('name');
        $index->date('created_at');
        $index->date('updated_at');
    });
});

afterEach(function () {
    $schema = Schema::connection('elasticsearch');
    $schema->dropIfExists('re_index_targets');
    $schema->dropIfExists('re_index_targets_temp');
});

it('re-indexes with updated mappings', function () {
    ReIndexTarget::insert([
        ['name' => 'Alpha', 'status' => 'active'],
        ['name' => 'Bravo', 'status' => 'inactive'],
        ['name' => 'Charlie', 'status' => 'active'],
    ]);

    $this->artisan('elastic:re-index', [
        'model' => ReIndexTarget::class,
        '--force' => true,
    ])->assertSuccessful();

    refreshIndex('re_index_targets');

    $count = DB::connection('elasticsearch')->table('re_index_targets')->count();
    expect($count)->toBe(3);

    $mapping = Schema::connection('elasticsearch')->getFieldsMapping('re_index_targets');
    expect($mapping)->toHaveKey('status', 'keyword');
});

it('fails when model class does not exist', function () {
    $this->artisan('elastic:re-index', [
        'model' => 'App\\Models\\NonExistentModel',
        '--force' => true,
    ])->assertFailed();
});

it('fails when model lacks mappingDefinition override', function () {
    $this->artisan('elastic:re-index', [
        'model' => \PDPhilip\Elasticsearch\Tests\Models\Product::class,
        '--force' => true,
    ])->assertFailed();
});

it('handles an empty index by recreating it', function () {
    // Empty index: command drops and recreates, then exits early (no re-index needed)
    $this->artisan('elastic:re-index', [
        'model' => ReIndexTarget::class,
        '--force' => true,
    ])->assertFailed();

    $mapping = Schema::connection('elasticsearch')->getFieldsMapping('re_index_targets');
    expect($mapping)->toHaveKey('status', 'keyword');
});

it('skips re-index when mapping already matches', function () {
    $schema = Schema::connection('elasticsearch');
    $schema->dropIfExists('re_index_targets');

    $schema->create('re_index_targets', function (Blueprint $index) {
        $index->keyword('status');
        $index->text('name');
        $index->date('created_at');
        $index->date('updated_at');
    });

    ReIndexTarget::insert([
        ['name' => 'Alpha', 'status' => 'active'],
    ]);

    // Mapping matches — exits early (no re-index needed)
    $this->artisan('elastic:re-index', [
        'model' => ReIndexTarget::class,
        '--force' => true,
    ])->assertFailed();

    expect(Schema::connection('elasticsearch')->hasTable('re_index_targets_temp'))->toBeFalse();
});

it('cleans up temp index after successful re-index', function () {
    ReIndexTarget::insert([
        ['name' => 'Alpha', 'status' => 'active'],
    ]);

    $this->artisan('elastic:re-index', [
        'model' => ReIndexTarget::class,
        '--force' => true,
    ])->assertSuccessful();

    expect(Schema::connection('elasticsearch')->hasTable('re_index_targets_temp'))->toBeFalse();
});

it('preserves all documents after re-index', function () {
    $records = [];
    for ($i = 0; $i < 20; $i++) {
        $records[] = ['name' => 'Record '.$i, 'status' => $i % 2 === 0 ? 'active' : 'inactive'];
    }
    ReIndexTarget::insert($records);

    $countBefore = DB::connection('elasticsearch')->table('re_index_targets')->count();

    $this->artisan('elastic:re-index', [
        'model' => ReIndexTarget::class,
        '--force' => true,
    ])->assertSuccessful();

    refreshIndex('re_index_targets');

    $countAfter = DB::connection('elasticsearch')->table('re_index_targets')->count();
    expect($countAfter)->toBe($countBefore);
});

it('resumes when leftover temp index exists with matching data', function () {
    ReIndexTarget::insert([
        ['name' => 'Alpha', 'status' => 'active'],
        ['name' => 'Bravo', 'status' => 'inactive'],
    ]);

    $schema = Schema::connection('elasticsearch');
    $schema->create('re_index_targets_temp', function (Blueprint $index) {
        $index->keyword('status');
        $index->text('name');
        $index->date('created_at');
        $index->date('updated_at');
    });
    $schema->reindex('re_index_targets', 're_index_targets_temp');

    $this->artisan('elastic:re-index', [
        'model' => ReIndexTarget::class,
        '--force' => true,
    ])->assertSuccessful();

    refreshIndex('re_index_targets');

    $count = DB::connection('elasticsearch')->table('re_index_targets')->count();
    expect($count)->toBe(2);

    expect($schema->hasTable('re_index_targets_temp'))->toBeFalse();
});

it('drops empty leftover temp and starts fresh', function () {
    ReIndexTarget::insert([
        ['name' => 'Alpha', 'status' => 'active'],
    ]);

    $schema = Schema::connection('elasticsearch');
    $schema->create('re_index_targets_temp', function (Blueprint $index) {
        $index->keyword('status');
        $index->text('name');
    });

    $this->artisan('elastic:re-index', [
        'model' => ReIndexTarget::class,
        '--force' => true,
    ])->assertSuccessful();

    refreshIndex('re_index_targets');

    $count = DB::connection('elasticsearch')->table('re_index_targets')->count();
    expect($count)->toBe(1);

    $mapping = Schema::connection('elasticsearch')->getFieldsMapping('re_index_targets');
    expect($mapping)->toHaveKey('status', 'keyword');
});
