<?php

declare(strict_types=1);

use PDPhilip\Elasticsearch\Tests\Models\Product;

beforeEach(function () {
    Product::executeSchema();
});

// ----------------------------------------------------------------------
// searchTerm - best_fields multi-match
// ----------------------------------------------------------------------

it('searches with best_fields multi-match', function () {
    Product::insert([
        ['name' => 'Red Widget', 'description' => 'A useful widget for everyday tasks', 'color' => 'red'],
        ['name' => 'Blue Gadget', 'description' => 'An innovative gadget with many features', 'color' => 'blue'],
        ['name' => 'Green Widget', 'description' => 'An eco-friendly widget', 'color' => 'green'],
    ]);

    $results = Product::searchTerm('widget', ['name', 'description'])->get();
    expect($results)->toHaveCount(2);

    $results = Product::searchTerm('gadget', ['name', 'description'])->get();
    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Blue Gadget');
});

it('combines searchTerm with orSearchTerm', function () {
    Product::insert([
        ['name' => 'Red Widget', 'description' => 'Handy tool'],
        ['name' => 'Blue Gadget', 'description' => 'Smart device'],
        ['name' => 'Yellow Tool', 'description' => 'Basic utility'],
    ]);

    $results = Product::searchTerm('widget', ['name'])
        ->orSearchTerm('gadget', ['name'])
        ->get();

    expect($results)->toHaveCount(2);
});

it('excludes results with searchNotTerm', function () {
    Product::insert([
        ['name' => 'Red Widget', 'description' => 'A useful widget'],
        ['name' => 'Blue Widget', 'description' => 'A smart widget'],
        ['name' => 'Green Gadget', 'description' => 'A simple gadget'],
    ]);

    $results = Product::searchTerm('widget', ['name', 'description'])
        ->searchNotTerm('useful', ['description'])
        ->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Blue Widget');
});

// ----------------------------------------------------------------------
// searchTermMost - most_fields multi-match
// ----------------------------------------------------------------------

it('searches with most_fields multi-match', function () {
    Product::insert([
        ['name' => 'Widget Pro', 'description' => 'Professional widget', 'details' => 'Advanced widget features'],
        ['name' => 'Basic Tool', 'description' => 'Simple utility', 'details' => 'No special features'],
    ]);

    $results = Product::searchTermMost('widget', ['name', 'description', 'details'])->get();
    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Widget Pro');
});

it('combines searchTermMost with orSearchTermMost', function () {
    Product::insert([
        ['name' => 'Widget', 'description' => 'Good widget'],
        ['name' => 'Gadget', 'description' => 'Nice gadget'],
        ['name' => 'Tool', 'description' => 'Basic tool'],
    ]);

    $results = Product::searchTermMost('widget', ['name', 'description'])
        ->orSearchTermMost('gadget', ['name', 'description'])
        ->get();

    expect($results)->toHaveCount(2);
});

// ----------------------------------------------------------------------
// searchTermCross - cross_fields multi-match
// ----------------------------------------------------------------------

it('searches across fields with cross_fields', function () {
    Product::insert([
        ['first_name' => 'John', 'last_name' => 'Smith'],
        ['first_name' => 'Jane', 'last_name' => 'Doe'],
        ['first_name' => 'Robert', 'last_name' => 'John'],  // John as last name
    ]);

    // cross_fields treats all fields as one big field - matches "John" in either field
    $results = Product::searchTermCross('John', ['first_name', 'last_name'])->get();
    expect($results)->toHaveCount(2)
        ->and($results->pluck('first_name'))->toContain('John')
        ->and($results->pluck('last_name'))->toContain('John');
});

it('excludes with searchNotTermCross', function () {
    Product::insert([
        ['first_name' => 'John', 'last_name' => 'Smith'],
        ['first_name' => 'Jane', 'last_name' => 'Doe'],
    ]);

    $results = Product::searchNotTermCross('John Smith', ['first_name', 'last_name'])->get();
    expect($results)->toHaveCount(1)
        ->and($results->first()->first_name)->toBe('Jane');
});

// ----------------------------------------------------------------------
// searchPhrase - phrase multi-match
// ----------------------------------------------------------------------

it('searches for exact phrase', function () {
    Product::insert([
        ['name' => 'Professional Widget', 'description' => 'A high quality widget for professionals'],
        ['name' => 'Basic Widget', 'description' => 'Widget for high volume use'],
        ['name' => 'Premium Tool', 'description' => 'Quality is high in this tool'],
    ]);

    // Phrase search finds exact phrase "high quality" in sequence
    $results = Product::searchPhrase('high quality', ['description'])->get();
    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Professional Widget');
});

it('combines searchPhrase with orSearchPhrase', function () {
    Product::insert([
        ['description' => 'A high quality widget'],
        ['description' => 'A premium product'],
        ['description' => 'A basic item'],
    ]);

    $results = Product::searchPhrase('high quality', ['description'])
        ->orSearchPhrase('premium product', ['description'])
        ->get();

    expect($results)->toHaveCount(2);
});

it('excludes phrase with searchNotPhrase', function () {
    Product::insert([
        ['name' => 'Widget A', 'description' => 'A high quality widget'],
        ['name' => 'Widget B', 'description' => 'A budget widget'],
    ]);

    $results = Product::searchNotPhrase('high quality', ['description'])->get();
    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Widget B');
});

// ----------------------------------------------------------------------
// searchPhrasePrefix - phrase_prefix multi-match
// ----------------------------------------------------------------------

it('searches for phrase prefix autocomplete', function () {
    Product::insert([
        ['name' => 'Widget Professional'],
        ['name' => 'Widget Pro Max'],
        ['name' => 'Gadget Pro'],
    ]);

    $results = Product::searchPhrasePrefix('Widget Pro', ['name'])->get();
    expect($results)->toHaveCount(2);
});

it('excludes with searchNotPhrasePrefix', function () {
    Product::insert([
        ['name' => 'Widget Professional'],
        ['name' => 'Widget Basic'],
        ['name' => 'Gadget Pro'],
    ]);

    $results = Product::searchNotPhrasePrefix('Widget Pro', ['name'])->get();
    expect($results)->toHaveCount(2);
});

// ----------------------------------------------------------------------
// searchBoolPrefix - bool_prefix multi-match
// ----------------------------------------------------------------------

it('searches with bool prefix for search-as-you-type', function () {
    Product::insert([
        ['name' => 'Quick Brown Fox'],
        ['name' => 'Rapid Black Dog'],
        ['name' => 'Slow Green Snake'],
    ]);

    // bool_prefix matches "quick" and prefix "bro*"
    $results = Product::searchBoolPrefix('quick bro', ['name'])->get();
    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Quick Brown Fox');
});

it('combines searchBoolPrefix with orSearchBoolPrefix', function () {
    Product::insert([
        ['name' => 'Quick Brown Fox'],
        ['name' => 'Slow Black Dog'],
        ['name' => 'Fast Blue Bird'],
    ]);

    $results = Product::searchBoolPrefix('quick bro', ['name'])
        ->orSearchBoolPrefix('slow bla', ['name'])
        ->get();

    expect($results)->toHaveCount(2);
});

// ----------------------------------------------------------------------
// searchFuzzy - fuzzy search with typo tolerance
// ----------------------------------------------------------------------

it('matches despite typos with fuzzy search', function () {
    Product::insert([
        ['name' => 'Widget'],
        ['name' => 'Gadget'],
        ['name' => 'Budget'],
    ]);

    // "Widgit" should match "Widget" with fuzzy
    $results = Product::searchFuzzy('Widgit', ['name'])->get();
    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Widget');
});

it('combines searchFuzzy with orSearchFuzzy', function () {
    Product::insert([
        ['name' => 'Widget'],
        ['name' => 'Gadget'],
        ['name' => 'Tool'],
    ]);

    // "Widgit" matches "Widget", "Gadgit" matches "Gadget"
    $results = Product::searchFuzzy('Widgit', ['name'])
        ->orSearchFuzzy('Gadgit', ['name'])
        ->get();

    expect($results)->toHaveCount(2);
});

it('excludes with searchNotFuzzy', function () {
    Product::insert([
        ['name' => 'Widget'],
        ['name' => 'Gadget'],
    ]);

    $results = Product::searchNotFuzzy('Widgit', ['name'])->get();
    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Gadget');
});

// ----------------------------------------------------------------------
// searchFuzzyPrefix - fuzzy with bool_prefix
// ----------------------------------------------------------------------

it('matches with fuzzy prefix for autocomplete with typo tolerance', function () {
    Product::insert([
        ['name' => 'Professional Widget'],
        ['name' => 'Basic Gadget'],
    ]);

    // "Profesional Wid" with typo should still match
    $results = Product::searchFuzzyPrefix('Profesional Wid', ['name'])->get();
    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Professional Widget');
});

// ----------------------------------------------------------------------
// searchQueryString - query string syntax
// ----------------------------------------------------------------------

it('searches with query string syntax', function () {
    Product::insert([
        ['name' => 'Red Widget', 'color' => 'red', 'price' => 100],
        ['name' => 'Blue Gadget', 'color' => 'blue', 'price' => 200],
        ['name' => 'Green Widget', 'color' => 'green', 'price' => 150],
    ]);

    $results = Product::searchQueryString('widget AND red', ['name', 'color'])->get();
    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Red Widget');
});

it('uses wildcard in query string', function () {
    Product::insert([
        ['name' => 'Widget Pro'],
        ['name' => 'Widget Basic'],
        ['name' => 'Gadget Pro'],
    ]);

    $results = Product::searchQueryString('Wid*', ['name'])->get();
    expect($results)->toHaveCount(2);
});

it('combines searchQueryString with orSearchQueryString', function () {
    Product::insert([
        ['name' => 'Widget', 'type' => 'hardware'],
        ['name' => 'Software', 'type' => 'software'],
        ['name' => 'Service', 'type' => 'service'],
    ]);

    $results = Product::searchQueryString('widget', ['name'])
        ->orSearchQueryString('software', ['type'])
        ->get();

    expect($results)->toHaveCount(2);
});

it('excludes with searchNotQueryString', function () {
    Product::insert([
        ['name' => 'Premium Widget'],
        ['name' => 'Basic Widget'],
        ['name' => 'Standard Gadget'],
    ]);

    $results = Product::searchQueryString('widget', ['name'])
        ->searchNotQueryString('premium', ['name'])
        ->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Basic Widget');
});

// ----------------------------------------------------------------------
// Core search() method with different types
// ----------------------------------------------------------------------

it('uses core search method with custom type', function () {
    Product::insert([
        ['name' => 'Test Product', 'description' => 'A test description'],
    ]);

    $results = Product::search('test', 'best_fields', ['name', 'description'])->get();
    expect($results)->toHaveCount(1);
});

it('applies search options via closure', function () {
    Product::insert([
        ['name' => 'Widget', 'description' => 'Good product'],
        ['name' => 'Gadget', 'description' => 'Another product'],
    ]);

    $results = Product::searchTerm('widget', ['name'], function ($options) {
        return $options->boost(2.0);
    })->get();

    expect($results)->toHaveCount(1);
});
