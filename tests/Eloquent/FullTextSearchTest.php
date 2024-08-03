<?php

declare(strict_types=1);

use Workbench\App\Models\Product;

beforeEach(function () {
    Product::factory()->create([
        'name' => 'Espresso Machine',
        'description' => 'Automatic espresso machine with fine control over brew temperature.',
        'manufacturer' => [
            'name' => 'Coffee Inc.',
            'location' => ['lat' => 40.7128, 'lon' => -74.0060],
        ],
    ]);
});

test('term search across all fields', function () {
    $results = Product::term('Espresso')->search();
    expect($results)->toHaveCount(1);
});

test('phrase search across all fields', function () {
    $results = Product::phrase('Espresso Machine')->search();
    expect($results)->toHaveCount(1);
});

test('combining multiple terms with logical operators', function () {
    $results = Product::term('Espresso')->orTerm('Machine')->andTerm('Automatic')->search();
    expect($results)->toHaveCount(1);
});

test('boosting terms in search', function () {
    $results = Product::term('Espresso', 2)->orTerm('Brew')->search();
    expect($results)->toHaveCount(1);
});

test('limiting search to specific fields', function () {
    $results = Product::term('Espresso')->fields(['name', 'description'])->search();
    expect($results)->toHaveCount(1);
});

test('minimum should match in search', function () {
    $results = Product::term('Espresso')->orTerm('Brew')->orTerm('Machine')->minShouldMatch(2)->search();
    expect($results)->toHaveCount(1);
});

test('minimum score for search results', function () {
    $results = Product::term('Espresso')->minScore(0.1)->search();
    expect($results)->toHaveCount(1);
});

test('fuzzy searches for similar terms', function () {
    $results = Product::fuzzyTerm('espreso')->orFuzzyTerm('mchine')->search();
    expect($results)->toHaveCount(1);
});

test('regex search on product fields', function () {
    $results = Product::regEx('espresso*')->search();
    expect($results)->toHaveCount(1);
});

test('highlighting search results', function () {
    $results = Product::term('Espresso')->highlight(['description'], '<em>', '</em>')->search();
    $highlighted = $results->first()->searchHighlights->description ?? [];
    expect($highlighted)->toContain('<em>');
});
