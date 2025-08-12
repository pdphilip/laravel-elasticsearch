<?php

declare(strict_types=1);

use PDPhilip\Elasticsearch\Exceptions\BulkInsertQueryException;
use PDPhilip\Elasticsearch\Tests\Models\User;

beforeEach(function () {
    User::executeSchema();
});

it('creates a document with createOnly and rejects duplicates', function () {
    $id = 'dataset:check-1:2025-01-01T00:00:00Z';

    // First create should succeed
    User::query()
        ->createOnly()
        ->withRefresh('wait_for')
        ->create([
            'id' => $id,
            'name' => 'First Insert',
            'title' => 'admin',
            'age' => 30,
        ]);

    $found = User::find($id);
    expect($found)->not()->toBeNull();
    expect($found->id)->toBe($id);

    // Second create with same _id must fail with 409 (bulk error)
    expect(function () use ($id) {
        User::query()
            ->createOnly()
            ->create([
                'id' => $id,
                'name' => 'Second Insert',
                'title' => 'user',
                'age' => 31,
            ]);
    })->toThrow(BulkInsertQueryException::class);
});

it('supports per-document op_type via attribute', function () {
    $id = 'dataset:check-2:2025-01-01T00:00:00Z';

    // Create with per-document op_type
    User::create([
        'id' => $id,
        '_op_type' => 'create',
        'name' => 'Doc Create',
        'title' => 'admin',
        'age' => 42,
    ]);

    $found = User::find($id);
    expect($found)->not()->toBeNull();
    expect($found->id)->toBe($id);

    // Duplicate should raise conflict
    expect(function () use ($id) {
        User::create([
            'id' => $id,
            '_op_type' => 'create',
            'name' => 'Doc Create Duplicate',
        ]);
    })->toThrow(BulkInsertQueryException::class);
});
