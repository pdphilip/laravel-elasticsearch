<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use PDPhilip\Elasticsearch\Connection;
use Workbench\App\Models\Guarded;
use Workbench\App\Models\Product;
use Workbench\App\Models\Soft;

test('New Model', function () {
    $product = new Product;
    $this->assertInstanceOf(Connection::class, $product->getConnection());
    $this->assertFalse($product->exists);
    $this->assertEquals('products', $product->getTable());
    $this->assertEquals('_id', $product->getKeyName());
});

test('Insert', function () {
    $product = new Product;
    $product['name'] = 'John Doe';
    $product['description'] = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vivamus lacinia odio vitae vestibulum vestibulum.';
    $product['product_id'] = 'c1b5f730-7e5c-11e9-8f9e-2a86e4085a59';
    $product['in_stock'] = 25;

    $product->save();

    $this->assertTrue($product->exists);
    $this->assertEquals(1, Product::count());

    $this->assertTrue(isset($product->id));
    $this->assertIsString($product->id);
    $this->assertNotEquals('', (string) $product->id);
    $this->assertNotEquals(0, strlen((string) $product->id));
    $this->assertInstanceOf(Carbon::class, $product->created_at);

    $this->assertEquals('John Doe', $product->name);
    $this->assertEquals(25, $product->in_stock);
});

test('Update', function () {
    $product = new Product;
    $product['name'] = 'John Doe';
    $product['description'] = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vivamus lacinia odio vitae vestibulum vestibulum.';
    $product['product_id'] = 'c1b5f730-7e5c-11e9-8f9e-2a86e4085a59';
    $product['in_stock'] = 25;
    $product->save();

    $this->assertTrue($product->exists);
    $this->assertTrue(isset($product->id));

    $check = Product::find($product->id);
    $this->assertInstanceOf(Product::class, $check);
    $check->in_stock = 36;
    $check->save();

    $this->assertTrue($check->exists);
    $this->assertInstanceOf(Carbon::class, $check->created_at);
    $this->assertInstanceOf(Carbon::class, $check->updated_at);
    $this->assertEquals(1, Product::count());

    $this->assertEquals('John Doe', $check->name);
    $this->assertEquals(36, $check->in_stock);

    $product->update(['in_stock' => 20]);

    $check = Product::find($product->id);
    $this->assertEquals(20, $check->in_stock);

    $check->in_stock = 24;
    $check->color = 'blue'; // new field
    $check->save();

    $check = Product::find($product->id);
    $this->assertEquals(24, $check->in_stock);
    $this->assertEquals('blue', $check->color);
});

test('Delete', function () {
    $product = new Product;
    $product['name'] = 'John Doe';
    $product['description'] = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vivamus lacinia odio vitae vestibulum vestibulum.';
    $product['product_id'] = 'c1b5f730-7e5c-11e9-8f9e-2a86e4085a59';
    $product['in_stock'] = 25;
    $product->save();

    $this->assertTrue($product->exists);
    $this->assertEquals(1, Product::count());

    $product->delete();

    $this->assertEquals(0, Product::count());

});

test('All', function () {
    $product = new Product;
    $product['name'] = 'John Doe';
    $product['description'] = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vivamus lacinia odio vitae vestibulum vestibulum.';
    $product['in_stock'] = 24;
    $product->save();

    $product = new Product;
    $product['name'] = 'Jane Doe';
    $product['description'] = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vivamus lacinia odio vitae vestibulum vestibulum.';
    $product['in_stock'] = 35;
    $product->save();

    $all = Product::all();

    $this->assertCount(2, $all);
    $this->assertContains('John Doe', $all->pluck('name'));
    $this->assertContains('Jane Doe', $all->pluck('name'));

});

test('Find', function () {
    $product = new Product;
    $product['name'] = 'John Doe';
    $product['description'] = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vivamus lacinia odio vitae vestibulum vestibulum.';
    $product['in_stock'] = 35;
    $product->save();

    $check = Product::find($product->id);
    $this->assertInstanceOf(Product::class, $check);
    $this->assertTrue($check->exists);
    $this->assertEquals($product->id, $check->id);

    $this->assertEquals('John Doe', $check->name);
    $this->assertEquals(35, $check->in_stock);
});

test('Meta', function () {
    $product = new Product;
    $product['name'] = 'John Doe';
    $product['description'] = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vivamus lacinia odio vitae vestibulum vestibulum.';
    $product['in_stock'] = 35;
    $product['_id'] = 'foo-bar';
    $product->save();

    $check = Product::find($product->id);

    $meta = $check->getMeta();

    expect($meta)->not()->toBeNull()
        ->and($meta->getIndex())->not()->toBeNull()
        ->and($meta->getScore())->not()->toBeNull()
        ->and($meta->getHighlights())->not()->toBeNull()
        ->and($meta->getHighlights())->toBeArray()
        ->and($meta->getId())->toBe('foo-bar')
        ->and($meta->getQuery())->toBeArray()
        ->and($meta->asArray())->toBeArray();
});

test('Get', function () {
    // this also test bulk insert yay!
    Product::insert([
        ['name' => 'John Doe'],
        ['name' => 'Jane Doe'],
    ]);

    $products = Product::get();
    $this->assertCount(2, $products);
    $this->assertInstanceOf(EloquentCollection::class, $products);
    $this->assertInstanceOf(Product::class, $products[0]);
});

test('First', function () {
    // this also test bulk insert yay!
    Product::insert([
        ['name' => 'John Doe'],
        ['name' => 'Jane Doe'],
    ]);

    $product = Product::first();
    $this->assertInstanceOf(Product::class, $product);
    $this->assertEquals('John Doe', $product->name);
});

test('No Document', function () {
    $items = Product::where('name', 'nothing')->get();
    $this->assertInstanceOf(EloquentCollection::class, $items);
    $this->assertEquals(0, $items->count());

    $item = Product::where('name', 'nothing')->first();
    $this->assertNull($item);

    $item = Product::find('51c33d8981fec6813e00000a');
    $this->assertNull($item);

});

test('Find Or Fail', function () {
    $this->expectException(ModelNotFoundException::class);
    Product::findOrFail('51c33d8981fec6813e00000a');

});

test('Create', function () {
    $product = Product::create(['name' => 'Jane Poe']);
    $this->assertInstanceOf(Product::class, $product);

    $this->assertTrue($product->exists);
    $this->assertEquals('Jane Poe', $product->name);

    $check = Product::where('name', 'Jane Poe')->first();
    $this->assertInstanceOf(Product::class, $check);
    $this->assertEquals($product->id, $check->id);
});

test('Destroy', function () {
    $product = new Product;
    $product['name'] = 'John Doe';
    $product['description'] = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vivamus lacinia odio vitae vestibulum vestibulum.';
    $product['in_stock'] = 35;
    $product->save();

    Product::destroy((string) $product->id);
    $this->assertEquals(0, Product::count());
});

test('Touch', function () {
    $product = new Product;
    $product['name'] = 'John Doe';
    $product['description'] = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vivamus lacinia odio vitae vestibulum vestibulum.';
    $product['in_stock'] = 35;
    $product->save();

    $old = $product->updated_at;
    sleep(1);
    $product->touch();

    $check = Product::find($product->id);
    $this->assertInstanceOf(Product::class, $check);

    $this->assertNotEquals($old, $check->updated_at);
});

test('Soft Delete', function () {
    Soft::truncate();
    Soft::create(['name' => 'John Doe', 'status' => 1]);
    Soft::create(['name' => 'Jane Doe', 'status' => 2]);

    $this->assertEquals(2, Soft::count());

    $object = Soft::where('status', 1)->first();
    $this->assertInstanceOf(Soft::class, $object);
    $this->assertTrue($object->exists);
    $this->assertFalse($object->trashed());
    $this->assertNull($object->deleted_at);

    $object->delete();
    $this->assertTrue($object->trashed());
    $this->assertNotNull($object->deleted_at);

    $object = Soft::where('status', 1)->first();
    $this->assertNull($object);

    $this->assertEquals(1, Soft::count());
    $this->assertEquals(2, Soft::withTrashed()->count());

    $object = Soft::withTrashed()->where('status', 1)->first();
    $this->assertNotNull($object);
    $this->assertInstanceOf(Carbon::class, $object->deleted_at);
    $this->assertTrue($object->trashed());

    $object->restore();
    $this->assertEquals(2, Soft::count());

});

test('Scope', function () {
    Product::insert([
        ['name' => 'knife', 'color' => 'green'],
        ['name' => 'spoon', 'color' => 'red'],
    ]);

    $green = Product::green()->get();
    $this->assertEquals(1, $green->count());
});

test('To Array', function () {
    $product = Product::create(['name' => 'fork', 'color' => 'green']);

    $array = $product->toArray();
    $keys = array_keys($array);
    sort($keys);
    $this->assertEquals(['_id', 'color', 'created_at', 'name', 'updated_at'], $keys);
    $this->assertIsString($array['created_at']);
    $this->assertIsString($array['updated_at']);
    $this->assertIsString($array['_id']);
});

test('Dot Notation', function () {

    $product = Product::create([
        'name' => 'John Doe',
        'manufacturer' => [
            'name' => 'Paris',
            'country' => 'France',
        ],
    ]);

    $this->assertEquals('Paris', $product->getAttribute('manufacturer.name'));
    $this->assertEquals('Paris', $product['manufacturer.name']);
    $this->assertEquals('Paris', $product->{'manufacturer.name'});

    // Fill
    // TODO: Fix this it's not working correctly
    //    $product->fill(['manufacturer.name' => 'Strasbourg']);
    //
    //    $this->assertEquals('Strasbourg', $product['manufacturer.name']);
});

test('Truncate Model', function () {
    Product::create(['name' => 'John Doe']);

    Product::truncate();
    sleep(2);

    $this->assertEquals(0, Product::count());

});

test('Chunk By Id', function () {

    Product::create(['name' => 'fork', 'order_values' => [10, 20]]);
    Product::create(['name' => 'spork', 'order_values' => [10, 35, 20, 30]]);
    Product::create(['name' => 'spoon', 'order_values' => [20, 30]]);

    $names = [];
    Product::chunkById(2, function (EloquentCollection $items) use (&$names) {
        $names = array_merge($names, $items->pluck('name')->all());
    });

    $this->assertEquals(['fork', 'spork', 'spoon'], $names);

});

test('Guarded Model', function () {
    $model = new Guarded;

    // foobar is properly guarded
    $model->fill(['foobar' => 'ignored', 'name' => 'John Doe']);
    $this->assertFalse(isset($model->foobar));
    $this->assertSame('John Doe', $model->name);

    // foobar is guarded to any level
    $model->fill(['foobar->level2' => 'v2']);
    $this->assertNull($model->getAttribute('foobar->level2'));

    // multi level statement also guarded
    $model->fill(['level1->level2' => 'v1']);
    $this->assertNull($model->getAttribute('level1->level2'));

    // level1 is still writable
    $dataValues = ['array', 'of', 'values'];
    $model->fill(['level1' => $dataValues]);
    $this->assertEquals($dataValues, $model->getAttribute('level1'));

});

test('First Or Create', function () {
    $name = 'Jane Poe';

    $user = Product::where('name', $name)->first();
    $this->assertNull($user);

    $user = Product::firstOrCreate(['name' => $name]);
    $this->assertInstanceOf(Product::class, $user);
    $this->assertTrue($user->exists);
    $this->assertEquals($name, $user->name);

    $check = Product::where('name', $name)->first();
    $this->assertInstanceOf(Product::class, $check);
    $this->assertEquals($user->id, $check->id);

});

test('Update Or Create', function () {
    // Insert data to ensure we filter on the correct criteria, and not getting
    // the first document randomly.
    Product::insert([
        ['name' => 'fixture@example.com'],
        ['name' => 'john.doe@example.com'],
    ]);

    Carbon::setTestNow('2010-01-01');
    $createdAt = Carbon::now()->getTimestamp();
    $events = [];
    registerModelEvents(Product::class, $events);

    // Create
    $product = Product::updateOrCreate(
        ['name' => 'bar'],
        ['name' => 'bar', 'in_stock' => 30],
    );

    $this->assertInstanceOf(Product::class, $product);
    $this->assertEquals('bar', $product->name);
    $this->assertEquals(30, $product->in_stock);
    $this->assertEquals($createdAt, $product->created_at->getTimestamp());
    $this->assertEquals($createdAt, $product->updated_at->getTimestamp());
    $this->assertEquals(['saving', 'creating', 'created', 'saved'], $events);
    Carbon::setTestNow('2010-02-01');
    $updatedAt = Carbon::now()->getTimestamp();

    // Update
    $events = [];
    $product = Product::updateOrCreate(
        ['name' => 'bar'],
        ['in_stock' => 25]
    );

    $this->assertInstanceOf(Product::class, $product);
    $this->assertEquals('bar', $product->name);
    $this->assertEquals(25, $product->in_stock);
    $this->assertEquals($createdAt, $product->created_at->getTimestamp());
    $this->assertEquals($updatedAt, $product->updated_at->getTimestamp());
    $this->assertEquals(['saving', 'updating', 'updated', 'saved'], $events);

    // Stored data
    $checkProduct = Product::where(['name' => 'bar'])->first();
    $this->assertInstanceOf(Product::class, $checkProduct);
    $this->assertEquals('bar', $checkProduct->name);
    $this->assertEquals(25, $checkProduct->in_stock);
    $this->assertEquals($createdAt, $checkProduct->created_at->getTimestamp());
    $this->assertEquals($updatedAt, $checkProduct->updated_at->getTimestamp());
});

test('Create With Null Id', function (string $id) {
    $product = Product::create([$id => null, 'email' => 'foo@bar']);
    $this->assertNotNull($product->id);
    $this->assertSame(1, Product::count());
})->with([
    'id',
    //    #TODO: this fails.
    //    '_id'
]);

function registerModelEvents(string $modelClass, array &$events): void
{
    $modelClass::creating(function () use (&$events) {
        $events[] = 'creating';
    });
    $modelClass::created(function () use (&$events) {
        $events[] = 'created';
    });
    $modelClass::updating(function () use (&$events) {
        $events[] = 'updating';
    });
    $modelClass::updated(function () use (&$events) {
        $events[] = 'updated';
    });
    $modelClass::saving(function () use (&$events) {
        $events[] = 'saving';
    });
    $modelClass::saved(function () use (&$events) {
        $events[] = 'saved';
    });
}
