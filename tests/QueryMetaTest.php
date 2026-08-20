<?php

declare(strict_types=1);

use PDPhilip\Elasticsearch\Data\QueryMeta;
use PDPhilip\Elasticsearch\Eloquent\ElasticCollection;
use PDPhilip\Elasticsearch\Tests\Models\User;

beforeEach(function () {
    User::executeSchema();

    User::insert([
        ['name' => 'John Doe', 'age' => 35, 'title' => 'admin'],
        ['name' => 'Jane Doe', 'age' => 33, 'title' => 'admin'],
        ['name' => 'Harry Hoe', 'age' => 13, 'title' => 'user'],
        ['name' => 'Robert Roe', 'age' => 37, 'title' => 'user'],
        ['name' => 'Mark Moe', 'age' => 23, 'title' => 'user'],
    ]);
});

it('carries the query meta on a page that has results', function () {
    $results = User::where('title', 'user')->paginate(2);

    expect($results->getCollection())->toBeInstanceOf(ElasticCollection::class)
        ->and($results->getCollection()->hasQueryMeta())->toBeTrue()
        ->and($results->total())->toBe(3)
        ->and($results->getQueryMeta()->getTotalHits())->toBe(3);
});

it('carries the query meta on a page that matched nothing', function () {
    $results = User::where('age', 999)->paginate(2);

    expect($results->getCollection())->toBeInstanceOf(ElasticCollection::class)
        ->and($results->getCollection()->hasQueryMeta())->toBeTrue()
        ->and($results->count())->toBe(0)
        ->and($results->total())->toBe(0)
        ->and($results->getQueryMeta())->toBeInstanceOf(QueryMeta::class)
        ->and($results->getQueryMeta()->getTotalHits())->toBe(0)
        ->and($results->getQueryMeta()->getTook())->toBeGreaterThanOrEqual(0);
});

it('reports the full total on a page that matched nothing when tracking total hits', function () {
    $results = User::where('age', 999)->withTrackTotalHits()->paginate(2);

    expect($results->total())->toBe(0)
        ->and($results->getQueryMeta()->getTotalHits())->toBe(0);
});

it('keeps the query meta through paginator transforms', function () {
    $emptyPage = User::where('age', 999)->paginate(2)
        ->withQueryString()
        ->through(fn ($user) => ['name' => $user->name]);

    $fullPage = User::where('title', 'user')->paginate(2)
        ->withQueryString()
        ->through(fn ($user) => ['name' => $user->name]);

    expect($emptyPage->getQueryMeta()->getTotalHits())->toBe(0)
        ->and($fullPage->getQueryMeta()->getTotalHits())->toBe(3);
});

it('answers every meta getter on a collection built without a query', function () {
    $collection = new ElasticCollection;

    expect($collection->hasQueryMeta())->toBeFalse()
        ->and($collection->getQueryMeta())->toBeInstanceOf(QueryMeta::class)
        ->and($collection->getQueryMeta()->getTotalHits())->toBe(-1)
        ->and($collection->getQueryMetaAsArray())->toBe([])
        ->and($collection->getTook())->toBe(-1)
        ->and($collection->getTotal())->toBe(-1)
        ->and($collection->getMaxScore())->toBe('')
        ->and($collection->getShards())->toBe([])
        ->and($collection->getDsl())->toBe(['query' => '', 'dsl' => []])
        ->and($collection->getResults())->toBe([])
        ->and($collection->getAfterKey())->toBe([])
        ->and($collection->getPitId())->toBeNull()
        ->and($collection->hasQueryMeta())->toBeFalse();
});

it('builds meta safe collections for models', function () {
    $newCollection = (new User)->newCollection();
    $hydrated = User::hydrate([['name' => 'John Doe']]);

    expect($newCollection)->toBeInstanceOf(ElasticCollection::class)
        ->and($newCollection->hasQueryMeta())->toBeFalse()
        ->and($newCollection->getQueryMeta()->getTotalHits())->toBe(-1)
        ->and($hydrated)->toBeInstanceOf(ElasticCollection::class)
        ->and($hydrated->hasQueryMeta())->toBeFalse()
        ->and($hydrated->getQueryMeta()->getTotalHits())->toBe(-1);
});

it('keeps the query meta on a fetched collection', function () {
    $users = User::where('title', 'user')->get();

    expect($users->hasQueryMeta())->toBeTrue()
        ->and($users->getQueryMeta()->getTotalHits())->toBe(3)
        ->and($users->getTook())->toBeGreaterThanOrEqual(0);
});
