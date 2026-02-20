<?php

declare(strict_types=1);

it('runs elastic:status successfully', function () {
    $this->artisan('elastic:status')
        ->assertSuccessful();
});

it('runs elastic:indices successfully', function () {
    $this->artisan('elastic:indices')
        ->assertSuccessful();
});

it('runs elastic:indices --all successfully', function () {
    $this->artisan('elastic:indices', ['--all' => true])
        ->assertSuccessful();
});

it('runs elastic:show on an existing index', function () {
    // Ensure the users index exists
    \PDPhilip\Elasticsearch\Tests\Models\User::executeSchema();

    $this->artisan('elastic:show', ['index' => 'users'])
        ->assertSuccessful();
});

it('elastic:show fails on non-existent index', function () {
    $this->artisan('elastic:show', ['index' => 'nonexistent_index_xyz'])
        ->assertFailed();
});
