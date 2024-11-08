<?php

  declare(strict_types=1);

  use Illuminate\Support\Facades\DB;
  use Workbench\App\Models\HiddenAnimal;
  use Workbench\App\Models\Item;
  use Workbench\App\Models\User;

  beforeEach(function () {
    User::executeSchema();
    Item::executeSchema();

    DB::table('users')->truncate();
    DB::table('items')->truncate();

  });

  it('tests delete with id', function () {
    $user = DB::table('users')->insertGetId([
                                              ['name' => 'Jane Doe', 'age' => 20],
                                            ]);

    $userId = (string) $user;

    DB::table('items')->insert([
                                 ['name' => 'one thing', 'user_id' => $userId],
                                 ['name' => 'last thing', 'user_id' => $userId],
                                 ['name' => 'another thing', 'user_id' => $userId],
                                 ['name' => 'one more thing', 'user_id' => $userId],
                               ]);

    $product = DB::table('items')->first();

    $pid = (string) ($product['id']);

    DB::table('items')->where('user_id', $userId)->delete($pid);

    expect(DB::table('items')->count())->toBe(3);

    $product = DB::table('items')->first();

    $pid = $product['id'];

    DB::table('items')->where('user_id', $userId)->delete($pid);

    DB::table('items')->where('user_id', $userId)->delete(md5('random-id'));

    expect(DB::table('items')->count())->toBe(2);
  });

  it('tests collection', function () {
    expect(DB::table('users'))->toBeInstanceOf(\PDPhilip\Elasticsearch\Query\Builder::class);
  });

  it('tests get', function () {
    $users = DB::table('users')->get();
    expect($users)->toHaveCount(0);

    DB::table('users')->insert(['name' => 'John Doe']);

    $users = DB::table('users')->get();
    expect($users)->toHaveCount(1);
  });

  it('tests no document', function () {
    $items = DB::table('items')->where('name', 'nothing')->get()->toArray();
    expect($items)->toBe([]);

    $item = DB::table('items')->where('name', 'nothing')->first();
    expect($item)->toBeNull();

    $item = DB::table('items')->where('id', '51c33d8981fec6813e00000a')->first();
    expect($item)->toBeNull();
  });

  it('tests insert', function () {
    DB::table('users')->insert([
                                 'tags' => ['tag1', 'tag2'],
                                 'name' => 'John Doe',
                               ]);

    $users = DB::table('users')->get();
    expect($users)->toHaveCount(1);

    $user = $users[0];
    expect($user['name'])->toBe('John Doe')
                         ->and($user['tags'])->toBeArray();
  });

  it('tests batch insert', function () {
    DB::table('users')->insert([
                                 [
                                   'tags' => ['tag1', 'tag2'],
                                   'name' => 'Jane Doe',
                                 ],
                                 [
                                   'tags' => ['tag3'],
                                   'name' => 'John Doe',
                                 ],
                               ]);

    $users = DB::table('users')->get();
    expect($users)->toHaveCount(2)
                  ->and($users[0]['tags'])->toBeArray();
  });

  it('tests find', function () {
    $id = DB::table('users')->insertGetId(['name' => 'John Doe']);

    $user = DB::table('users')->find($id);
    expect($user['name'])->toBe('John Doe');
  });

  it('tests find null', function () {
    $user = DB::table('users')->find(null);
    expect($user)->toBeNull();
  });

  it('tests count', function () {
    DB::table('users')->insert([
                                 ['name' => 'Jane Doe'],
                                 ['name' => 'John Doe'],
                               ]);

    expect(DB::table('users')->count())->toBe(2);
  });

  it('tests update', function () {
    DB::table('users')->insert([
                                 ['name' => 'Jane Doe', 'age' => 20],
                                 ['name' => 'John Doe', 'age' => 21],
                               ]);

    DB::table('users')->where('name', 'John Doe')->update(['age' => 100]);

    $john = DB::table('users')->where('name', 'John Doe')->first();
    $jane = DB::table('users')->where('name', 'Jane Doe')->first();

    expect($john['age'])->toBe(100)
                      ->and($jane['age'])->toBe(20);
  });

  it('tests delete', function () {
    DB::table('users')->insert([
                                 ['name' => 'Jane Doe', 'age' => 20],
                                 ['name' => 'John Doe', 'age' => 25],
                               ]);

    DB::table('users')->where('age', '<', 10)->delete();
    expect(DB::table('users')->count())->toBe(2);

    DB::table('users')->where('age', '<', 25)->delete();
    expect(DB::table('users')->count())->toBe(1);
  });

  it('tests truncate', function () {
    DB::table('users')->insert(['name' => 'John Doe']);
    DB::table('users')->insert(['name' => 'John Doe']);

    expect(DB::table('users')->count())->toBe(2);

    DB::table('users')->truncate();

    expect(DB::table('users')->count())->toBe(0);
  });

  it('tests sub key', function () {
    DB::table('users')->insert([
                                 [
                                   'name' => 'John Doe',
                                   'address' => ['country' => 'Belgium', 'city' => 'Ghent'],
                                 ],
                                 [
                                   'name' => 'Jane Doe',
                                   'address' => ['country' => 'France', 'city' => 'Paris'],
                                 ],
                               ]);

    $users = DB::table('users')->where('address.country', 'Belgium')->get();

    expect($users)->toHaveCount(1);
    expect($users[0]['name'])->toBe('John Doe');
  });

  it('tests in array', function () {
    DB::table('items')->insert([
                                 ['tags' => ['tag1', 'tag2', 'tag3', 'tag4']],
                                 ['tags' => ['tag2']],
                               ]);

    $items = DB::table('items')->where('tags', 'tag2')->get();
    expect($items)->toHaveCount(2);

    $items = DB::table('items')->where('tags', 'tag1')->get();
    expect($items)->toHaveCount(1);
  });

  it('tests distinct', function () {
    DB::table('items')->insert([
                                 ['name' => 'knife', 'type' => 'sharp'],
                                 ['name' => 'fork', 'type' => 'sharp'],
                                 ['name' => 'spoon', 'type' => 'round'],
                                 ['name' => 'spoon', 'type' => 'round'],
                               ]);

    $items = DB::table('items')->distinct('name')->get()->pluck('name')->sort()->values()->toArray();
    expect($items)->toHaveCount(3)->toEqual(['fork', 'knife', 'spoon']);

    $types = DB::table('items')->distinct('type')->get()->pluck('type')->sort()->values()->toArray();
    expect($types)->toHaveCount(2)->toEqual(['round', 'sharp']);
  });

  it('tests custom ID', function () {
    $tags = [['id' => 'sharp', 'name' => 'Sharp']];
    DB::table('items')->insert([
                                 ['id' => 'knife', 'type' => 'sharp', 'amount' => 34, 'tags' => $tags],
                                 ['id' => 'fork', 'type' => 'sharp', 'amount' => 20, 'tags' => $tags],
                                 ['id' => 'spoon', 'type' => 'round', 'amount' => 3],
                               ]);

    $item = DB::table('items')->find('knife');
    expect($item['id'])->toBe('knife')
                     ->and($item)->not->toHaveProperty('_id')
                                      ->and($item['tags'][0]['id'])->toBe('sharp')
                                      ->and($item['tags'][0])->not->toHaveKey('_id');

    $item = DB::table('items')->where('id', 'fork')->first();
    expect($item['id'])->toBe('fork');

    $items = DB::table('items')->whereIn('tags.id', ['sharp'])->get();
    expect($items)->toHaveCount(2);
  });

  it('tests take', function () {
    DB::table('items')->insert([
                                 ['name' => 'knife', 'type' => 'sharp', 'amount' => 34],
                                 ['name' => 'fork', 'type' => 'sharp', 'amount' => 20],
                                 ['name' => 'spoon', 'type' => 'round', 'amount' => 3],
                                 ['name' => 'spoon', 'type' => 'round', 'amount' => 14],
                               ]);

    $items = DB::table('items')->orderBy('name')->take(2)->get();
    expect($items)->toHaveCount(2)
                  ->and($items[0]['name'])->toBe('fork');
  });

  it('tests skip', function () {
    DB::table('items')->insert([
                                 ['name' => 'knife', 'type' => 'sharp', 'amount' => 34],
                                 ['name' => 'fork', 'type' => 'sharp', 'amount' => 20],
                                 ['name' => 'spoon', 'type' => 'round', 'amount' => 3],
                                 ['name' => 'spoon', 'type' => 'round', 'amount' => 14],
                               ]);

    $items = DB::table('items')->orderBy('name')->skip(2)->get();
    expect($items)->toHaveCount(2);
    expect($items[0]['name'])->toBe('spoon');
  });

  it('tests pluck', function () {
    DB::table('users')->insert([
                                 ['name' => 'Jane Doe', 'age' => 20],
                                 ['name' => 'John Doe', 'age' => 25],
                               ]);

    $age = DB::table('users')->where('name', 'John Doe')->pluck('age')->toArray();
    expect($age)->toEqual([25]);
  });

  it('tests list', function () {
    DB::table('items')->insert([
                                 ['name' => 'knife', 'type' => 'sharp', 'amount' => 34],
                                 ['name' => 'fork', 'type' => 'sharp', 'amount' => 20],
                                 ['name' => 'spoon', 'type' => 'round', 'amount' => 3],
                                 ['name' => 'spoon', 'type' => 'round', 'amount' => 14],
                               ]);

    $list = DB::table('items')->pluck('name')->sort()->values()->toArray();
    expect($list)->toHaveCount(4)->toEqual(['fork', 'knife', 'spoon', 'spoon']);
  });

  it('tests aggregate', function () {
    DB::table('items')->insert([
                                 ['name' => 'knife', 'type' => 'sharp', 'amount' => 34],
                                 ['name' => 'fork', 'type' => 'sharp', 'amount' => 20],
                                 ['name' => 'spoon', 'type' => 'round', 'amount' => 3],
                                 ['name' => 'spoon', 'type' => 'round', 'amount' => 14],
                               ]);

    expect(DB::table('items')->sum('amount'))->toBe(71);
//    expect(DB::table('items')->count('amount'))->toBe(4);
//    expect(DB::table('items')->min('amount'))->toBe(3);
//    expect(DB::table('items')->max('amount'))->toBe(34);
//    expect(DB::table('items')->avg('amount'))->toBe(17.75);
  });

