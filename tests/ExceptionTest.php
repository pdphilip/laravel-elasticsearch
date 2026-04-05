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
})->throws(QueryException::class, 'Error Type: parsing_exception
Reason: Unknown key for a START_ARRAY in [query].


Root Cause Type: parsing_exception
Root Cause Reason: Unknown key for a START_ARRAY in [query]');

it('handles error responses without error.type structure', function () {
    $body = json_encode(['status' => 400, 'message' => 'Something went wrong']);
    $response = new \GuzzleHttp\Psr7\Response(400, [], $body);
    $previous = new \Elastic\Elasticsearch\Exception\ClientResponseException($body, 400);
    $previous->setResponse($response);

    $exception = new QueryException($previous);

    expect($exception)->toBeInstanceOf(QueryException::class)
        ->and($exception->getMessage())->toBe($body);
});

it('handles error responses with null json body', function () {
    $response = new \GuzzleHttp\Psr7\Response(400, [], 'not json at all');
    $previous = new \Elastic\Elasticsearch\Exception\ClientResponseException('not json at all', 400);
    $previous->setResponse($response);

    $exception = new QueryException($previous);

    expect($exception)->toBeInstanceOf(QueryException::class)
        ->and($exception->getMessage())->toBe('not json at all');
});
