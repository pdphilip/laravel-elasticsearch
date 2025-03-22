<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use PDPhilip\Elasticsearch\Connection;
use PDPhilip\Elasticsearch\Eloquent\Model;
use PDPhilip\Elasticsearch\Schema\Schema;
use PDPhilip\Elasticsearch\Tests\Models\Book;
use PDPhilip\Elasticsearch\Tests\Models\Guarded;
use PDPhilip\Elasticsearch\Tests\Models\Item;
use PDPhilip\Elasticsearch\Tests\Models\Soft;
use PDPhilip\Elasticsearch\Tests\Models\User;

beforeEach(function () {
    User::executeSchema();
    Item::executeSchema();
    Soft::executeSchema();
});

it('tests new model', function () {
    $user = new User;

    expect(Model::isElasticsearchModel($user))->toBeTrue()
        ->and($user->getConnection())->toBeInstanceOf(Connection::class)
        ->and($user->exists)->toBeFalse()
        ->and($user->getTable())->toBe('users')
        ->and($user->getKeyName())->toBe('id');
});

it('tests insert', function () {
    $user = new User;
    $user->name = 'John Doe';
    $user->title = 'admin';
    $user->age = 35;

    $user->save();

    expect($user->exists)->toBeTrue()
        ->and(User::count())->toBe(1)
        ->and(isset($user->id))->toBeTrue()
        ->and($user->id)->toBeString()->not->toBeEmpty()
        ->and($user->id)->not->toHaveLength(0)
        ->and($user->created_at)->toBeInstanceOf(Carbon::class)
        ->and($user->name)->toBe('John Doe')
        ->and($user->age)->toBe(35);
});

it('tests update', function () {
    $user = new User;
    $user->name = 'John Doe';
    $user->title = 'admin';
    $user->age = 35;
    $user->save();

    $check = User::find($user->id);
    expect($check)->toBeInstanceOf(User::class);
    $check->age = 36;
    $check->save();

    expect($check->exists)->toBeTrue()
        ->and($check->created_at)->toBeInstanceOf(Carbon::class)
        ->and($check->updated_at)->toBeInstanceOf(Carbon::class)
        ->and(User::count())->toBe(1)
        ->and($check->name)->toBe('John Doe')
        ->and($check->age)->toBe(36);

    $user->update(['age' => 20]);

    $check = User::find($user->id);
    expect($check->age)->toBe(20);

    $check->age = 24;
    $check->fullname = 'Hans Thomas'; // new field
    $check->save();

    $check = User::find($user->id);
    expect($check->age)->toBe(24)
        ->and($check->fullname)->toBe('Hans Thomas');
});

it('tests upsert', function () {
    $result = User::upsert([
        ['email' => 'foo', 'name' => 'bar'],
        ['name' => 'bar2', 'email' => 'foo2'],
    ], ['email']);

    expect($result)->toBe(2)
        ->and(User::count())->toBe(2)
        ->and(User::where('email', 'foo')->first()->name)->toBe('bar');

    // Update 1 document
    $result = User::upsert([
        ['email' => 'foo', 'name' => 'bar2'],
        ['name' => 'bar2', 'email' => 'foo2'],
    ], 'email', ['name']);

    expect($result)->toBe(2)
        ->and(User::count())->toBe(2)
        ->and(User::where('email', 'foo')->first()->name)->toBe('bar2');

    // Test single document update
    $result = User::upsert(['email' => 'foo', 'name' => 'bar3'], 'email');

    expect($result)->toBe(1)
        ->and(User::count())->toBe(2)
        ->and(User::where('email', 'foo')->first()->name)->toBe('bar3');
})->todo();

it('tests manual string id', function () {
    $user = new User;
    $user->id = '4af9f23d8ead0e1d32000000';
    $user->name = 'John Doe';
    $user->title = 'admin';
    $user->age = 35;
    $user->save();

    expect($user->exists)->toBeTrue()
        ->and($user->id)->toBe('4af9f23d8ead0e1d32000000');

    $user = new User;
    $user->id = 'customId';
    $user->name = 'John Doe';
    $user->title = 'admin';
    $user->age = 35;
    $user->save();

    expect($user->exists)->toBeTrue()
        ->and($user->id)->toBe('customId');

    $raw = $user->getAttributes();
    expect($raw['id'])->toBeString();
});

it('tests manual int id', function () {
    $user = new User;
    $user->id = 1;
    $user->name = 'John Doe';
    $user->title = 'admin';
    $user->age = 35;
    $user->save();

    expect($user->exists)->toBeTrue()
        ->and($user->id)->toBe(1);

    $raw = $user->getAttributes();
    expect($raw['id'])->toBeInt();
});

it('tests delete', function () {
    $user = new User;
    $user->name = 'John Doe';
    $user->title = 'admin';
    $user->age = 35;
    $user->save();

    expect($user->exists)->toBeTrue()
        ->and(User::count())->toBe(1);

    $user->delete();

    expect(User::count())->toBe(0);
});

it('tests all', function () {
    $user = new User;
    $user->name = 'John Doe';
    $user->title = 'admin';
    $user->age = 35;
    $user->save();

    $user = new User;
    $user->name = 'Jane Doe';
    $user->title = 'user';
    $user->age = 32;
    $user->save();

    $all = User::all();

    expect($all)->toHaveCount(2)
        ->and($all->pluck('name'))->toContain('John Doe', 'Jane Doe');
});

it('tests find', function () {
    $user = new User;
    $user->name = 'John Doe';
    $user->title = 'admin';
    $user->age = 35;
    $user->save();

    $check = User::find($user->id);
    expect($check)->toBeInstanceOf(User::class)
        ->and(Model::isElasticsearchModel($check))->toBeTrue()
        ->and($check->exists)->toBeTrue()
        ->and($check->id)->toBe($user->id)
        ->and($check->name)->toBe('John Doe')
        ->and($check->age)->toBe(35);
});

it('tests insert empty', function () {
    $success = User::insert([]);
    expect($success)->toBeTrue();
});

it('tests get', function () {
    User::insert([
        ['name' => 'John Doe'],
        ['name' => 'Jane Doe'],
    ]);

    $users = User::get();
    expect($users)->toHaveCount(2)
        ->and($users)->toBeInstanceOf(EloquentCollection::class)
        ->and($users[0])->toBeInstanceOf(User::class);
});

it('tests first', function () {
    User::insert([
        ['name' => 'John Doe'],
        ['name' => 'Jane Doe'],
    ]);

    $user = User::first();
    expect($user)->toBeInstanceOf(User::class)
        ->and(Model::isElasticsearchModel($user))->toBeTrue()
        ->and($user->name)->toBe('John Doe');
});

it('tests no document', function () {
    $items = Item::where('name', 'nothing')->get();
    expect($items)->toBeInstanceOf(EloquentCollection::class)
        ->and($items->count())->toBe(0);

    $item = Item::where('name', 'nothing')->first();
    expect($item)->toBeNull();

    $item = Item::find('51c33d8981fec6813e00000a');
    expect($item)->toBeNull();
});

it('tests find or fail', function () {
    expect(fn () => User::findOrFail('51c33d8981fec6813e00000a'))
        ->toThrow(ModelNotFoundException::class);
});

it('tests create', function () {
    $user = User::withoutRefresh()->create(['name' => 'Jane Poe']);
    expect($user)->toBeInstanceOf(User::class)
        ->and(Model::isElasticsearchModel($user))->toBeTrue()
        ->and($user->exists)->toBeTrue()
        ->and($user->name)->toBe('Jane Poe');
    sleep(1);
    $check = User::where('name', 'Jane Poe')->first();
    expect($check)->toBeInstanceOf(User::class)
        ->and($check->id)->toBe($user->id);
});

it('tests destroy', function () {
    $user = new User;
    $user->name = 'John Doe';
    $user->title = 'admin';
    $user->age = 35;
    $user->save();

    User::destroy((string) $user->id);

    expect(User::count())->toBe(0);
});

it('tests touch', function () {
    $user = new User;
    $user->name = 'John Doe';
    $user->title = 'admin';
    $user->age = 35;
    $user->save();

    $old = $user->updated_at;
    sleep(1);
    $user->touch();

    $check = User::find($user->id);
    expect($check)->toBeInstanceOf(User::class)
        ->and($check->updated_at)->not->toBe($old);
});

it('tests soft delete', function () {
    Soft::create(['name' => 'John Doe']);
    Soft::create(['name' => 'Jane Doe']);

    expect(Soft::count())->toBe(2);

    $object = Soft::where('name', 'John Doe')->first();
    expect($object)->toBeInstanceOf(Soft::class)
        ->and($object->exists)->toBeTrue()
        ->and($object->trashed())->toBeFalse()
        ->and($object->deleted_at)->toBeNull();

    $object->delete();
    expect($object->trashed())->toBeTrue()
        ->and($object->deleted_at)->not->toBeNull();

    $object = Soft::where('name', 'John Doe')->first();
    expect($object)->toBeNull()
        ->and(Soft::count())->toBe(1)
        ->and(Soft::withTrashed()->count())->toBe(2);

    $object = Soft::withTrashed()->where('name', 'John Doe')->first();
    expect($object)->not->toBeNull()
        ->and($object->deleted_at)->toBeInstanceOf(Carbon::class)
        ->and($object->trashed())->toBeTrue();

    $object->restore();
    expect(Soft::count())->toBe(2);
});

it('tests scope', function () {
    Item::insert([
        ['name' => 'knife', 'type' => 'sharp'],
        ['name' => 'spoon', 'type' => 'round'],
    ]);

    $sharp = Item::sharp()->get();
    expect($sharp->count())->toBe(1);
});

it('tests to array', function () {
    $item = Item::create(['name' => 'fork', 'type' => 'sharp']);

    $array = $item->toArray();
    $keys = array_keys($array);
    sort($keys);
    expect($keys)->toEqual([
        'created_at',
        'id',
        'name',
        'type',
        'updated_at',
    ])
        ->and($array['created_at'])->toBeString()
        ->and($array['updated_at'])->toBeString()
        ->and($array['id'])->toBeString();
});

it('tests dates', function () {
    $user = User::create(['name' => 'John Doe', 'birthday' => new DateTime('1965/1/1')]);
    expect($user->birthday)->toBeInstanceOf(Carbon::class);

    $user = User::whereDate('birthday', '<', new DateTime('1968/1/1'))->first();
    expect($user->name)->toBe('John Doe');

    $user = User::create(['name' => 'John Doe', 'birthday' => new DateTime('1980/1/1')]);
    expect($user->birthday)->toBeInstanceOf(Carbon::class);

    $check = User::find($user->id);

    expect($check->birthday)->toBeInstanceOf(Carbon::class)
        ->and($check->birthday->format('U'))->toBe($user->birthday->format('U'));

    $user = User::whereDate('birthday', '>', new DateTime('1975/1/1'))->first();
    expect($user->name)->toBe('John Doe');

    // test custom date format for json output
    $json = $user->toArray();
    expect($json['birthday'])->toBe($user->birthday->format('l jS \of F Y h:i:s A'))
        ->and($json['created_at'])->toBe($user->created_at->format('l jS \of F Y h:i:s A'));

    // test created_at
    $item = Item::create(['name' => 'sword']);

    expect($item->getRawOriginal('created_at'))->toBeInstanceOf(Carbon::class)
        ->and($item->getRawOriginal('created_at')->toDateTime()->getTimestamp())
        ->toBe($item->created_at->getTimestamp())
        ->and(abs(time() - $item->created_at->getTimestamp()))->toBeLessThan(2);

    $item = Item::create(['name' => 'sword']);
    expect($item)->toBeInstanceOf(Item::class);
    $json = $item->toArray();
    expect($json['created_at'])->toBe($item->created_at->toISOString());
});

it('tests date null', function () {
    $user = User::create(['name' => 'Jane Doe', 'birthday' => null]);
    expect($user->birthday)->toBeNull();

    $user->setAttribute('birthday', new DateTime);
    $user->setAttribute('birthday', null);
    expect($user->birthday)->toBeNull();

    $user->save();

    // Re-fetch to be sure
    $user = User::find($user->id);
    expect($user->birthday)->toBeNull();

    // Nested field with dot notation
    $user = User::create(['name' => 'Jane Doe', 'entry' => ['date' => null]]);
    expect($user->getAttribute('entry.date'))->toBeNull();

    $user->setAttribute('entry.date', new DateTime);
    $user->setAttribute('entry.date', null);
    expect($user->getAttribute('entry.date'))->toBeNull();

    // Re-fetch to be sure
    $user = User::find($user->id);
    expect($user->getAttribute('entry.date'))->toBeNull();
});

it('tests id attribute', function () {
    $user = User::create(['name' => 'John Doe']);
    expect($user)->toBeInstanceOf(User::class)
        ->and($user->id)->toBe($user->_id);

    $user = User::create(['id' => 'custom_id', 'name' => 'John Doe']);
    expect($user->id)->toBe($user->_id);
});

it('tests attribute mutator', function () {
    $username = 'JaneDoe';
    $usernameSlug = Str::slug($username);
    $user = User::create([
        'name' => 'Jane Doe',
        'username' => $username,
    ]);

    expect($user->getAttribute('username'))->not->toBe($username)
        ->and($user['username'])->not->toBe($username)
        ->and($user->username)->not->toBe($username)
        ->and($user->getAttribute('username'))->toBe($usernameSlug)
        ->and($user['username'])->toBe($usernameSlug)
        ->and($user->username)->toBe($usernameSlug);
});

it('tests multiple level dot notation', function () {
    $book = Book::create([
        'title' => 'A Game of Thrones',
        'chapters' => [
            'one' => ['title' => 'The first chapter'],
        ],
    ]);
    expect($book)->toBeInstanceOf(Book::class)
        ->and($book->chapters)->toBe(['one' => ['title' => 'The first chapter']])
        ->and($book['chapters.one'])->toBe(['title' => 'The first chapter'])
        ->and($book['chapters.one.title'])->toBe('The first chapter');
});

it('tests chunk by id', function () {
    User::create(['name' => 'fork', 'tags' => ['sharp', 'pointy']]);
    User::create(['name' => 'spork', 'tags' => ['sharp', 'pointy', 'round', 'bowl']]);
    User::create(['name' => 'spoon', 'tags' => ['round', 'bowl']]);

    $names = [];
    User::chunkById(2, function (EloquentCollection $items) use (&$names) {
        $names = array_merge($names, $items->pluck('name')->all());
    });

    expect($names)->toBe(['fork', 'spork', 'spoon']);
})->todo();

it('tests chunk across many items', function () {
    $users = [];
    for ($i = 0; $i < 15000; $i++) {
        $users[] = [
            'name' => "User {$i}",
            'age' => rand(1, 100),
            'title' => rand(0, 1) ? 'admin' : 'user',
        ];
    }
    User::insert($users);

    $names = [];
    User::chunk(1000, function (EloquentCollection $items) use (&$names) {
        $names = [
            ...$names,
            ...$items,
        ];
    });

    expect($names)->toHaveCount(15000);
});

it('tests cursor across many items', function () {
    $users = [];
    for ($i = 0; $i < 15000; $i++) {
        $users[] = [
            'name' => "User {$i}",
            'age' => rand(1, 100),
            'title' => rand(0, 1) ? 'admin' : 'user',
        ];
    }
    User::insert($users);

    $names = [];
    foreach (User::limit(50)->cursor() as $cursor) {
        $names[] = $cursor;
    }
    expect($names)->toHaveCount(50);

    $names = [];
    foreach (User::limit(20000)->cursor() as $cursor) {
        $names[] = $cursor;
    }
    expect($names)->toHaveCount(15000);
});

it('tests truncate model', function () {
    User::create(['name' => 'John Doe']);

    User::truncate();

    expect(User::count())->toBe(0);
});

it('tests guarded model', function () {
    $model = new Guarded;

    // foobar is properly guarded
    $model->fill(['foobar' => 'ignored', 'name' => 'John Doe']);
    expect(isset($model->foobar))->toBeFalse()
        ->and($model->name)->toBe('John Doe');

    // foobar is guarded to any level
    $model->fill(['foobar->level2' => 'v2']);
    expect($model->getAttribute('foobar->level2'))->toBeNull();

    // multi level statement also guarded
    $model->fill(['level1->level2' => 'v1']);
    expect($model->getAttribute('level1->level2'))->toBeNull();

    // level1 is still writable
    $dataValues = ['array', 'of', 'values'];
    $model->fill(['level1' => $dataValues]);
    expect($model->getAttribute('level1'))->toBe($dataValues);
});

it('tests first or create', function () {
    $name = 'Jane Poe';

    $user = User::where('name', $name)->first();
    expect($user)->toBeNull();

    $user = User::firstOrCreate(['name' => $name]);
    expect($user)->toBeInstanceOf(User::class)
        ->and(Model::isElasticsearchModel($user))->toBeTrue()
        ->and($user->exists)->toBeTrue()
        ->and($user->name)->toBe($name);

    $check = User::where('name', $name)->first();
    expect($check)->toBeInstanceOf(User::class)
        ->and($check->id)->toBe($user->id);
});

it('tests first or fail', function () {
    User::firstOrFail(['name' => 'foo bar']);
})->throws(ModelNotFoundException::class);

it('tests changes the table index', function () {

    $schema = Schema::connection('elasticsearch');
    $schema->dropIfExists('urs_test');
    $user = new User;
    $user->name = 'one';
    $user->setTable('urs_test');
    $user->save();
    $check = User::withTable('urs_test')->first();
    expect($check->getFullTable())->toBe('urs_test');
});

it('should throw an error if suffix is applied to a non dynamic index', function () {

    $schema = Schema::connection('elasticsearch');
    $schema->dropIfExists('users_test');
    $user = new User;
    $user->name = 'one';
    $user->withSuffix('_test');
    $user->save();
})->throws(\PDPhilip\Elasticsearch\Exceptions\DynamicIndexException::class);

it('gets the query meta', function () {

    $user = new User;
    $user->name = 'one';
    $user->save();

    $check = User::first();
    expect($check->getMeta())->toBeInstanceOf(\PDPhilip\Elasticsearch\Data\ModelMeta::class)
        ->and($check->getMeta()->toArray())->toHaveKeys(['score', 'index']);
});

it('tests numeric field name', function () {
    $user = new User;
    $user->{1} = 'one';
    $user->{2} = ['3' => 'two.three'];
    $user->save();

    expect($user->id)->toBeString()->and(isset($user->_id))->toBeTrue()
        ->and(User::count())->toBe(1);
});

it('tests create with null id', function (string $id) {
    $user = User::create([$id => null, 'email' => 'foo@bar']);
    expect($user->id)->toBeString()->and($user->_id)->toBeString()->and($user->_id)->toBe($user->id)->and(User::count())->toBe(1);
})->with([
    'id',
    '_id',
]);
