<?php

declare(strict_types=1);

use PDPhilip\Elasticsearch\Schema\Schema;
use Workbench\App\Models\PageHit;

beforeEach(function () {
    collect([
        '2021-01-01',
        '2021-01-02',
        '2021-01-03',
        '2021-01-04',
        '2021-01-05',
        '2021-01-06',
        '2021-01-07',
        '2021-01-08',
        '2021-01-09',
        '2021-01-10',
    ])->each(function (string $index) {
        Schema::deleteIfExists('page_hits_'.$index);
    });
});

test('retrieve page hits across dynamic indices', function () {
    PageHit::factory()->count(15)->state(['page_id' => 1])->make()->each(function ($pageHit) {
        $pageHit->setIndex('page_hits_'.$pageHit->date);
        $pageHit->saveWithoutRefresh();
    });
    sleep(2);

    $pageHitsSearch = PageHit::where('page_id', 1)->get();
    expect($pageHitsSearch)->toHaveCount(15);

});

test('create a page hit record with dynamic index', function () {
    $pageHit = new PageHit;
    $pageHit->ip = '192.168.1.1';
    $pageHit->page_id = 4;
    $pageHit->date = '2021-01-01';
    $pageHit->setIndex('page_hits_'.$pageHit->date);
    $pageHit->save();

    $retrievedHits = PageHit::where('page_id', 4)->get();
    expect($retrievedHits)->toHaveCount(1)
        ->and($retrievedHits->first()->ip)->toEqual('192.168.1.1');
});

test('retrieve current record index', function () {
    $pageHit = new PageHit;
    $pageHit->ip = '192.168.1.100';
    $pageHit->page_id = 5;
    $pageHit->date = '2021-01-01';
    $pageHit->setIndex('page_hits_'.$pageHit->date);
    $pageHit->save();

    $indexName = $pageHit->getRecordIndex();
    expect($indexName)->toEqual('page_hits_*');
});

test('search within a specific dynamic index', function () {
    $pageHit = new PageHit;
    $pageHit->ip = '192.168.1.100';
    $pageHit->page_id = 3;
    $pageHit->date = '2021-01-02';
    $pageHit->setIndex('page_hits_'.$pageHit->date);
    $pageHit->save();

    $pageHit = new PageHit;
    $pageHit->ip = '192.168.1.100';
    $pageHit->page_id = 3;
    $pageHit->date = '2021-01-01';
    $pageHit->setIndex('page_hits_'.$pageHit->date);
    $pageHit->save();

    $model = new PageHit;
    $model->setIndex('page_hits_2021-01-01');

    $pageHits = $model->where('page_id', 3)->get();
    expect($pageHits)->toHaveCount(1);
});
