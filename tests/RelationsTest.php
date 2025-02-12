<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use PDPhilip\Elasticsearch\Tests\Models\Address;
use PDPhilip\Elasticsearch\Tests\Models\Book;
use PDPhilip\Elasticsearch\Tests\Models\Client;
use PDPhilip\Elasticsearch\Tests\Models\Group;
use PDPhilip\Elasticsearch\Tests\Models\Item;
use PDPhilip\Elasticsearch\Tests\Models\Label;
use PDPhilip\Elasticsearch\Tests\Models\Photo;
use PDPhilip\Elasticsearch\Tests\Models\Role;
use PDPhilip\Elasticsearch\Tests\Models\Soft;
use PDPhilip\Elasticsearch\Tests\Models\User;

beforeEach(function () {
    Photo::executeSchema();
    User::executeSchema();
    Label::executeSchema();
    Book::executeSchema();
    Item::executeSchema();
    Soft::executeSchema();
    Role::executeSchema();
    Client::executeSchema();
    Address::executeSchema();
});

it('tests has many', function () {
    $author = User::create(['name' => 'George R. R. Martin']);
    Book::create(['title' => 'A Game of Thrones', 'author_id' => $author->id]);
    Book::create(['title' => 'A Clash of Kings', 'author_id' => $author->id]);

    $books = $author->books;
    expect($books)->toHaveCount(2);

    $user = User::create(['name' => 'John Doe']);
    Item::create(['type' => 'knife', 'user_id' => $user->id]);
    Item::create(['type' => 'shield', 'user_id' => $user->id]);
    Item::create(['type' => 'sword', 'user_id' => $user->id]);
    Item::create(['type' => 'bag', 'user_id' => null]);

    $items = $user->items;
    expect($items)->toHaveCount(3);
});

it('tests has many with trashed', function () {
    $user = User::create(['name' => 'George R. R. Martin']);
    $first = Soft::create(['title' => 'A Game of Thrones', 'user_id' => $user->id]);
    $second = Soft::create(['title' => 'The Witcher', 'user_id' => $user->id]);

    expect($first->deleted_at)->toBeNull()
        ->and($first->user->id)->toBe($user->id)
        ->and($user->softs->pluck('id')->toArray())->toBe([
            $first->id,
            $second->id,
        ]);

    $first->delete();
    $user->refresh();

    expect($first->deleted_at)->not->toBeNull()
        ->and($user->softs->pluck('id')->toArray())->toBe([$second->id])
        ->and($user->softsWithTrashed->pluck('id')->toArray())->toContain($first->id, $second->id);
});

it('tests belongs to', function () {
    $user = User::create(['name' => 'George R. R. Martin']);
    Book::create(['title' => 'A Game of Thrones', 'author_id' => $user->id]);
    $book = Book::create(['title' => 'A Clash of Kings', 'author_id' => $user->id]);

    $author = $book->author;
    expect($author->name)->toBe('George R. R. Martin');

    $user = User::create(['name' => 'John Doe']);
    $item = Item::create(['type' => 'sword', 'user_id' => $user->id]);

    $owner = $item->user;
    expect($owner->name)->toBe('John Doe');

    $book = Book::create(['title' => 'A Clash of Kings']);
    expect($book->author)->toBeNull();
});

it('tests has one', function () {
    $user = User::create(['name' => 'John Doe']);
    Role::create(['type' => 'admin', 'user_id' => $user->id]);

    $role = $user->role;
    expect($role->type)->toBe('admin')
        ->and($role->user_id)->toBe($user->id);

    $user = User::create(['name' => 'Jane Doe']);
    $role = new Role(['type' => 'user']);
    $user->role()->save($role);

    $role = $user->role;
    expect($role->type)->toBe('user')
        ->and($role->user_id)->toBe($user->id);

    $user = User::where('name', 'Jane Doe')->first();
    $role = $user->role;
    expect($role->type)->toBe('user')
        ->and($role->user_id)->toBe($user->id);
});

it('tests with belongs to', function () {
    $user = User::create(['name' => 'John Doe']);
    Item::create(['type' => 'knife', 'user_id' => $user->id]);
    Item::create(['type' => 'shield', 'user_id' => $user->id]);
    Item::create(['type' => 'sword', 'user_id' => $user->id]);
    Item::create(['type' => 'bag', 'user_id' => null]);

    $items = Item::with('user')->orderBy('user_id', 'desc')->get();

    $user = $items[0]->getRelation('user');
    expect($user)->toBeInstanceOf(User::class)
        ->and($user->name)->toBe('John Doe')
        ->and($items[0]->getRelations())->toHaveCount(1)
        ->and($items[3]->getRelation('user'))->toBeNull();
});

it('tests with has many', function () {
    $user = User::create(['name' => 'John Doe']);
    Item::create(['type' => 'knife', 'user_id' => $user->id]);
    Item::create(['type' => 'shield', 'user_id' => $user->id]);
    Item::create(['type' => 'sword', 'user_id' => $user->id]);
    Item::create(['type' => 'bag', 'user_id' => null]);

    $user = User::with('items')->find($user->id);

    $items = $user->items;
    expect($items)->toHaveCount(3)
        ->and($items[0])->toBeInstanceOf(Item::class);
});

it('tests with has one', function () {
    $user = User::create(['name' => 'John Doe']);
    Role::create(['type' => 'admin', 'user_id' => $user->id]);
    Role::create(['type' => 'guest', 'user_id' => $user->id]);

    $user = User::with('role')->find($user->id);

    $role = $user->role;
    expect($role)->toBeInstanceOf(Role::class)
        ->and($role->type)->toBe('admin');
});

it('tests easy relation', function () {
    // Has Many
    $user = User::create(['name' => 'John Doe']);
    $item = Item::create(['type' => 'knife']);
    $user->items()->save($item);

    $user = User::find($user->id);
    $items = $user->items;
    expect($items)->toHaveCount(1)
        ->and($items[0])->toBeInstanceOf(Item::class)
        ->and($items[0]->user_id)->toBe($user->id);

    // Has One
    $user = User::create(['name' => 'John Doe']);
    $role = Role::create(['type' => 'admin']);
    $user->role()->save($role);

    $user = User::find($user->id);
    $role = $user->role;
    expect($role)->toBeInstanceOf(Role::class)
        ->and($role->type)->toBe('admin')
        ->and($role->user_id)->toBe($user->id);
});

it('tests belongs to many', function () {
    $user = User::create(['name' => 'John Doe']);

    // Add 2 clients
    $user->clients()->save(new Client(['name' => 'Pork Pies Ltd.']));
    $user->clients()->create(['name' => 'Buffet Bar Inc.']);

    // Refetch
    $user = User::with('clients')->find($user->id);
    $client = Client::with('users')->first();

    expect($client->getAttributes())->toHaveKey('user_ids')
        ->and($user->getAttributes())->toHaveKey('client_ids');

    $clients = $user->clients;
    $users = $client->users;

    expect($users)->toBeInstanceOf(Collection::class)
        ->and($clients)->toBeInstanceOf(Collection::class)
        ->and($clients[0])->toBeInstanceOf(Client::class)
        ->and($users[0])->toBeInstanceOf(User::class)
        ->and($user->clients)->toHaveCount(2)
        ->and($client->users)->toHaveCount(1);

    // Create a new user for an existing client
    $user = $client->users()->create(['name' => 'Jane Doe']);
    expect($user->clients)->toBeInstanceOf(Collection::class)
        ->and($user->clients->first())->toBeInstanceOf(Client::class)
        ->and($user->clients)->toHaveCount(1);

    // Get user and unattached client
    $user = User::where('name', '=', 'Jane Doe')->first();
    $client = Client::where('name', '=', 'Buffet Bar Inc.')->first();

    expect($client)->toBeInstanceOf(Client::class)
        ->and($user)->toBeInstanceOf(User::class)
        ->and($user->client_ids)->not->toContain($client->id)
        ->and($client->user_ids)->not->toContain($user->id)
        ->and($user->clients)->toHaveCount(1)
        ->and($client->users)->toHaveCount(1);

    // Attach the client to the user
    $user->clients()->attach($client);

    // Refetch user and client
    $user = User::where('name', '=', 'Jane Doe')->first();
    $client = Client::where('name', '=', 'Buffet Bar Inc.')->first();

    expect($user->client_ids)->toContain($client->id)
        ->and($client->user_ids)->toContain($user->id)
        ->and($user->clients)->toHaveCount(2)
        ->and($client->users)->toHaveCount(2);

    // Detach clients from user
    $user->clients()->sync([]);

    // Refetch user and client
    $user = User::where('name', '=', 'Jane Doe')->first();
    $client = Client::where('name', '=', 'Buffet Bar Inc.')->first();

    expect($user->client_ids)->not->toContain($client->id)
        ->and($client->user_ids)->not->toContain($user->id)
        ->and($user->clients)->toHaveCount(0)
        ->and($client->users)->toHaveCount(1);
});

it('tests belongs to many attaches existing models', function () {
    $user = User::create(['name' => 'John Doe', 'client_ids' => ['1234523']]);

    $clients = [
        Client::create(['name' => 'Pork Pies Ltd.'])->id,
        Client::create(['name' => 'Buffet Bar Inc.'])->id,
    ];

    $moreClients = [
        Client::create(['name' => 'synced Boloni Ltd.'])->id,
        Client::create(['name' => 'synced Meatballs Inc.'])->id,
    ];

    $user->clients()->sync($clients);
    $user = User::with('clients')->find($user->id);

    expect($user->client_ids)->not->toContain('1234523')
        ->and($user->clients)->toHaveCount(2);

    $user->clients()->sync($moreClients);
    $user = User::with('clients')->find($user->id);

    expect($user->clients)->toHaveCount(2)
        ->and($user->clients[0]->name)->toStartWith('synced')
        ->and($user->clients[1]->name)->toStartWith('synced');
});

it('tests belongs to many sync', function () {
    $user = User::create(['name' => 'Hans Thomas']);
    $client1 = Client::create(['name' => 'Pork Pies Ltd.']);
    $client2 = Client::create(['name' => 'Buffet Bar Inc.']);

    $user->clients()->sync([$client1->id, $client2->id]);
    expect($user->clients)->toHaveCount(2);

    $user->clients()->sync([$client1->id]);
    $user->load('clients');
    expect($user->clients)->toHaveCount(1)
        ->and($user->clients->first()->is($client1))->toBeTrue();

    $user->clients()->sync($client2);
    $user->load('clients');
    expect($user->clients)->toHaveCount(1)
        ->and($user->clients->first()->is($client2))->toBeTrue();
});

it('tests belongs to many attach array', function () {
    $user = User::create(['name' => 'John Doe']);
    $client1 = Client::create(['name' => 'Test 1'])->id;
    $client2 = Client::create(['name' => 'Test 2'])->id;

    $user->clients()->attach([$client1, $client2]);
    expect($user->clients)->toHaveCount(2);
});

it('tests belongs to many attach eloquent collection', function () {
    User::create(['name' => 'John Doe']);
    $client1 = Client::create(['name' => 'Test 1']);
    $client2 = Client::create(['name' => 'Test 2']);
    $collection = new Collection([$client1, $client2]);

    $user = User::where('name', '=', 'John Doe')->first();
    $user->clients()->attach($collection);
    expect($user->clients)->toHaveCount(2);
});

it('tests belongs to many sync already present', function () {
    $user = User::create(['name' => 'John Doe']);
    $client1 = Client::create(['name' => 'Test 1'])->id;
    $client2 = Client::create(['name' => 'Test 2'])->id;

    $user->clients()->sync([$client1, $client2]);
    expect($user->clients)->toHaveCount(2);

    $user = User::where('name', '=', 'John Doe')->first();
    $user->clients()->sync([$client1]);
    expect($user->clients)->toHaveCount(1);

    $user = User::where('name', '=', 'John Doe')->first()->toArray();
    expect($user['client_ids'])->toHaveCount(1);
});

it('tests belongs to many custom', function () {
    $user = User::create(['name' => 'John Doe']);
    $group = $user->groups()->create(['name' => 'Admins']);

    // Refetch
    $user = User::find($user->id);
    $group = Group::find($group->id);

    // Check for custom relation attributes
    expect($group->getAttributes())->toHaveKey('users')
        ->and($user->getAttributes())->toHaveKey('groups')
        ->and($user->groups->pluck('id')->toArray())->toContain($group->id)
        ->and($group->users->pluck('id')->toArray())->toContain($user->id)
        ->and($user->groups()->first()->id)->toBe($group->id)
        ->and($group->users()->first()->id)->toBe($user->id);

    // Assert they are attached
})->todo();

it('tests morph', function () {
    $user = User::create(['name' => 'John Doe']);
    $client = Client::create(['name' => 'Jane Doe']);

    $photo = Photo::create(['url' => 'http://graph.facebook.com/john.doe/picture']);
    $photo = $user->photos()->save($photo);

    expect($user->photos->count())->toBe(1)
        ->and($user->photos->first()->id)->toBe($photo->id);

    $user = User::find($user->id);
    expect($user->photos->count())->toBe(1)
        ->and($user->photos->first()->id)->toBe($photo->id);

    $photo = Photo::create(['url' => 'http://graph.facebook.com/jane.doe/picture']);
    $client->photo()->save($photo);

    expect($client->photo)->not->toBeNull()
        ->and($client->photo->id)->toBe($photo->id);

    $client = Client::find($client->id);
    expect($client->photo)->not->toBeNull()
        ->and($client->photo->id)->toBe($photo->id);

    $photo = Photo::first();
    expect($photo->hasImage->name)->toBe($user->name);

    // eager load
    $user = User::with('photos')->find($user->id);
    // TODO: Figure out why getRelations is not working
    //    $relations = $user->getRelations();
    //    dd($relations);
    //    expect($relations)->toHaveKey('photos');
    expect($user['photos']->count())->toBe(1);

    // inverse eager load
    $photos = Photo::with('hasImage')->get();
    $relations = $photos[0]->getRelations();
    expect($relations)->toHaveKey('hasImage')
        ->and($photos[0]->hasImage)->toBeInstanceOf(User::class);

    $relations = $photos[1]->getRelations();
    expect($relations)->toHaveKey('hasImage')
        ->and($photos[1]->hasImage)->toBeInstanceOf(Client::class);

    // inverse relationship
    $photo = Photo::query()->create(['url' => 'https://graph.facebook.com/hans.thomas/picture']);
    $client = Client::create(['name' => 'Hans Thomas']);
    $photo->hasImage()->associate($client)->save();

    expect($photo->hasImage()->get())->toHaveCount(1)
        ->and($photo->hasImage)->toBeInstanceOf(Client::class)
        ->and($photo->hasImage->id)->toBe($client->id);

    // inverse with custom ownerKey
    $photo = Photo::query()->create(['url' => 'https://graph.facebook.com/young.gerald/picture']);
    $client = Client::create(['cclient_id' => (string) 'abc_123', 'name' => 'Young Gerald']);
    $photo->hasImageWithCustomOwnerKey()->associate($client)->save();

    expect($photo->hasImageWithCustomOwnerKey()->get())->toHaveCount(1)
        ->and($photo->hasImageWithCustomOwnerKey)->toBeInstanceOf(Client::class)
        ->and($photo->has_image_with_custom_owner_key_id)->toBe($client->cclient_id)
        ->and($photo->hasImageWithCustomOwnerKey->id)->toBe($client->id);

    // inverse eager load with custom ownerKey
    $photos = Photo::with('hasImageWithCustomOwnerKey')->get();
    $check = $photos->last();
    $relations = $check->getRelations();
    expect($relations)->toHaveKey('hasImageWithCustomOwnerKey')
        ->and($check->hasImageWithCustomOwnerKey)->toBeInstanceOf(Client::class);
});

it('tests morph to many', function () {
    $user = User::query()->create(['name' => 'Young Gerald']);
    $client = Client::query()->create(['name' => 'Hans Thomas']);
    $label = Label::query()->create(['name' => 'Had the world in my palms, I gave it to you']);

    $user->labels()->attach($label);
    $client->labels()->attach($label);

    expect($user->labels->count())->toBe(1)
        ->and($user->labels->pluck('id'))->toContain($label->id)
        ->and($client->labels->count())->toBe(1)
        ->and($client->labels->pluck('id'))->toContain($label->id);

});

it('tests morph to many attach eloquent collection', function () {
    $client = Client::query()->create(['name' => 'Young Gerald']);
    $label1 = Label::query()->create(['name' => "Make no mistake, it's the life that I was chosen for"]);
    $label2 = Label::query()->create(['name' => 'All I prayed for was an open door']);

    $client->labels()->attach(new Collection([$label1, $label2]));

    expect($client->labels->count())->toBe(2)
        ->and($client->labels->pluck('id'))->toContain($label1->id)
        ->and($client->labels->pluck('id'))->toContain($label2->id);
});

it('tests morph to many attach multiple ids', function () {
    $client = Client::query()->create(['name' => 'Young Gerald']);
    $label1 = Label::query()->create(['name' => 'stayed solid i never fled']);
    $label2 = Label::query()->create(['name' => "I've got a lane and I'm in gear"]);

    $client->labels()->attach([$label1->id, $label2->id]);

    expect($client->labels->count())->toBe(2)
        ->and($client->labels->pluck('id'))->toContain($label1->id)
        ->and($client->labels->pluck('id'))->toContain($label2->id);
});

it('tests morph to many detaching', function () {
    $client = Client::query()->create(['name' => 'Marshall Mathers']);
    $label1 = Label::query()->create(['name' => "I'll never love again"]);
    $label2 = Label::query()->create(['name' => 'The way I loved you']);

    $client->labels()->attach([$label1->id, $label2->id]);

    expect($client->labels->count())->toBe(2);

    $client->labels()->detach($label1);
    $client->refresh();

    expect($client->labels->count())->toBe(1)
        ->and($client->labels->pluck('id'))->toContain($label2->id);
});

it('tests morph to many detaching multiple ids', function () {
    $client = Client::query()->create(['name' => 'Young Gerald']);
    $label1 = Label::query()->create(['name' => "I make what I wanna make, but I won't make everyone happy"]);
    $label2 = Label::query()->create(['name' => "My skin's thick, but I'm not bulletproof"]);
    $label3 = Label::query()->create(['name' => 'All I can be is myself, go, and tell the truth']);

    $client->labels()->attach([$label1->id, $label2->id, $label3->id]);

    expect($client->labels->count())->toBe(3);

    $client->labels()->detach([$label1->id, $label2->id]);
    $client->refresh();

    expect($client->labels->count())->toBe(1)
        ->and($client->labels->pluck('id'))->toContain($label3->id);
});

it('tests morph to many syncing', function () {
    $user = User::query()->create(['name' => 'Young Gerald']);
    $client = Client::query()->create(['name' => 'Hans Thomas']);
    $label = Label::query()->create(['name' => "Lesson learned, we weren't the perfect match"]);
    $label2 = Label::query()->create(['name' => 'Future ref, not keeping personal and work attached']);

    $user->labels()->sync($label);
    $client->labels()->sync($label);
    $client->labels()->sync($label2, false);

    expect($user->labels->count())->toBe(1)
        ->and($user->labels->pluck('id'))->toContain($label->id)
        ->and($user->labels->pluck('id'))->not->toContain($label2->id)
        ->and($client->labels->count())->toBe(2)
        ->and($client->labels->pluck('id'))->toContain($label->id)
        ->and($client->labels->pluck('id'))->toContain($label2->id);

});

it('tests morph to many syncing eloquent collection', function () {
    $client = Client::query()->create(['name' => 'Young Gerald']);
    $label = Label::query()->create(['name' => 'Why the ones who love me most, the people I push away?']);
    $label2 = Label::query()->create(['name' => 'Look in a mirror, this is you']);

    $client->labels()->sync(new Collection([$label, $label2]));

    expect($client->labels->count())->toBe(2)
        ->and($client->labels->pluck('id'))->toContain($label->id)
        ->and($client->labels->pluck('id'))->toContain($label2->id);
});

it('tests morph to many syncing multiple ids', function () {
    $client = Client::query()->create(['name' => 'Young Gerald']);
    $label = Label::query()->create(['name' => 'They all talk about karma, how it slowly comes']);
    $label2 = Label::query()->create(['name' => "But life is short, enjoy it while you're young"]);

    $client->labels()->sync([$label->id, $label2->id]);

    expect($client->labels->count())->toBe(2)
        ->and($client->labels->pluck('id'))->toContain($label->id)
        ->and($client->labels->pluck('id'))->toContain($label2->id);
});

it('tests morph to many syncing with custom keys', function () {
    $client = Client::query()->create(['cclient_id' => (string) (Str::uuid()), 'name' => 'Young Gerald']);
    $label = Label::query()->create(['clabel_id' => (string) (Str::uuid()), 'name' => "Why do people do things that be bad for 'em?"]);
    $label2 = Label::query()->create(['clabel_id' => (string) (Str::uuid()), 'name' => "Say we done with these things, then we ask for 'em"]);

    $client->labelsWithCustomKeys()->sync([$label->clabel_id, $label2->clabel_id]);

    expect($client->labelsWithCustomKeys->count())->toBe(2)
        ->and($client->labelsWithCustomKeys->pluck('id'))->toContain($label->id)
        ->and($client->labelsWithCustomKeys->pluck('id'))->toContain($label2->id);

    $client->labelsWithCustomKeys()->sync($label);
    $client->load('labelsWithCustomKeys');

    expect($client->labelsWithCustomKeys->count())->toBe(1)
        ->and($client->labelsWithCustomKeys->pluck('id'))->toContain($label->id)
        ->and($client->labelsWithCustomKeys->pluck('id'))->not->toContain($label2->id);
});

it('tests morph to many load and refreshing', function () {
    $user = User::query()->create(['name' => 'The Pretty Reckless']);
    $client = Client::query()->create(['name' => 'Young Gerald']);
    $label = Label::query()->create(['name' => 'The greatest gift is knowledge itself']);
    $label2 = Label::query()->create(['name' => "I made it here all by my lonely, no askin' for help"]);

    $client->labels()->sync([$label->id, $label2->id]);
    $client->users()->sync($user);

    expect($client->labels->count())->toBe(2);

    $client->load('labels');
    expect($client->labels->count())->toBe(2);

    $client->refresh();
    expect($client->labels->count())->toBe(2);

    $check = Client::query()->find($client->id);
    expect($check->labels->count())->toBe(2);

    $check = Client::query()->with('labels')->find($client->id);
    expect($check->labels->count())->toBe(2);
});

it('tests morph to many has query', function () {
    $client = Client::query()->create(['name' => 'Ashley']);
    $client2 = Client::query()->create(['name' => 'Halsey']);
    $client3 = Client::query()->create(['name' => 'John Doe 2']);

    $label = Label::query()->create(['name' => "I've been digging myself down deeper"]);
    $label2 = Label::query()->create(['name' => "I won't stop 'til I get where you are"]);

    $client->labels()->sync([$label->id, $label2->id]);
    $client2->labels()->sync($label);

    expect($client->labels->count())->toBe(2)
        ->and($client2->labels->count())->toBe(1);

    $check = Client::query()->has('labels')->get();
    expect($check)->toHaveCount(2);

    $check = Client::query()->has('labels', '>', 1)->get();
    expect($check)->toHaveCount(1)
        ->and($check->pluck('id'))->toContain($client->id);

    $check = Client::query()->has('labels', '<', 2)->get();
    expect($check)->toHaveCount(2)
        ->and($check->pluck('id'))->toContain($client2->id)
        ->and($check->pluck('id'))->toContain($client3->id);
});

it('tests morphed by many', function () {
    $user = User::query()->create(['name' => 'Young Gerald']);
    $client = Client::query()->create(['name' => 'Hans Thomas']);
    $extra = Client::query()->create(['name' => 'John Doe']);

    $label = Label::query()->create(['name' => 'Never finished, tryna search for more']);

    $label->users()->attach($user);
    $label->clients()->attach($client);

    expect($label->users->count())->toBe(1)
        ->and($label->users->pluck('id'))->toContain($user->id)
        ->and($label->clients->count())->toBe(1)
        ->and($label->clients->pluck('id'))->toContain($client->id);

});

it('tests morphed by many attach eloquent collection', function () {
    $client1 = Client::query()->create(['name' => 'Young Gerald']);
    $client2 = Client::query()->create(['name' => 'Hans Thomas']);
    $extra = Client::query()->create(['name' => 'John Doe']);

    $label = Label::query()->create(['name' => 'They want me to architect Rome, in a day']);

    $label->clients()->attach(new Collection([$client1, $client2]));

    expect($label->clients->count())->toBe(2)
        ->and($label->clients->pluck('id'))->toContain($client1->id)
        ->and($label->clients->pluck('id'))->toContain($client2->id);

    $client1->refresh();
    expect($client1->labels->count())->toBe(1);
});

it('tests morphed by many attach multiple ids', function () {
    $client1 = Client::query()->create(['name' => 'Austin Richard Post']);
    $client2 = Client::query()->create(['name' => 'Hans Thomas']);
    $extra = Client::query()->create(['name' => 'John Doe']);

    $label = Label::query()->create(['name' => 'Always in the game and never played by the rules']);

    $label->clients()->attach([$client1->id, $client2->id]);

    expect($label->clients->count())->toBe(2)
        ->and($label->clients->pluck('id'))->toContain($client1->id)
        ->and($label->clients->pluck('id'))->toContain($client2->id);

    $client1->refresh();
    expect($client1->labels->count())->toBe(1);
});

it('tests morphed by many detaching', function () {
    $client1 = Client::query()->create(['name' => 'Austin Richard Post']);
    $client2 = Client::query()->create(['name' => 'Hans Thomas']);
    $extra = Client::query()->create(['name' => 'John Doe']);

    $label = Label::query()->create(['name' => 'Seasons change and our love went cold']);

    $label->clients()->attach([$client1->id, $client2->id]);

    expect($label->clients->count())->toBe(2);

    $label->clients()->detach($client1->id);
    $label->refresh();

    expect($label->clients->count())->toBe(1)
        ->and($label->clients->pluck('id'))->toContain($client2->id);
});

it('tests morphed by many detaching multiple ids', function () {
    $client1 = Client::query()->create(['name' => 'Austin Richard Post']);
    $client2 = Client::query()->create(['name' => 'Hans Thomas']);
    $client3 = Client::query()->create(['name' => 'John Doe']);

    $label = Label::query()->create(['name' => "Run away, but we're running in circles"]);

    $label->clients()->attach([$client1->id, $client2->id, $client3->id]);

    expect($label->clients->count())->toBe(3);

    $label->clients()->detach([$client1->id, $client2->id]);
    $label->load('clients');

    expect($label->clients->count())->toBe(1)
        ->and($label->clients->pluck('id'))->toContain($client3->id);
});

it('tests morphed by many syncing', function () {
    $client1 = Client::query()->create(['name' => 'Austin Richard Post']);
    $client2 = Client::query()->create(['name' => 'Hans Thomas']);
    $client3 = Client::query()->create(['name' => 'John Doe']);

    $label = Label::query()->create(['name' => "Was scared of losin' somethin' that we never found"]);

    $label->clients()->sync($client1);
    $label->clients()->sync($client2, false);
    $label->clients()->sync($client3, false);

    expect($label->clients->count())->toBe(3)
        ->and($label->clients->pluck('id'))->toContain($client1->id)
        ->and($label->clients->pluck('id'))->toContain($client2->id)
        ->and($label->clients->pluck('id'))->toContain($client3->id);
});

it('tests morphed by many syncing eloquent collection', function () {
    $client1 = Client::query()->create(['name' => 'Austin Richard Post']);
    $client2 = Client::query()->create(['name' => 'Hans Thomas']);
    $extra = Client::query()->create(['name' => 'John Doe']);

    $label = Label::query()->create(['name' => "I'm goin' hard 'til I'm gone. Can you feel it?"]);

    $label->clients()->sync(new Collection([$client1, $client2]));

    expect($label->clients->count())->toBe(2)
        ->and($label->clients->pluck('id'))->toContain($client1->id)
        ->and($label->clients->pluck('id'))->toContain($client2->id)
        ->and($label->clients->pluck('id'))->not->toContain($extra->id);
});

it('tests morphed by many syncing multiple ids', function () {
    $client1 = Client::query()->create(['name' => 'Dorothy']);
    $client2 = Client::query()->create(['name' => 'Hans Thomas']);
    $extra = Client::query()->create(['name' => 'John Doe']);

    $label = Label::query()->create(['name' => "Love ain't patient, it's not kind. true love waits to rob you blind"]);

    $label->clients()->sync([$client1->id, $client2->id]);

    expect($label->clients->count())->toBe(2)
        ->and($label->clients->pluck('id'))->toContain($client1->id)
        ->and($label->clients->pluck('id'))->toContain($client2->id)
        ->and($label->clients->pluck('id'))->not->toContain($extra->id);
});

it('tests morphed by many syncing with custom keys', function () {
    $client1 = Client::query()->create(['cclient_id' => (string) ((Str::uuid())), 'name' => 'Young Gerald']);
    $client2 = Client::query()->create(['cclient_id' => (string) ((Str::uuid())), 'name' => 'Hans Thomas']);
    $client3 = Client::query()->create(['cclient_id' => (string) ((Str::uuid())), 'name' => 'John Doe']);

    $label = Label::query()->create(['clabel_id' => (string) ((Str::uuid())), 'name' => "I'm in my own lane, so what do I have to hurry for?"]);

    $label->clientsWithCustomKeys()->sync([$client1->cclient_id, $client2->cclient_id]);

    expect($label->clientsWithCustomKeys->count())->toBe(2)
        ->and($label->clientsWithCustomKeys->pluck('id'))->toContain($client1->id)
        ->and($label->clientsWithCustomKeys->pluck('id'))->toContain($client2->id)
        ->and($label->clientsWithCustomKeys->pluck('id'))->not->toContain($client3->id);

    $label->clientsWithCustomKeys()->sync($client3);
    $label->load('clientsWithCustomKeys');

    expect($label->clientsWithCustomKeys->count())->toBe(1)
        ->and($label->clientsWithCustomKeys->pluck('id'))->not->toContain($client1->id)
        ->and($label->clientsWithCustomKeys->pluck('id'))->not->toContain($client2->id)
        ->and($label->clientsWithCustomKeys->pluck('id'))->toContain($client3->id);
});

it('tests morphed by many load and refreshing', function () {
    $user = User::query()->create(['name' => 'Abel Tesfaye']);
    $client1 = Client::query()->create(['name' => 'Young Gerald']);
    $client2 = Client::query()->create(['name' => 'Hans Thomas']);
    $client3 = Client::query()->create(['name' => 'John Doe']);

    $label = Label::query()->create(['name' => "but don't think I don't think about you just cause I ain't spoken about you"]);

    $label->clients()->sync(new Collection([$client1, $client2, $client3]));
    $label->users()->sync($user);

    expect($label->clients->count())->toBe(3);

    $label->load('clients');
    expect($label->clients->count())->toBe(3);

    $label->refresh();
    expect($label->clients->count())->toBe(3);

    $check = Label::query()->find($label->id);
    expect($check->clients->count())->toBe(3);

    $check = Label::query()->with('clients')->find($label->id);
    expect($check->clients->count())->toBe(3);
});

it('tests morphed by many has query', function () {
    $user = User::query()->create(['name' => 'Austin Richard Post']);
    $client1 = Client::query()->create(['name' => 'Young Gerald']);
    $client2 = Client::query()->create(['name' => 'John Doe']);

    $label = Label::query()->create(['name' => "My star's back shining bright, I just polished it"]);
    $label2 = Label::query()->create(['name' => "Somethin' in my spirit woke back up like I just sat up"]);
    $label3 = Label::query()->create(['name' => 'How can I beam when you blocking my light?']);

    $label->clients()->sync(new Collection([$client1, $client2]));
    $label2->clients()->sync($client1);
    $label3->users()->sync($user);

    expect($label->clients->count())->toBe(2);

    $check = Label::query()->has('clients')->get();
    expect($check)->toHaveCount(2)
        ->and($check->pluck('id'))->toContain($label->id)
        ->and($check->pluck('id'))->toContain($label2->id);

    $check = Label::query()->has('users')->get();
    expect($check)->toHaveCount(1)
        ->and($check->pluck('id'))->toContain($label3->id);

    $check = Label::query()->has('clients', '>', 1)->get();
    expect($check)->toHaveCount(1)
        ->and($check->pluck('id'))->toContain($label->id);
});

it('tests has many has', function () {
    $author1 = User::create(['name' => 'George R. R. Martin']);
    $author1->books()->create(['title' => 'A Game of Thrones', 'rating' => 5]);
    $author1->books()->create(['title' => 'A Clash of Kings', 'rating' => 5]);
    $author2 = User::create(['name' => 'John Doe']);
    $author2->books()->create(['title' => 'My book', 'rating' => 2]);
    User::create(['name' => 'Anonymous author']);
    Book::create(['title' => 'Anonymous book', 'rating' => 1]);

    $authors = User::has('books')->get();
    expect($authors)->toHaveCount(2);
    expect($authors[0]->name)->toBe('George R. R. Martin');
    expect($authors[1]->name)->toBe('John Doe');

    $authors = User::has('books', '>', 1)->get();
    expect($authors)->toHaveCount(1);

    $authors = User::has('books', '<', 5)->get();
    expect($authors)->toHaveCount(3);

    $authors = User::has('books', '>=', 2)->get();
    expect($authors)->toHaveCount(1);

    $authors = User::has('books', '<=', 1)->get();
    expect($authors)->toHaveCount(2);

    $authors = User::has('books', '=', 2)->get();
    expect($authors)->toHaveCount(1);

    $authors = User::has('books', '!=', 2)->get();
    expect($authors)->toHaveCount(2);

    $authors = User::has('books', '=', 0)->get();
    expect($authors)->toHaveCount(1);

    $authors = User::has('books', '!=', 0)->get();
    expect($authors)->toHaveCount(2);

    $authors = User::whereHas('books', function ($query) {
        $query->where('rating', 5);
    })->get();
    expect($authors)->toHaveCount(1);

    $authors = User::whereHas('books', function ($query) {
        $query->where('rating', '<', 5);
    })->get();
    expect($authors)->toHaveCount(1);
})->todo();

it('tests has one has', function () {
    $user1 = User::create(['name' => 'John Doe']);
    $user1->role()->create(['title' => 'admin']);
    $user2 = User::create(['name' => 'Jane Doe']);
    $user2->role()->create(['title' => 'reseller']);
    User::create(['name' => 'Mark Moe']);
    Role::create(['title' => 'Customer']);

    $users = User::has('role')->get();
    expect($users)->toHaveCount(2);
    expect($users[0]->name)->toBe('John Doe');
    expect($users[1]->name)->toBe('Jane Doe');

    $users = User::has('role', '=', 0)->get();
    expect($users)->toHaveCount(1);

    $users = User::has('role', '!=', 0)->get();
    expect($users)->toHaveCount(2);
})->todo();

it('tests nested keys', function () {
    $client = Client::create([
        'data' => [
            'client_id' => '35298',
            'name' => 'John Doe',
        ],
    ]);

    $client->addresses()->create([
        'data' => [
            'address_id' => '1432',
            'city' => 'Paris',
        ],
    ]);

    $client = Client::where('data.client_id', 35298)->first();
    expect($client->addresses->count())->toBe(1)
        ->and($client->addresses->first()->data['city'])->toBe('Paris');

    $client = Client::with('addresses')->first();
    expect($client->addresses->first()->data['city'])->toBe('Paris');
});

it('tests double save one to many', function () {
    $author = User::create(['name' => 'George R. R. Martin']);
    $book = Book::create(['title' => 'A Game of Thrones']);

    $author->books()->save($book);
    $author->books()->save($book);
    $author->save();

    expect($author->books()->count())->toBe(1)
        ->and($book->author_id)->toBe($author->id);

    $author = User::where('name', 'George R. R. Martin')->first();
    $book = Book::where('title', 'A Game of Thrones')->first();

    expect($author->books()->count())->toBe(1)
        ->and($book->author_id)->toBe($author->id);

    $author->books()->save($book);
    $author->books()->save($book);
    $author->save();

    expect($author->books()->count())->toBe(1)
        ->and($book->author_id)->toBe($author->id);
});

it('tests double save many to many', function () {
    $user = User::create(['name' => 'John Doe']);
    $client = Client::create(['name' => 'Admins']);

    $user->clients()->save($client);
    $user->clients()->save($client);
    $user->save();

    expect($user->clients()->count())->toBe(1)
        ->and($client->user_ids)->toContain($user->id)
        ->and($user->client_ids)->toContain($client->id);

    $user = User::where('name', 'John Doe')->first();
    $client = Client::where('name', 'Admins')->first();

    expect($user->clients()->count())->toBe(1)
        ->and($client->user_ids)->toContain($user->id)
        ->and($user->client_ids)->toContain($client->id);

    $user->clients()->save($client);
    $user->clients()->save($client);
    $user->save();

    expect($user->clients()->count())->toBe(1)
        ->and($client->user_ids)->toContain($user->id)
        ->and($user->client_ids)->toContain($client->id);
});

it('tests where belongs to', function () {
    $user = User::create(['name' => 'John Doe']);
    Item::create(['user_id' => $user->id]);
    Item::create(['user_id' => $user->id]);
    Item::create(['user_id' => $user->id]);
    Item::create(['user_id' => null]);

    $items = Item::whereBelongsTo($user)->get();
    expect($items)->toHaveCount(3);
});
