<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use PDPhilip\Elasticsearch\Exceptions\QueryException;
use PDPhilip\Elasticsearch\Tests\Models\PageHit;

// Set up the database schema before each test
beforeEach(function () {
    PageHit::executeSchema();
});

it('tests first or fail', function () {
    $results = DB::table('users')->whereRaw(['foo'])->get();
})->throws(QueryException::class, 'Error Type: x_content_parse_exception
Reason: [1:27] [bool] failed to parse field [must]


Root Cause Type: x_content_parse_exception
Root Cause Reason: [1:27] [bool] failed to parse field [must]
Caused By: illegal_state_exception
Reason: expected value but got [START_ARRAY]');
