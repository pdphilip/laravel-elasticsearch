<?php

use Workbench\App\Models\BlogPost;

test('retrieve blog posts with specific comments', function () {
    BlogPost::factory()->create([
        'comments' => [
            ['name' => 'John Doe', 'country' => 'Peru', 'likes' => 5],
            ['name' => 'Jane Smith', 'country' => 'USA', 'likes' => 3],
        ],
    ]);

    $posts = BlogPost::whereNestedObject('comments', function ($query) {
        $query->where('country', 'Peru')->where('likes', 5);
    })->get();

    expect($posts)->toHaveCount(1)
        ->and($posts->first()->comments[0]['country'])->toEqual('Peru')
        ->and($posts->first()->comments[0]['likes'])->toEqual(5);
});

test('exclude blog posts with comments from a specific country', function () {
    BlogPost::factory()->create([
        'comments' => [
            ['name' => 'John Doe', 'country' => 'Peru', 'likes' => 5],
        ],
    ]);

    $posts = BlogPost::whereNotNestedObject('comments', function ($query) {
        $query->where('country', 'Peru');
    })->get();

    expect($posts->isNotEmpty())->toBeTrue();
});

test('order blog posts by comments likes descending', function () {
    BlogPost::factory()->create([
        'status' => 1,
        'comments' => [
            ['name' => 'John Doe', 'country' => 'Peru', 'likes' => 5],
            ['name' => 'Jane Smith', 'country' => 'USA', 'likes' => 8],
        ],
    ]);

    // FIXME: @pdphilip I can't get this to sort for the life of me not sure what I am doing wrong.
    $posts = BlogPost::where('status', 1)->orderByNested('comments.likes', 'desc', 'sum')->get();
    expect($posts->first()->comments[0]['likes'])->toEqual(8);
})->todo();

test('filter blog posts by comments from Switzerland ordered by likes', function () {
    BlogPost::factory()->create([
        'status' => 5,
        'comments' => [
            ['name' => 'April Von', 'country' => 'Switzerland', 'likes' => 10],
            ['name' => 'Mabelle Schinner', 'country' => 'Switzerland', 'likes' => 7],
        ],
    ]);

    $post = BlogPost::where('status', 5)->queryNested('comments', function ($query) {
        $query->where('country', 'Switzerland')->orderBy('likes');
    })->first();

    expect($post->comments[0]['name'])->toEqual('Mabelle Schinner')
        ->and($post->comments[0]['likes'])->toEqual(7)
        ->and($post->comments[1]['likes'])->toEqual(10);
});

test('filter comments with likes greater than or equal to 5, limit 2', function () {
    BlogPost::factory()->create([
        'status' => 5,
        'comments' => [
            ['name' => 'Damaris Ondricka', 'country' => 'Peru', 'likes' => 5],
            ['name' => 'April Von', 'country' => 'Switzerland', 'likes' => 10],
            ['name' => 'Third Comment', 'country' => 'USA', 'likes' => 2],
        ],
    ]);

    $post = BlogPost::where('status', 5)->queryNested('comments', function ($query) {
        $query->where('likes', '>=', 5)->limit(2);
    })->first();

    expect($post->comments)->toHaveCount(2)
        ->and($post->comments[0]['likes'])->toBeGreaterThanOrEqual(5);
});
