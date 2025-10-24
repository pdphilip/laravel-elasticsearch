<?php

declare(strict_types=1);

use PDPhilip\Elasticsearch\Query\Options\QueryStringOptions;
use PDPhilip\Elasticsearch\Tests\Models\Product;
use PDPhilip\Elasticsearch\Tests\Models\User;

beforeEach(function () {
    User::executeSchema();
    Product::executeSchema();

    User::insert([
        ['name' => 'John Doe', 'age' => 35, 'title' => 'admin'],
        ['name' => 'Jane Doe', 'age' => 33, 'title' => 'admin'],
        ['name' => 'Harry Hoe', 'age' => 13, 'title' => 'user'],
        ['name' => 'Robert Roe', 'age' => 37, 'title' => 'user'],
        ['name' => 'Mark Moe', 'age' => 23, 'title' => 'user'],
        ['name' => 'Brett Boe', 'age' => 35, 'title' => 'user'],
        ['name' => 'Tommy Toe', 'age' => 33, 'title' => 'user'],
        ['name' => 'Yvonne Yoe', 'age' => 35, 'title' => 'admin'],
        ['name' => 'Error', 'age' => null, 'title' => null],
    ]);

    Product::insert([
        ['name' => 'Frosty chocolate deluxe', 'details' => ['type' => 'sweet', 'product' => 'chocolate', 'gluten_free' => true], 'price' => 20, 'price_history' => [14, 21, 18]],
        ['name' => 'Autumn pumpkin special', 'details' => ['type' => 'vegetable', 'product' => 'pumpkin', 'gluten_free' => true], 'price' => 30, 'price_history' => [25, 28, 32]],
        ['name' => 'Crisp apple bites', 'details' => ['type' => 'fruit', 'product' => 'apple', 'gluten_free' => true], 'price' => 10, 'price_history' => [9, 11, 10]],
        ['name' => 'Fresh orange juice', 'details' => ['type' => 'drink', 'product' => 'orange juice', 'gluten_free' => true], 'price' => 25, 'price_history' => [24, 26, 27]],
        ['name' => 'Morning roast', 'details' => ['type' => 'drink', 'product' => 'coffee', 'gluten_free' => true], 'price' => 15, 'price_history' => [13, 16, 15]],
        ['name' => 'Vanilla coffee flavoured pie', 'details' => ['type' => 'meal', 'product' => 'pizza', 'gluten_free' => false], 'price' => 19, 'price_history' => [17, 18, 19]],
        ['name' => 'Green leaf tea', 'details' => ['type' => 'drink', 'product' => 'tea', 'gluten_free' => true], 'price' => 12, 'price_history' => [11, 13, 12]],
        ['name' => 'Crunchy cookies', 'details' => ['type' => 'sweet', 'product' => 'cookies', 'gluten_free' => false], 'price' => 18, 'price_history' => [17, 19, 18]],
        ['name' => 'Vanilla dream ice cream', 'details' => ['type' => 'sweet', 'product' => 'ice cream', 'gluten_free' => false], 'price' => 19, 'price_history' => [17, 18, 19]],
        ['name' => 'Cheesy pizza', 'details' => ['type' => 'meal', 'product' => 'pizza', 'gluten_free' => false], 'price' => 45, 'price_history' => [40, 47, 44]],
        ['name' => 'Classic bagel', 'details' => ['type' => 'bakery', 'product' => 'bagel', 'gluten_free' => false], 'price' => 8, 'price_history' => [7, 8, 9]],
        ['name' => 'Jurassic Garden salad', 'details' => ['type' => 'meal', 'product' => 'salad', 'gluten_free' => true], 'price' => 14, 'price_history' => [13, 14, 15]],
        ['name' => 'Club sandwich deluxe', 'details' => ['type' => 'meal', 'product' => 'sandwich', 'gluten_free' => false], 'price' => 30, 'price_history' => [28, 31, 29]],
        ['name' => 'Mineral water', 'details' => ['type' => 'drink', 'product' => 'water', 'gluten_free' => true], 'price' => 5, 'price_history' => [4, 5, 5]],
        ['name' => 'Sweet Soda Classic', 'details' => ['type' => 'drink', 'product' => 'soda', 'gluten_free' => true], 'price' => 8, 'price_history' => [7, 8, 9]],
        ['name' => 'error', 'price' => null],
    ]);

});

it('tests basic query string', function () {
    $users = User::searchQueryString('age:35')->get();
    expect($users)->toHaveCount(3);

    $users = User::searchQueryString('age:>=35')->get();
    expect($users)->toHaveCount(4);

    $users = User::searchQueryString('age:<=18')->get();
    expect($users)->toHaveCount(1);

    $users = User::searchQueryString('age:(NOT 35)')->get();
    expect($users)->toHaveCount(6);

});

it('tests basic query string combined with normal query operators', function () {
    $users = User::searchQueryString('age:35')->where('title', 'admin')->get();
    expect($users)->toHaveCount(2);

    $users = User::searchQueryString('age:>30')->count();
    expect($users)->toBe(6);

    $users = User::searchQueryString('age:(NOT 35)')->skip(4)->limit(3)->get();
    expect($users)->toHaveCount(2);

});

it('tests AND query string', function () {

    $users = User::searchQueryString('age:35')->searchQueryString('title:admin')->get();
    expect($users)->toHaveCount(2);

    $users = User::searchQueryString('age:>=35')->searchQueryString('title:user')->get();
    expect($users)->toHaveCount(2);

    // Equivalent direct:

    $users = User::searchQueryString('(age:35) AND (title:admin)')->get();
    expect($users)->toHaveCount(2);

    $users = User::searchQueryString('(age:>=35) AND (title:user)')->get();
    expect($users)->toHaveCount(2);
});
//
it('tests OR query string', function () {
    $users = User::searchQueryString('name:doe')->orSearchQueryString('name:toe')->get();
    expect($users)->toHaveCount(3);

    // Equivalent direct:

    $users = User::searchQueryString('name:(doe OR toe)')->get();
    expect($users)->toHaveCount(3);

});
//
it('tests NOT query string', function () {
    $users = User::searchNotQueryString('name:doe')->get();
    expect($users)->toHaveCount(7);

    // Equivalent direct:
    $users = User::searchQueryString('name: (NOT doe)')->get();
    expect($users)->toHaveCount(7);
});
//
it('tests AND NOT query string', function () {
    $users = User::searchNotQueryString('name:doe')->searchNotQueryString('name:toe')->get();
    expect($users)->toHaveCount(6);

    // Equivalent:
    $users = User::searchNotQueryString('name:(doe OR toe)')->get();
    expect($users)->toHaveCount(6);

    // Equivalent direct:
    $users = User::searchQueryString('(name: (NOT doe)) AND (name: (NOT toe))')->get();
    expect($users)->toHaveCount(6);

});
//
it('tests OR NOT query string', function () {

    $users = User::searchNotQueryString('name:doe')->orSearchNotQueryString('age:35')->get();
    expect($users)->toHaveCount(8);

    // Equivalent:
    $users = User::searchNotQueryString('(name:doe) AND (age:35)')->get();
    expect($users)->toHaveCount(8);

    // Equivalent direct:
    $users = User::searchQueryString('(name:(NOT doe)) OR (age:(NOT 35))')->get();
    expect($users)->toHaveCount(8);

});

it('tests searching across all fields', function () {

    $products = Product::searchQueryString('sweet')->get();
    expect($products)->toHaveCount(4);

    $products = Product::searchQueryString('NOT sweet')->get();
    expect($products)->toHaveCount(12);

});

it('tests searching specific fields', function () {
    $products = Product::searchQueryString('sweet', 'details.type')->get();
    expect($products)->toHaveCount(3);

    $products = Product::searchQueryString('false', 'details.gluten_free')->get();
    expect($products)->toHaveCount(6);

    $products = Product::searchQueryString('details.gluten_free:false')->get();
    expect($products)->toHaveCount(6);
});

it('tests searching with options: Fuzziness', function () {

    $products = Product::searchQueryString('sweetee~')->get();
    expect($products)->toHaveCount(4);

    $products = Product::searchQueryString('sweetee~', function (QueryStringOptions $options) {
        $options->fuzziness(2); // Same as Default
    })->get();
    expect($products)->toHaveCount(4);

    $products = Product::searchQueryString('sweetee~', function (QueryStringOptions $options) {
        $options->fuzziness(1);
    })->get();
    expect($products)->toHaveCount(0);

    $products = Product::searchQueryString('sweetz~', function (QueryStringOptions $options) {
        $options->fuzziness(1);
    })->get();
    expect($products)->toHaveCount(4);
});

it('tests searching with options: Default Operator', function () {

    $products = Product::searchQueryString('sweet soda')->get();
    expect($products)->toHaveCount(4);

    $products = Product::searchQueryString('sweet soda', function (QueryStringOptions $options) {
        $options->defaultOperator('AND');
    })->get();
    expect($products)->toHaveCount(1);
});

it('tests searching with options: Minimum Should Match', function () {

    $products = Product::searchQueryString('drink OR water OR soda')->get();
    expect($products)->toHaveCount(5);

    $products = Product::searchQueryString('drink OR water OR soda', function (QueryStringOptions $options) {
        $options->minimumShouldMatch(2);
    })->get();
    expect($products)->toHaveCount(2);
});

it('tests searching with options: Phrase Slop', function () {

    // phrase_slop: allow a gap between terms → matches “Fresh orange juice”
    $products = Product::searchQueryString('"fresh juice"', function (QueryStringOptions $options) {
        $options->phraseSlop(1);
    })->get();
    expect($products)->toHaveCount(1);

    // phrase_slop 0 should not match (terms not adjacent)
    $products = Product::searchQueryString('"fresh juice"', function (QueryStringOptions $options) {
        $options->phraseSlop(0);
    })->get();
    expect($products)->toHaveCount(0);
});

it('tests searching with options: Leading Wildcard', function () {

    expect(function () {
        Product::searchQueryString('*assic', function (QueryStringOptions $options) {
            $options->allowLeadingWildcard(false);
        })->get();
    })->toThrow(PDPhilip\Elasticsearch\Exceptions\QueryException::class);

    $products = Product::searchQueryString('*assic', function (QueryStringOptions $options) {
        $options->allowLeadingWildcard(true); // Same as default
    })->get();
    expect($products)->toHaveCount(3);
});

it('tests searching with options: Lenient', function () {
    expect(function () {
        Product::searchQueryString('ABC', 'price')->get();
    })->toThrow(PDPhilip\Elasticsearch\Exceptions\QueryException::class);

    // lenient numeric parsing: invalid numeric query should not error, returns 0
    $products = Product::searchQueryString('ABC', 'price', function (QueryStringOptions $options) {
        $options->lenient(true);
    })->get();
    expect($products)->toHaveCount(0);
});

it('tests searching with options: Types', function () {
    // phrase vs phrase_prefix on a single field (name)
    // "club sand" is not a complete phrase in name -> phrase: 0, phrase_prefix: 1 ("Club sandwich deluxe")
    $products = Product::searchQueryString('club sand', 'name', function (QueryStringOptions $options) {
        $options->type('phrase');
    })->get();
    expect($products)->toHaveCount(0);

    $products = Product::searchQueryString('club sand', 'name', function (QueryStringOptions $options) {
        $options->type('phrase_prefix');
    })->get();
    expect($products)->toHaveCount(1);

    // best_fields vs cross_fields across multiple fields
    // We want tokens split across fields in a SINGLE doc:
    //   name contains "Vanilla", details.product contains "pizza" (the new seed row)
    // With AND:
    //  - best_fields requires both tokens in the same field -> 0
    //  - cross_fields allows tokens to be distributed across fields -> 1
    $products = Product::searchQueryString('vanilla pizza', ['name', 'details.product'], function (QueryStringOptions $options) {
        $options->defaultOperator('AND')->type('best_fields');
    })->get();
    expect($products)->toHaveCount(0);

    $products = Product::searchQueryString('vanilla pizza', ['name', 'details.product'], function (QueryStringOptions $options) {
        $options->defaultOperator('AND')->type('cross_fields');
    })->get();
    expect($products)->toHaveCount(1);

    // bool_prefix on a single field (name): both terms as prefixes should match "Cheesy pizza"
    $products = Product::searchQueryString('chees piz', 'name', function (QueryStringOptions $options) {
        $options->type('bool_prefix');
    })->get();
    expect($products)->toHaveCount(1);
});

it('tests searching with boosted fields', function () {
    // When name is boosted, expect "Vanilla delight" to rank highest (strong name match)
    $firstNameBoosted = Product::searchQueryString('coffee', ['name^3', 'details.product'], function (QueryStringOptions $options) {
        $options->type('cross_fields');
    })->first();

    expect($firstNameBoosted)->not->toBeNull()
        ->and($firstNameBoosted->name)->toBe('Vanilla coffee flavoured pie');

    // When product is boosted, expect "Cheesy pizza" to rank highest (strong product match)
    $firstProductBoosted = Product::searchQueryString('coffee', ['name', 'details.product^3'], function (QueryStringOptions $options) {
        $options->type('cross_fields');
    })->first();

    expect($firstProductBoosted)->not->toBeNull()
        ->and($firstProductBoosted->name)->toBe('Morning roast');
});

it('tests searching with regex', function () {
    // Matches: Classic bagel, Jurassic Garden salad, Sweet Soda Classic
    $products = Product::searchQueryString('name:/.*assic.*/')->get();
    expect($products)->toHaveCount(3);
});

it('tests searching with ranges (inclusive vs exclusive)', function () {
    // Inclusive upper bound includes price = 19 (two items)
    $inclusive = Product::searchQueryString('price:[5 TO 19]')->get();
    expect($inclusive)->toHaveCount(10);

    // Exclusive upper bound excludes price = 19
    $exclusive = Product::searchQueryString('price:[5 TO 19}')->get();
    expect($exclusive)->toHaveCount(8);
});

it('tests searching with boolean operators', function () {
    // Require pizza, forbid ice; "vanilla" is optional.
    // Matches: "Vanilla coffee flavoured pie" (has vanilla + pizza, no ice)
    //          "Cheesy pizza" (has pizza, no ice)
    $products = Product::searchQueryString('vanilla +pizza -ice', function (QueryStringOptions $options) {
        $options->type('cross_fields'); // blend name + details.product
    })->get();

    expect($products)->toHaveCount(2);
});
