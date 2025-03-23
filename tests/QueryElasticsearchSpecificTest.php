<?php

declare(strict_types=1);

use PDPhilip\Elasticsearch\Query\Builder;
use PDPhilip\Elasticsearch\Tests\Models\Post;
use PDPhilip\Elasticsearch\Tests\Models\User;

beforeEach(function () {
    User::executeSchema();
    Post::executeSchema();

    User::insert([
        ['name' => 'John Doe', 'age' => 35, 'title' => 'admin', 'description' => 'John manages the admin team effectively.'],
        ['name' => 'Jane Doe', 'age' => 33, 'title' => 'admin', 'description' => 'Jane oversees all administrative operations.'],
        ['name' => 'Harry Hoe', 'age' => 13, 'title' => 'user', 'description' => 'Harry is a young user exploring the platform.'],
        ['name' => 'Robert Roe', 'age' => 37, 'title' => 'user', 'description' => 'Robert actively participates in user discussions.'],
        ['name' => 'Mark Moe', 'age' => 23, 'title' => 'user', 'description' => 'Mark contributes valuable feedback to the user community.'],
        ['name' => 'Brett Boe', 'age' => 35, 'title' => 'user', 'description' => 'Brett frequently posts detailed reviews.'],
        ['name' => 'Tommy Toe', 'age' => 33, 'title' => 'user', 'description' => 'Tommy enjoys creating content for user forums.'],
        ['name' => 'John John Yoe', 'age' => 35, 'title' => 'admin', 'description' => 'Yoe coordinates tasks across multiple admin teams.'],
        ['name' => 'Error', 'age' => null, 'title' => null, 'description' => null],
    ]);

    Post::insert([
        [
            'title' => 'Getting Started with Laravel',
            'status' => 2,
            'comments' => [
                ['name' => 'John Doe', 'comment' => 'This was the perfect introduction to Laravel, thank you!', 'country' => 'USA', 'likes' => 15],
                ['name' => 'Jane Smith', 'comment' => 'I feel much more confident starting my first Laravel project.', 'country' => 'UK', 'likes' => 8],
                ['name' => 'Akira Tanaka', 'comment' => 'I loved the step-by-step guide!', 'country' => 'Japan', 'likes' => 12],
            ],
        ],
        [
            'title' => 'Exploring Laravel Testing',
            'status' => 1,
            'comments' => [
                ['name' => 'Michael Brown', 'comment' => 'Laravel testing is super powerful!', 'country' => 'USA', 'likes' => 10],
                ['name' => 'Emily Davis', 'comment' => 'I struggled at first but now it makes sense.', 'country' => 'Australia', 'likes' => 35],
            ],
        ],
        [
            'title' => 'Understanding Eloquent Relationships',
            'status' => 1,
            'comments' => [
                ['name' => 'Carlos Ruiz', 'comment' => 'Great examples in this post!', 'country' => 'Spain', 'likes' => 18],
                ['name' => 'Sofia Lopez', 'comment' => 'I need help with many-to-many relationships.', 'country' => 'Mexico', 'likes' => 9],
                ['name' => 'Liam O’Connor', 'comment' => 'This cleared up a lot of confusion for me.', 'country' => 'Ireland', 'likes' => 11],
            ],
        ],
        [
            'title' => 'Building APIs with Laravel',
            'status' => 3,
            'comments' => [
                ['name' => 'Sarah Lee', 'comment' => 'APIs are the future of web development!', 'country' => 'South Korea', 'likes' => 20],
                ['name' => 'James Wilson', 'comment' => 'Any tips on debugging API responses?', 'country' => 'New Zealand', 'likes' => 6],
                ['name' => 'Anna Muller', 'comment' => 'Laravel makes building APIs so easy.', 'country' => 'Germany', 'likes' => 14],
            ],
        ],
    ]);

});

it('ES Specific Queries', function () {

    $users = User::whereTermExists('title')->get();
    expect($users)->toHaveCount(8);

    $users = User::whereFuzzy('title', 'admik')->get();
    expect($users)->toHaveCount(3);

    // Was searchMatch()
    $users = User::whereMatch('description', 'exploring')->get();
    expect($users)->toHaveCount(1);

    $users = User::wherePhrase('description', 'exploring the')->get();
    expect($users)->toHaveCount(1);

    $users = User::wherePhrasePrefix('description', 'Robert actively')->get();
    expect($users)->toHaveCount(1);

});

it('can use function score', function () {
    $users = User::functionScore('random_score', [
        'seed' => 2,
        'field' => '_seq_no',
    ], function (Builder $query) {
        $query->whereFuzzy('title.keyword', 'admik');
    })->get();
    expect($users)->toHaveCount(3);
});

it('Nested Queries', function () {

    // single where in nested
    $users = Post::whereNestedObject('comments', function (Builder $query) {
        $query->whereTerm('comments.country', 'USA');
    })->get();
    expect($users)->toHaveCount(2);

    // single where NOT nested
    $posts = Post::whereNotNestedObject('comments', function (Builder $query) {
        $query->whereTerm('comments.country', 'USA');
    })->get();
    expect($posts)->toHaveCount(2);

    // dual where in nested
    $users = Post::whereNestedObject('comments', function (Builder $query) {
        $query->whereTerm('comments.country', 'USA')->where('comments.likes', '>=', 15);
    })->get();
    expect($users)->toHaveCount(1);

    // sorting
    $users = Post::orderByNested('comments.likes', 'desc')->get();
    expect($users[0]['title'])->toBe('Exploring Laravel Testing');

    // single where in nested
    $users = Post::whereNestedObject('comments', function (Builder $query) {
        $query->whereTerm('comments.country', 'USA');
    }, true)->get();

    expect($users)->toHaveCount(2)
        ->and($users[0]['comments'])->toHaveCount(1)
        ->and($users[1]['comments'])->toHaveCount(1);

});

it('can search with field boosting', function () {
    $users = User::search('John', 'best_fields', ['name' => 5, 'description' => 1])->get();
    expect($users)->toHaveCount(2)
        ->and($users[0]['name'])->toBe('John John Yoe')
        ->and($users[1]['name'])->toBe('John Doe');
});

it('can search across all fields', function () {
    $users = User::search('admin', 'best_fields', ['*'])->get();
    expect($users)->toHaveCount(3);
});

it('can search with fuzziness', function () {
    $users = User::search('Jon', 'best_fields', ['name' => 5, 'description' => 1], [
        'fuzziness' => 'AUTO',
    ])->get();
    expect($users)->toHaveCount(2)
        ->and($users[0]['name'])->toBe('John John Yoe')
        ->and($users[1]['name'])->toBe('John Doe');
});

it('can search with specific fields only', function () {
    $users = User::search('young', 'best_fields', ['description' => 1])->get();
    expect($users)->toHaveCount(1)
        ->and($users[0]['name'])->toBe('Harry Hoe');
});

it('returns empty result for unmatched query', function () {
    $users = User::search('nonexistent', 'best_fields', ['name' => 1, 'description' => 1])->get();
    expect($users)->toBeEmpty();
});

it('can use constant score query', function () {
    $users = User::search('John', 'best_fields', ['name' => 5, 'description' => 1], [
        'constant_score' => true,
    ])->get();
    expect($users)->toHaveCount(2)
        ->and($users[0]['name'])->toBe('John Doe')
        ->and($users[1]['name'])->toBe('John John Yoe');
});

it('can search and highlight', function () {

    $users = User::search('John')->highlight(['name'])->get();
    expect($users)->toHaveCount(2)
        ->and($users[0]->getHighlights())->toHaveKey('name')
        ->and($users[0]->getHighlight('name'))->toBe('<em>John</em> Doe');

    $users = User::search('John')->highlight(['name'], '<strong>', '</strong>')->get();
    expect($users)->toHaveCount(2)
        ->and($users[0]->getHighlights())->toHaveKey('name')
        ->and($users[0]->getHighlight('name'))->toBe('<strong>John</strong> Doe');

    $users = User::search('John')->highlight(['name' => ['pre_tags' => '<strong>', 'post_tags' => '</strong>'], 'description'])->get();
    expect($users)->toHaveCount(2)
        ->and($users[0]->getHighlights())->toHaveKey('name')
        ->and($users[0]->getHighlight('name'))->toBe('<strong>John</strong> Doe')
        ->and($users[0]->getHighlight('description'))->toBe('<em>John</em> manages the admin team effectively.');
});
