<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use PDPhilip\Elasticsearch\Tests\Models\Birthday;
use PDPhilip\Elasticsearch\Tests\Models\Item;
use PDPhilip\Elasticsearch\Tests\Models\User;

beforeEach(function () {
    User::executeSchema();
    Item::executeSchema();
    Birthday::executeSchema();

    DB::table('items')->insert([
        ['name' => 'knife', 'type' => 'sharp', 'amount' => 34, 'stock' => 1],
        ['name' => 'fork', 'type' => 'sharp', 'amount' => 20, 'stock' => 5],
        ['name' => 'spoon', 'type' => 'round', 'amount' => 3, 'stock' => 15],
        ['name' => 'spoon', 'type' => 'round', 'amount' => 14, 'stock' => 7],
    ]);

    Birthday::insert([
        ['name' => 'Mark Moe', 'birthday' => new DateTime('2020-04-10 10:53:11')],
        ['name' => 'Jane Doe', 'birthday' => new DateTime('2021-05-12 10:53:12')],
        ['name' => 'Harry Hoe', 'birthday' => new DateTime('2021-05-11 10:53:13')],
        ['name' => 'Robert Doe', 'birthday' => new DateTime('2021-05-12 10:53:14')],
        ['name' => 'Mark Moe', 'birthday' => new DateTime('2021-05-12 10:53:15')],
        ['name' => 'Mark Moe', 'birthday' => new DateTime('2022-05-12 10:53:16')],
        ['name' => 'Error'],
    ]);

    $this->license = DB::connection('elasticsearch')->getLicenseType();
});

it('aggregate multiple metrics', function () {

    $items = DB::table('items')->bucket('type', 'terms')->getAggregationResults();
    expect($items)->toHaveCount(2)
        ->and($items[0]['type'])->toBe('round')
        ->and($items[0]['_meta']->getDocCount())->toBe(2);

    $items = DB::table('birthday')->bucket('birthday', 'date_histogram', ['field' => 'birthday', 'fixed_interval' => '30d'])->getAggregationResults();
    expect($items)->toHaveCount(26)
        ->and($items[0]['birthday'])->toBe(1586304000000)
        ->and($items[0]['_meta']->getDocCount())->toBe(1);

    $items = DB::table('birthday')->bucket('birthday', 'date_histogram', ['field' => 'birthday', 'calendar_interval' => '1y'])->getAggregationResults();
    expect($items)->toHaveCount(3)
        ->and($items[1]['birthday'])->toBe(1609459200000)
        ->and($items[1]['_meta']->getDocCount())->toBe(4);

    $items = DB::table('birthday')->bucket('birthday', 'missing', ['field' => 'birthday'])->getAggregationResults();
    expect($items)->toHaveCount(1)
        ->and($items['doc_count'])->toBe(1);

});

// categorize_text is not a Free/Basic License Elasticsearch Feature
// See https://www.elastic.co/subscriptions @Text categorization aggregation
it('aggregate categorize_text', function () {
    $items = DB::table('items')->bucket('name', 'categorize_text', ['field' => 'name'])->getAggregationResults();
    expect($items)->toHaveCount(3)
        ->and($items[0]['name'])->toBe('spoon')
        ->and($items[0]['_meta']->getDocCount())->toBe(2);
})->skip(fn () => $this->license === 'basic', 'Skipped as "categorize_text" is not a Free/Basic License Elasticsearch Feature');

it('aggregate multiple metrics and return a collection when using a model', function () {
    // @pest-ignore categorize-text-agg is not a Free/Basic License Elasticsearch Feature
    $items = Item::bucket('name', 'categorize_text', ['field' => 'name'])->getAggregationResults();
    expect($items)->toHaveCount(3)
        ->and($items[0]['name'])->toBe('spoon')
        ->and($items[0]['_meta']->getDocCount())->toBe(2);

})->skip(fn () => $this->license === 'basic', 'Skipped as "categorize_text" is not a Free/Basic License Elasticsearch Feature');
