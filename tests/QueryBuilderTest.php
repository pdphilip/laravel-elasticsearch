<?php

  declare(strict_types=1);

  use Illuminate\Support\Facades\Date;
  use Illuminate\Support\Facades\DB;
  use Illuminate\Support\LazyCollection;
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
                                 ['name' => 'knife', 'type' => 'sharp', 'amount' => 34, 'cost' => 3.40],
                                 ['name' => 'fork', 'type' => 'sharp', 'amount' => 20, 'cost' => 2.00],
                                 ['name' => 'spoon', 'type' => 'round', 'amount' => 3, 'cost' => 3.0],
                                 ['name' => 'spoon', 'type' => 'round', 'amount' => 14, 'cost' => 1.40],
                               ]);

    $list = DB::table('items')->pluck('name')->sort()->values()->toArray();
    expect($list)->toHaveCount(4)->toEqual(['fork', 'knife', 'spoon', 'spoon']);
  });

  it('tests aggregate', function () {
    DB::table('items')->insert([
                                 ['name' => 'knife', 'type' => 'sharp', 'amount' => 34, 'cost' => 3.40],
                                 ['name' => 'fork', 'type' => 'sharp', 'amount' => 20, 'cost' => 2.00],
                                 ['name' => 'spoon', 'type' => 'round', 'amount' => 3, 'cost' => 3.0],
                                 ['name' => 'spoon', 'type' => 'round', 'amount' => 14, 'cost' => 1.40],
                               ]);

    expect(DB::table('items')->sum('amount'))->toBe(71.0)
                                   ->and(DB::table('items')->count('amount'))->toBe(4)
                                   ->and(DB::table('items')->min('amount'))->toBe(3.0)
                                   ->and(DB::table('items')->max('amount'))->toBe(34.0)
                                   ->and(DB::table('items')->avg('amount'))->toBe(17.75);
  });

  it('tests subdocument aggregate', function () {
    DB::table('items')->insert([
                                 ['name' => 'knife', 'amount' => ['hidden' => 10, 'found' => 3]],
                                 ['name' => 'fork', 'amount' => ['hidden' => 35, 'found' => 12]],
                                 ['name' => 'spoon', 'amount' => ['hidden' => 14, 'found' => 21]],
                                 ['name' => 'spoon', 'amount' => ['hidden' => 6, 'found' => 4]],
                               ]);

    expect(DB::table('items')->sum('amount.hidden'))->toBe(65.0)
                                                    ->and(DB::table('items')->count('amount.hidden'))->toBe(4)
                                                    ->and(DB::table('items')->min('amount.hidden'))->toBe(6.0)
                                                    ->and(DB::table('items')->max('amount.hidden'))->toBe(35.0)
                                                    ->and(DB::table('items')->avg('amount.hidden'))->toBe(16.25);
  });

  it('updates subdocument fields', function () {
    $id = DB::table('users')->insertGetId(['name' => 'John Doe', 'address' => ['country' => 'Belgium']]);

    DB::table('users')->where('id', $id)->update(['address.country' => 'England']);

    $check = DB::table('users')->find($id);
    expect($check['address']['country'])->toBe('England');
  });

  it('handles dates correctly', function () {
    DB::table('users')->insert([
                                 ['name' => 'John Doe', 'birthday' => Date::parse('1980-01-01 00:00:00')],
                                 ['name' => 'Robert Roe', 'birthday' => Date::parse('1982-01-01 00:00:00')],
                                 ['name' => 'Mark Moe', 'birthday' => Date::parse('1983-01-01 00:00:00.1')],
                                 ['name' => 'Frank White', 'birthday' => Date::parse('1975-01-01 12:12:12.1')],
                               ]);

    $user = DB::table('users')
              ->where('birthday', Date::parse('1980-01-01 00:00:00'))
              ->first();
    expect($user['name'])->toBe('John Doe');

    $user = DB::table('users')
              ->where('birthday', Date::parse('1975-01-01 12:12:12.1'))
              ->first();
    expect($user['name'])->toBe('Frank White');
    expect($user['birthday'])->toBe('1975-01-01T12:12:12+00:00');

    $user = DB::table('users')->where('birthday', '=', new DateTime('1980-01-01 00:00:00'))->first();
    expect($user['name'])->toBe('John Doe');
    expect($user['birthday'])->toBe('1980-01-01T00:00:00+00:00');

    $start = new DateTime('1950-01-01 00:00:00');
    $stop  = new DateTime('1981-01-01 00:00:00');

    $users = DB::table('users')->whereBetween('birthday', [$start, $stop])->get();
    expect($users)->toHaveCount(2);
  });

  it('uses various query operators', function () {
    DB::table('users')->insert([
                                 ['name' => 'John Doe', 'age' => 30],
                                 ['name' => 'Jane Doe'],
                                 ['name' => 'Robert Roe', 'age' => 'thirty-one'],
                               ]);

    $results = DB::table('users')->where('age', 'exists', true)->get();
    expect($results)->toHaveCount(2);
    expect($results->pluck('name'))->toContain('John Doe', 'Robert Roe');

    $results = DB::table('users')->where('age', 'exists', false)->get();
    expect($results)->toHaveCount(1);
    expect($results[0]->name)->toBe('Jane Doe');

    $results = DB::table('users')->where('age', 'type', 2)->get();
    expect($results)->toHaveCount(1);
    expect($results[0]->name)->toBe('Robert Roe');

    $results = DB::table('users')->where('age', 'mod', [15, 0])->get();
    expect($results)->toHaveCount(1);
    expect($results[0]->name)->toBe('John Doe');

    $results = DB::table('users')->where('age', 'mod', [29, 1])->get();
    expect($results)->toHaveCount(1);
    expect($results[0]->name)->toBe('John Doe');

    DB::table('items')->insert([
                                 ['name' => 'fork', 'tags' => ['sharp', 'pointy']],
                                 ['name' => 'spork', 'tags' => ['sharp', 'pointy', 'round', 'bowl']],
                                 ['name' => 'spoon', 'tags' => ['round', 'bowl']],
                               ]);

    $results = DB::table('items')->where('tags', 'all', ['sharp', 'pointy'])->get();
    expect($results)->toHaveCount(2);

    $results = DB::table('items')->where('tags', 'size', 2)->get();
    expect($results)->toHaveCount(2);

    $regex = new Regex('.*doe', 'i');
    $results = DB::table('users')->where('name', 'regex', $regex)->get();
    expect($results)->toHaveCount(2);

    DB::table('users')->insert([
                                 [
                                   'name' => 'John Doe',
                                   'addresses' => [
                                     ['city' => 'Ghent'],
                                     ['city' => 'Paris'],
                                   ],
                                 ],
                                 [
                                   'name' => 'Jane Doe',
                                   'addresses' => [
                                     ['city' => 'Brussels'],
                                     ['city' => 'Paris'],
                                   ],
                                 ],
                               ]);

    $users = DB::table('users')->where('addresses', 'elemMatch', ['city' => 'Brussels'])->get();
    expect($users)->toHaveCount(1);
    expect($users[0]->name)->toBe('Jane Doe');
  });


  it('increments and decrements user age', function () {
    DB::table('users')->insert([
                                 ['name' => 'John Doe', 'age' => 30, 'note' => 'adult'],
                                 ['name' => 'Jane Doe', 'age' => 10, 'note' => 'minor'],
                                 ['name' => 'Robert Roe', 'age' => null],
                                 ['name' => 'Mark Moe'],
                               ]);

    $user = DB::table('users')->where('name', 'John Doe')->first();
    expect($user['age'])->toBe(30);

    DB::table('users')->where('name', 'John Doe')->increment('age');
    $user = DB::table('users')->where('name', 'John Doe')->first();
    expect($user['age'])->toBe(31);

    DB::table('users')->where('name', 'John Doe')->decrement('age');
    $user = DB::table('users')->where('name', 'John Doe')->first();
    expect($user['age'])->toBe(30);

    DB::table('users')->where('name', 'John Doe')->increment('age', 5);
    $user = DB::table('users')->where('name', 'John Doe')->first();
    expect($user['age'])->toBe(35);

    DB::table('users')->where('name', 'John Doe')->decrement('age', 5);
    $user = DB::table('users')->where('name', 'John Doe')->first();
    expect($user['age'])->toBe(30);

    DB::table('users')->where('name', 'Jane Doe')->increment('age', 10, ['note' => 'adult']);
    $user = DB::table('users')->where('name', 'Jane Doe')->first();
    expect($user['age'])->toBe(20);
    expect($user['note'])->toBe('adult');

    DB::table('users')->where('name', 'John Doe')->decrement('age', 20, ['note' => 'minor']);
    $user = DB::table('users')->where('name', 'John Doe')->first();
    expect($user['age'])->toBe(10);
    expect($user['note'])->toBe('minor');


  });

  it('verifies cursor returns lazy collection and checks names', function () {
    $data = [
      ['name' => 'fork', 'tags' => ['sharp', 'pointy']],
      ['name' => 'spork', 'tags' => ['sharp', 'pointy', 'round', 'bowl']],
      ['name' => 'spoon', 'tags' => ['round', 'bowl']],
    ];
    DB::table('items')->insert($data);

    $results = DB::table('items')->orderBy('id', 'asc')->cursor();

    expect($results)->toBeInstanceOf(Generator::class);
    foreach ($results as $i => $result) {
      expect($result['name'])->toBe($data[$i]['name']);
    }
  });

  it('increments each specified field by respective values', function () {
    DB::table('users')->insert([
                                 ['name' => 'John Doe', 'age' => 30, 'note' => 5],
                                 ['name' => 'Jane Doe', 'age' => 10, 'note' => 6],
                                 ['name' => 'Robert Roe', 'age' => null],
                               ]);

    DB::table('users')->incrementEach([
                                        'age' => 1,
                                        'note' => 2,
                                      ]);

    $user = DB::table('users')->where('name', 'John Doe')->first();
    expect($user->age)->toBe(31);
    expect($user->note)->toBe(7);

    $user = DB::table('users')->where('name', 'Jane Doe')->first();
    expect($user->age)->toBe(11);
    expect($user->note)->toBe(8);

    $user = DB::table('users')->where('name', 'Robert Roe')->first();
    expect($user->age)->toBe(1);
    expect($user->note)->toBe(2);

    DB::table('users')->where('name', 'Jane Doe')->incrementEach([
                                                                   'age' => 1,
                                                                   'note' => 2,
                                                                 ], ['extra' => 'foo']);

    $user = DB::table('users')->where('name', 'Jane Doe')->first();
    expect($user->age)->toBe(12);
    expect($user->note)->toBe(10);
    expect($user->extra)->toBe('foo');

    $user = DB::table('users')->where('name', 'John Doe')->first();
    expect($user->age)->toBe(31);
    expect($user->note)->toBe(7);
    expect($user)->not->toHaveProperty('extra');

    DB::table('users')->decrementEach([
                                        'age' => 1,
                                        'note' => 2,
                                      ], ['extra' => 'foo']);

    $user = DB::table('users')->where('name', 'John Doe')->first();
    expect($user->age)->toBe(30);
    expect($user->note)->toBe(5);
    expect($user->extra)->toBe('foo');
  });
