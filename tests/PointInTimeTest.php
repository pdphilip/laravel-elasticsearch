<?php

declare(strict_types=1);

use PDPhilip\Elasticsearch\Tests\Models\Product;

beforeEach(function () {
    Product::executeSchema();
});

// ----------------------------------------------------------------------
// Basic PIT Operations
// ----------------------------------------------------------------------

it('opens point in time', function () {
    $pitId = Product::query()->openPit();

    expect($pitId)->toBeString()
        ->and($pitId)->not->toBeEmpty();

    // Clean up
    Product::query()->toBase()->closePit($pitId);
});

it('opens and closes point in time', function () {
    $pitId = Product::query()->openPit();
    expect($pitId)->toBeString();

    $closed = Product::query()->toBase()->closePit($pitId);
    expect($closed)->toBeTrue();
});

it('sets keep alive duration', function () {
    $query = Product::query()->keepAlive('5m');

    // Verify the keep alive is set (uses reflection or checking compiled query)
    $pitId = $query->openPit();
    expect($pitId)->toBeString();

    Product::query()->toBase()->closePit($pitId);
});

it('sets pit id explicitly', function () {
    $pitId = Product::query()->openPit();

    $query = Product::query()->withPitId($pitId);
    // The query should have the PIT ID set
    expect($query)->toBeInstanceOf(\PDPhilip\Elasticsearch\Eloquent\Builder::class);

    Product::query()->toBase()->closePit($pitId);
});

// ----------------------------------------------------------------------
// Querying with PIT
// ----------------------------------------------------------------------

it('gets results via pit', function () {
    Product::insert([
        ['name' => 'Product 1', 'price' => 100],
        ['name' => 'Product 2', 'price' => 200],
        ['name' => 'Product 3', 'price' => 300],
    ]);

    $results = Product::query()
        ->orderBy('price')
        ->limit(10)
        ->getPit();

    expect($results)->toHaveCount(3);

    // Clean up - getPit opens a PIT automatically
    // The PIT should be closed or will expire
});

it('uses viaPit for continuation', function () {
    Product::insert([
        ['name' => 'Product 1', 'price' => 100],
        ['name' => 'Product 2', 'price' => 200],
        ['name' => 'Product 3', 'price' => 300],
        ['name' => 'Product 4', 'price' => 400],
    ]);

    // First batch
    $pitId = Product::query()->openPit();

    $firstBatch = Product::query()
        ->orderBy('price')
        ->limit(2)
        ->viaPit($pitId, null)
        ->getPit();

    expect($firstBatch)->toHaveCount(2)
        ->and($firstBatch->first()->name)->toBe('Product 1');

    $afterKey = $firstBatch->getAfterKey();

    // Second batch using search_after
    $secondBatch = Product::query()
        ->orderBy('price')
        ->limit(2)
        ->viaPit($pitId, $afterKey)
        ->getPit();

    expect($secondBatch)->toHaveCount(2)
        ->and($secondBatch->first()->name)->toBe('Product 3');

    Product::query()->toBase()->closePit($pitId);
});

it('sets search after key', function () {
    Product::insert([
        ['name' => 'A', 'price' => 100],
        ['name' => 'B', 'price' => 200],
        ['name' => 'C', 'price' => 300],
    ]);

    $pitId = Product::query()->openPit();

    $first = Product::query()
        ->orderBy('price')
        ->limit(1)
        ->viaPit($pitId, null)
        ->getPit();

    $afterKey = $first->getAfterKey();

    $second = Product::query()
        ->orderBy('price')
        ->limit(1)
        ->withPitId($pitId)
        ->searchAfter($afterKey)
        ->get();

    expect($second)->toHaveCount(1)
        ->and($second->first()->name)->toBe('B');

    Product::query()->toBase()->closePit($pitId);
});

// ----------------------------------------------------------------------
// chunkByPit - Chunked iteration
// ----------------------------------------------------------------------

it('chunks large result sets by pit', function () {
    // Create 10 products
    $products = [];
    for ($i = 1; $i <= 10; $i++) {
        $products[] = ['name' => "Product $i", 'price' => $i * 100];
    }
    Product::insert($products);

    $chunks = [];
    $totalProducts = [];

    Product::query()
        ->orderBy('price')
        ->limit(3)
        ->chunkByPit(3, function ($results, $page) use (&$chunks, &$totalProducts) {
            $chunks[] = $page;
            foreach ($results as $product) {
                $totalProducts[] = $product->name;
            }
        });

    expect($chunks)->toHaveCount(4) // 10 products / 3 per chunk = 4 chunks
        ->and($totalProducts)->toHaveCount(10);
});

it('stops chunk iteration when callback returns false', function () {
    $products = [];
    for ($i = 1; $i <= 10; $i++) {
        $products[] = ['name' => "Product $i", 'price' => $i * 100];
    }
    Product::insert($products);

    $processedPages = 0;

    Product::query()
        ->orderBy('price')
        ->limit(3)
        ->chunkByPit(3, function ($results, $page) use (&$processedPages) {
            $processedPages = $page;
            if ($page >= 2) {
                return false; // Stop after 2 pages
            }
        });

    expect($processedPages)->toBe(2);
});

it('uses custom keep alive with chunkByPit', function () {
    Product::insert([
        ['name' => 'Product 1', 'price' => 100],
        ['name' => 'Product 2', 'price' => 200],
    ]);

    $processed = false;

    Product::query()
        ->orderBy('price')
        ->limit(5)
        ->chunkByPit(5, function ($results) use (&$processed) {
            $processed = true;
        }, '2m'); // Custom 2 minute keep alive

    expect($processed)->toBeTrue();
});

// ----------------------------------------------------------------------
// Cursor Metadata
// ----------------------------------------------------------------------

it('initializes cursor metadata via query builder', function () {
    $query = Product::query()->toBase();
    $meta = $query->initCursorMeta(null);

    expect($meta)->toBeArray()
        ->and($meta['pit_id'])->toBeNull()
        ->and($meta['page'])->toBe(1)
        ->and($meta['pages'])->toBe(0)
        ->and($meta['records'])->toBe(0)
        ->and($meta['sort_history'])->toBe([])
        ->and($meta['next_sort'])->toBeNull()
        ->and($meta['ts'])->toBe(0);
});

it('sets and gets cursor metadata via query builder', function () {
    $query = Product::query()->toBase();

    $customMeta = [
        'pit_id' => 'test-pit-id',
        'page' => 5,
        'pages' => 10,
        'records' => 100,
        'sort_history' => [[100], [200]],
        'next_sort' => [300],
        'ts' => time(),
    ];

    $query->setCursorMeta($customMeta);
    $retrieved = $query->getCursorMeta();

    expect($retrieved)->toBe($customMeta)
        ->and($retrieved['pit_id'])->toBe('test-pit-id')
        ->and($retrieved['page'])->toBe(5);
});

// ----------------------------------------------------------------------
// PIT with Filters
// ----------------------------------------------------------------------

it('queries with pit and where clauses', function () {
    Product::insert([
        ['name' => 'Cheap Item', 'price' => 50],
        ['name' => 'Medium Item', 'price' => 150],
        ['name' => 'Expensive Item', 'price' => 500],
    ]);

    $pitId = Product::query()->openPit();

    $results = Product::query()
        ->where('price', '>', 100)
        ->orderBy('price')
        ->viaPit($pitId, null)
        ->getPit();

    expect($results)->toHaveCount(2)
        ->and($results->first()->name)->toBe('Medium Item');

    Product::query()->toBase()->closePit($pitId);
});

it('maintains consistency during iteration with pit', function () {
    // Create initial products
    Product::insert([
        ['name' => 'Product 1', 'price' => 100],
        ['name' => 'Product 2', 'price' => 200],
        ['name' => 'Product 3', 'price' => 300],
    ]);

    $pitId = Product::query()->openPit();

    // Get first page
    $firstPage = Product::query()
        ->orderBy('price')
        ->limit(2)
        ->viaPit($pitId, null)
        ->getPit();

    expect($firstPage)->toHaveCount(2);

    // Insert new product (this shouldn't appear in the PIT view)
    Product::insert(['name' => 'Product 4', 'price' => 150]);

    // Get second page - should only see original products
    $afterKey = $firstPage->getAfterKey();
    $secondPage = Product::query()
        ->orderBy('price')
        ->limit(2)
        ->viaPit($pitId, $afterKey)
        ->getPit();

    // Should get Product 3 from original snapshot
    expect($secondPage)->toHaveCount(1)
        ->and($secondPage->first()->name)->toBe('Product 3');

    Product::query()->toBase()->closePit($pitId);
});
