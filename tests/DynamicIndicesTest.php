<?php

declare(strict_types=1);

  use PDPhilip\Elasticsearch\Tests\Models\PageHit;

  beforeEach(function () {
    PageHit::executeSchema();
});

test('creates using a suffix', function () {
    $pageHit = new PageHit;
    $pageHit['page_id'] = 1;
    $pageHit['page_name'] = 'foo';
    $pageHit->setSuffix('_2021-01-01')->save();

    $check = PageHit::withSuffix('_2021-01-01')->find($pageHit->id);
    expect($check->getTable())->toBe('page_hits_2021-01-01');
});

test('retrieve page hits across dynamic indices', function () {
  $pageHit = new PageHit;
  $pageHit['page_id'] = 1;
  $pageHit['page_name'] = 'foo';
  $pageHit->setSuffix('_2021-01-01')->save();

  $pageHit = new PageHit;
  $pageHit['page_id'] = 1;
  $pageHit['page_name'] = 'bar';
  $pageHit->setSuffix('_2021-01-02')->save();

  $pageHit = new PageHit;
  $pageHit['page_id'] = 1;
  $pageHit['page_name'] = 'baz';
  $pageHit->setSuffix('_2021-01-03')->save();

  $check = PageHit::withSuffix('_2021-01-02')->get();
  expect($check)->toHaveCount(1);

  $check = PageHit::withSuffix('_2021-01*')->get();
  expect($check)->toHaveCount(3);
});
