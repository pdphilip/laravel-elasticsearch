<?php

declare(strict_types=1);

use Illuminate\Database\SQLiteConnection;
use PDPhilip\Elasticsearch\Tests\Models\Book;
use PDPhilip\Elasticsearch\Tests\Models\Experience;
use PDPhilip\Elasticsearch\Tests\Models\Label;
use PDPhilip\Elasticsearch\Tests\Models\Role;
use PDPhilip\Elasticsearch\Tests\Models\Skill;
use PDPhilip\Elasticsearch\Tests\Models\SqlBook;
use PDPhilip\Elasticsearch\Tests\Models\SqlRole;
use PDPhilip\Elasticsearch\Tests\Models\SqlUser;
use PDPhilip\Elasticsearch\Tests\Models\User;

beforeEach(function () {
    SqlUser::executeSchema();
    SqlBook::executeSchema();
    SqlRole::executeSchema();

    Skill::executeSchema();
    Label::executeSchema();
    Experience::executeSchema();
    Book::executeSchema();

});

afterEach(function () {
    SqlUser::truncate();
    SqlBook::truncate();
    SqlRole::truncate();
});

test('Sql Relations', function () {

    $user = new SqlUser;

    expect($user)->toBeInstanceOf(SqlUser::class)
        ->and($user->getConnection())->toBeInstanceOf(SQLiteConnection::class);

    // SQL User
    $user->name = 'John Doe';
    $user->save();
    expect($user->id)->toBeInt();

    // SQL has many
    $book = new Book(['title' => 'Game of Thrones']);
    $user->books()->save($book);
    $user = SqlUser::find($user->id); // refetch

    expect($user->books->all())->toHaveCount(1);

    // ES belongs to
    $book = $user->books()->first(); // refetch
    expect($book->sqlAuthor->name)->toBe('John Doe');

    // SQL has one
    $role = new Role(['type' => 'admin']);
    $user->role()->save($role);
    $user = SqlUser::find($user->id); // refetch
    expect($user->role->type)->toBe('admin');

    // MongoDB belongs to
    $role = $user->role()->first(); // refetch
    expect($role->sqlUser->name)->toBe('John Doe');

    // MongoDB User
    $user = new User;
    $user->name = 'John Doe';
    $user->save();

    // MongoDB has many
    $book = new SqlBook(['title' => 'Game of Thrones']);
    $user->sqlBooks()->save($book);
    $user = User::find($user->id); // refetch
    expect($user->sqlBooks)->toHaveCount(1);

    // SQL belongs to
    $book = $user->sqlBooks()->first(); // refetch
    expect($book->author->name)->toBe('John Doe');

    // MongoDB has one
    $role = new SqlRole(['type' => 'admin']);
    $user->sqlRole()->save($role);
    $user = User::find($user->id); // refetch
    expect($user->sqlRole->type)->toBe('admin');

    // SQL belongs to
    $role = $user->sqlRole()->first(); // refetch
    expect($role->user->name)->toBe('John Doe');

});

it('tests hybrid where has', function () {
    $user = new SqlUser;
    $otherUser = new SqlUser;

    expect($user)->toBeInstanceOf(SqlUser::class)
        ->and($user->getConnection())->toBeInstanceOf(SQLiteConnection::class)
        ->and($otherUser)->toBeInstanceOf(SqlUser::class)
        ->and($otherUser->getConnection())->toBeInstanceOf(SQLiteConnection::class);

    // SQL User
    $user->name = 'John Doe';
    $user->id = 2;
    $user->save();

    // Other user
    $otherUser->name = 'Other User';
    $otherUser->id = 3;
    $otherUser->save();

    // Make sure they are created
    expect($user->id)->toBeInt()
        ->and($otherUser->id)->toBeInt();

    // Clear to start
    $user->books()->truncate();
    $otherUser->books()->truncate();

    // Create books
    $otherUser->books()->saveMany([
        new Book(['title' => 'Harry Plants']),
        new Book(['title' => 'Harveys']),
    ]);

    // SQL has many
    $user->books()->saveMany([
        new Book(['title' => 'Game of Thrones']),
        new Book(['title' => 'Harry Potter']),
        new Book(['title' => 'Harry Planter']),
    ]);

    $users = SqlUser::whereHas('books', function ($query) {
        return $query->where('title', 'like', 'Har%');
    })->get();

    expect($users->count())->toBe(2);

    $users = SqlUser::whereHas('books', function ($query) {
        return $query->where('title', 'like', 'Harry%');
    }, '>=', 2)->get();

    expect($users->count())->toBe(1);

    $books = Book::whereHas('sqlAuthor', function ($query) {
        return $query->where('name', 'LIKE', 'Other%');
    })->get();

    expect($books->count())->toBe(2);
});

it('tests hybrid with', function () {
    $user = new SqlUser;
    $otherUser = new SqlUser;

    expect($user)->toBeInstanceOf(SqlUser::class)
        ->and($user->getConnection())->toBeInstanceOf(SQLiteConnection::class)
        ->and($otherUser)->toBeInstanceOf(SqlUser::class)
        ->and($otherUser->getConnection())->toBeInstanceOf(SQLiteConnection::class);

    // SQL User
    $user->name = 'John Doe';
    $user->id = 2;
    $user->save();

    // Other user
    $otherUser->name = 'Other User';
    $otherUser->id = 3;
    $otherUser->save();

    // Make sure they are created
    expect($user->id)->toBeInt();
    expect($otherUser->id)->toBeInt();

    // Clear to start
    Book::truncate();
    SqlBook::truncate();

    // Create books
    // SQL relation
    $user->sqlBooks()->saveMany([
        new SqlBook(['title' => 'Game of Thrones']),
        new SqlBook(['title' => 'Harry Potter']),
    ]);

    $otherUser->sqlBooks()->saveMany([
        new SqlBook(['title' => 'Harry Plants']),
        new SqlBook(['title' => 'Harveys']),
        new SqlBook(['title' => 'Harry Planter']),
    ]);

    // SQL has many Hybrid
    $user->books()->saveMany([
        new Book(['title' => 'Game of Thrones']),
        new Book(['title' => 'Harry Potter']),
    ]);

    $otherUser->books()->saveMany([
        new Book(['title' => 'Harry Plants']),
        new Book(['title' => 'Harveys']),
        new Book(['title' => 'Harry Planter']),
    ]);

    SqlUser::with('books')->get()
        ->each(function ($user) {
            expect($user->books->count())->toBe($user->id);
        });

    SqlUser::whereHas('sqlBooks', function ($query) {
        return $query->where('title', 'LIKE', 'Harry%');
    })
        ->with('books')
        ->get()
        ->each(function ($user) {
            expect($user->books->count())->toBe($user->id);
        });
});

it('tests hybrid belongs to many', function () {
    $user = new SqlUser;
    $user2 = new SqlUser;
    expect($user)->toBeInstanceOf(SqlUser::class)
        ->and($user->getConnection())->toBeInstanceOf(SQLiteConnection::class)
        ->and($user2)->toBeInstanceOf(SqlUser::class)
        ->and($user2->getConnection())->toBeInstanceOf(SQLiteConnection::class);

    // Create MySQL Users
    $user->fill(['name' => 'John Doe'])->save();
    $user = SqlUser::query()->find($user->id);

    $user2->fill(['name' => 'Maria Doe'])->save();
    $user2 = SqlUser::query()->find($user2->id);

    // Create Elasticsearch Skills
    $skill = Skill::query()->create(['name' => 'Laravel']);
    $skill2 = Skill::query()->create(['name' => 'Elasticsearch']);

    // sync (pivot is empty)
    $skill->sqlUsers()->sync([$user->id, $user2->id]);
    $check = Skill::query()->find($skill->id);
    expect($check->sqlUsers->count())->toBe(2);

    // sync (pivot is not empty)
    $skill->sqlUsers()->sync($user);
    $check = Skill::query()->find($skill->id);
    expect($check->sqlUsers->count())->toBe(1);

    // Inverse sync (pivot is empty)
    $user->skills()->sync([$skill->id, $skill2->id]);
    $check = SqlUser::find($user->id);
    expect($check->skills->count())->toBe(2);

    // Inverse sync (pivot is not empty)
    $user->skills()->sync($skill);
    $check = SqlUser::find($user->id);
    expect($check->skills->count())->toBe(1);

    // Inverse attach
    $user->skills()->sync([]);
    $check = SqlUser::find($user->id);
    expect($check->skills->count())->toBe(0);
    $user->skills()->attach($skill);
    $check = SqlUser::find($user->id);
    expect($check->skills->count())->toBe(1);
});

it('tests hybrid morph to many sql model to elastic model', function () {
    $user = new SqlUser;
    $user2 = new SqlUser;

    expect($user)->toBeInstanceOf(SqlUser::class)
        ->and($user->getConnection())->toBeInstanceOf(SQLiteConnection::class)
        ->and($user2)->toBeInstanceOf(SqlUser::class)
        ->and($user2->getConnection())->toBeInstanceOf(SQLiteConnection::class);

    // Create MySQL Users
    $user->fill(['name' => 'John Doe'])->save();
    $user = SqlUser::query()->find($user->id);

    $user2->fill(['name' => 'Maria Doe'])->save();
    $user2 = SqlUser::query()->find($user2->id);

    // Create Labels
    $label = Label::query()->create(['name' => 'Laravel']);
    $label2 = Label::query()->create(['name' => 'MongoDB']);

    //    // MorphToMany (pivot is empty)
    //    $user->labels()->sync([$label->id, $label2->id]);
    //    $check = SqlUser::query()->find($user->id);
    //    expect($check->labels->count())->toBe(2);

    // MorphToMany (pivot is not empty)
    $user->labels()->sync($label);
    $check = SqlUser::query()->find($user->id);
    expect($check->labels->count())->toBe(1);

    // Attach MorphToMany
    $user->labels()->sync([]);
    $check = SqlUser::query()->find($user->id);
    expect($check->labels->count())->toBe(0);
    $user->labels()->attach($label);
    $user->labels()->attach($label); // ignore duplicates
    $check = SqlUser::query()->find($user->id);
    expect($check->labels->count())->toBe(1);

    // Inverse MorphToMany (pivot is empty)
    $label->sqlUsers()->sync([$user->id, $user2->id]);
    $check = Label::query()->find($label->id);
    expect($check->sqlUsers->count())->toBe(2);

    // Inverse MorphToMany (pivot is not empty)
    $label->sqlUsers()->sync([$user->id, $user2->id]);
    $check = Label::query()->find($label->id);
    expect($check->sqlUsers->count())->toBe(2);
});

it('tests hybrid morph to many elastic model to sql model', function () {
    $user = new SqlUser;
    $user2 = new SqlUser;

    expect($user)->toBeInstanceOf(SqlUser::class)
        ->and($user->getConnection())->toBeInstanceOf(SQLiteConnection::class)
        ->and($user2)->toBeInstanceOf(SqlUser::class)
        ->and($user2->getConnection())->toBeInstanceOf(SQLiteConnection::class);

    // Create MySQL Users
    $user->fill(['name' => 'John Doe'])->save();
    $user = SqlUser::query()->find($user->id);

    $user2->fill(['name' => 'Maria Doe'])->save();
    $user2 = SqlUser::query()->find($user2->id);

    // Create MongoDB Experiences
    $experience = Experience::query()->create(['title' => 'DB expert']);
    $experience2 = Experience::query()->create(['title' => 'MongoDB']);

    // MorphToMany (pivot is empty)
    $experience->sqlUsers()->sync([$user->id, $user2->id]);
    $check = Experience::query()->find($experience->id);
    expect($check->sqlUsers->count())->toBe(2);

    // MorphToMany (pivot is not empty)
    $experience->sqlUsers()->sync([$user->id]);
    $check = Experience::query()->find($experience->id);
    expect($check->sqlUsers->count())->toBe(1);

    // Inverse MorphToMany (pivot is empty)
    $user->experiences()->sync([$experience->id, $experience2->id]);
    $check = SqlUser::query()->find($user->id);
    expect($check->experiences->count())->toBe(2);

    // Inverse MorphToMany (pivot is not empty)
    $user->experiences()->sync([$experience->id]);
    $check = SqlUser::query()->find($user->id);
    expect($check->experiences->count())->toBe(1);

    // Inverse MorphToMany (pivot is not empty)
    $user->experiences()->sync([]);
    $check = SqlUser::query()->find($user->id);
    expect($check->experiences->count())->toBe(0);
    $user->experiences()->attach($experience);
    $user->experiences()->attach($experience); // ignore duplicates
    $check = SqlUser::query()->find($user->id);
    expect($check->experiences->count())->toBe(1);
});
