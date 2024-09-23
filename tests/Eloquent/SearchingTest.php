<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Factories\Sequence;
use Workbench\App\Models\Product;

it('can search for a term', function () {

    $products = Product::factory(100)
        ->state(new Sequence(
            ['manufacturer.country' => 'United States of America'],
            ['manufacturer.country' => 'Australia'],
            ['manufacturer.country' => 'Armenia']
        ))->make();
    Product::insert($products->toArray());

    $set = Product::term('United States America')->field('manufacturer.country')->search();
    $set2 = Product::term('United')->orTerm('States')->orTerm('America')->field('manufacturer.country')->search();

    expect(count($set))->toBeGreaterThan(0)
        ->and(count($set) == count($set2));

})->todo();

it('should find terms where minShouldMatch()', function () {
    $products = Product::factory(100)
        ->state(new Sequence(
            ['manufacturer.country' => 'United States of America', 'manufacturer.owned_by.country' => 'United States of America'],
            ['manufacturer.country' => 'Australia', 'manufacturer.owned_by.country' => 'Australia'],
            ['manufacturer.country' => 'Armenia', 'manufacturer.owned_by.country' => 'Armenia']
        ))->make();

    Product::insert($products->toArray());

    $records = Product::term('United States of America')->field('manufacturer.country')->minShouldMatch(3)->search();
    foreach ($records as $record) {
        expect($record->manufacturer['country'])->toBe('United States of America');
    }

    $records = Product::term('United States of America')->fields(['manufacturer.country', 'manufacturer.owned_by.country'])->minShouldMatch(3)->search();
    foreach ($records as $record) {
        expect($record->manufacturer['country'])->toBe('United States of America')
            ->and($record->manufacturer['owned_by']['country'])->toBe('United States of America');
    }
});

it('should find terms where minScore()', function () {
    $products = Product::factory(12)
        ->state(new Sequence(
            ['manufacturer.country' => 'United States of America', 'manufacturer.owned_by.country' => 'United States of America'],
            ['manufacturer.country' => 'Australia', 'manufacturer.owned_by.country' => 'Australia'],
            ['manufacturer.country' => 'Armenia', 'manufacturer.owned_by.country' => 'Armenia']
        ))->make();

    Product::insert($products->toArray());
    $search1 = Product::term('United States of')->minScore(1)->search();
    expect(count($search1))->toBeGreaterThan(1);

});

it('should work combined with where clauses', function () {
    $products = Product::factory(100)
        ->state(new Sequence(
            ['name' => 'bar', 'color' => 'red'],
            ['name' => 'foo', 'color' => 'blue'],
            ['name' => 'bar', 'color' => 'green'],
            ['name' => 'foo', 'color' => 'black'],
        ))->make();
    Product::insert($products->toArray());

    $search1 = Product::term('foo')->search();
    $search2 = Product::term('foo')->where('color', 'black')->search();

    expect(count($search1) > count($search2))
        ->toBeTrue('Search 1: '.count($search1).' Search 2: '.count($search2));
});

it('should work with return limit', function () {
    $products = Product::factory(100)
        ->state(new Sequence(
            ['color' => 'red'],
            ['color' => 'blue'],
            ['color' => 'green'],
            ['color' => 'black'],
        ))->make();
    Product::insert($products->toArray());

    $blues = Product::term('blue')->limit(5)->search();
    expect(count($blues))->toBe(5);
});

it('sorted by boosted field', function () {
    $products = Product::factory(100)
        ->state(new Sequence(
            ['color' => 'silver'],
            ['color' => 'blue'],
            ['color' => 'green'],
            ['color' => 'black'],
        ))->make();
    Product::insert($products->toArray());

    $records = Product::term('silver', 3)->orTerm('blue')->field('color')->search();
    $currentColor = 'silver';
    foreach ($records as $record) {
        if ($record->color == 'blue' && $currentColor == 'silver') {
            $currentColor = 'blue';
        }
        expect($record->color == $currentColor)
            ->toBeTrue('Record color: '.$record->color.' Current color: '.$currentColor);

    }
});

it('should work for fuzzy terms', function () {
    $products = Product::factory(100)
        ->state(new Sequence(
            ['color' => 'silver', 'manufacturer.country' => 'United States of America'],
            ['color' => 'blue'],
            ['color' => 'green'],
            ['color' => 'black'],
        ))->make();
    Product::insert($products->toArray());

    $silver = Product::term('silver')->search();
    $fuzzySilver = Product::fuzzyTerm('silvr')->search();
    $silverUsa = Product::term('silver')->orTerm('america')->andTerm('united')->search();
    $fuzzySilverUsa = Product::fuzzyTerm('silvr')->orFuzzyTerm('Amrica')->andFuzzyTerm('unitd')->search();
    expect(count($silver) == count($fuzzySilver))
        ->toBeTrue('Silver: '.count($silver).' Fuzzy Silver: '.count($fuzzySilver))
        ->and(count($silverUsa) <= count($fuzzySilverUsa))
        ->toBeTrue('Silver USA: '.count($silverUsa).' Fuzzy Silver USA: '.count($fuzzySilverUsa));

});

it('should highlight searches', function () {
    $products = Product::factory(100)
        ->state(new Sequence(
            ['color' => 'silver'],
            ['color' => 'blue'],
            ['color' => 'green'],
            ['color' => 'black'],
        ))->make();
    Product::insert($products->toArray());

    $silvers = Product::term('silver')->highlight()->search();
    $errorSearchHighlights = false;
    $errorSearchHighlightsAsArray = false;
    $errorWithHighlights = false;
    foreach ($silvers as $silver) {
        if (empty($silver->searchHighlights->color)) {
            $errorSearchHighlights = true;
        }
        if (empty($silver->searchHighlightsAsArray['color'])) {
            $errorSearchHighlightsAsArray = true;
        }
        if (! str_contains($silver->withHighlights->color, '<em>silver</em>')) {
            $errorWithHighlights = true;
        }
    }

    expect($errorSearchHighlights)->toBeFalse()
        ->and($errorSearchHighlightsAsArray)->toBeFalse()
        ->and($errorWithHighlights)->toBeFalse();

});
