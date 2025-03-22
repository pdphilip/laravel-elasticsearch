<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Tests\Models;

use DateTimeInterface;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use PDPhilip\Elasticsearch\Eloquent\Builder;
use PDPhilip\Elasticsearch\Eloquent\Model;
use PDPhilip\Elasticsearch\Schema\Blueprint;
use PDPhilip\Elasticsearch\Schema\Schema;

class User extends Model implements AuthenticatableContract, CanResetPasswordContract
{
    use Authenticatable;
    use CanResetPassword;
    use MassPrunable;
    use Notifiable;

    protected $connection = 'elasticsearch';

    protected $casts = [
        'birthday' => 'datetime',
        'entry.date' => 'datetime',
        'member_status' => MemberStatus::class,
    ];

    protected $queryFieldMap = [
        'title' => 'title.keyword',
    ];

    protected $fillable = [
        'name',
        'email',
        'title',
        'age',
        'birthday',
        'username',
        'member_status',
    ];

    protected static $unguarded = true;

    public function books()
    {
        return $this->hasMany(Book::class, 'author_id');
    }

    public function softs()
    {
        return $this->hasMany(Soft::class);
    }

    public function softsWithTrashed()
    {
        return $this->hasMany(Soft::class)->withTrashed();
    }

    public function sqlBooks()
    {
        return $this->hasMany(SqlBook::class, 'author_id');
    }

    public function items()
    {
        return $this->hasMany(Item::class);
    }

    public function role()
    {
        return $this->hasOne(Role::class);
    }

    public function sqlRole()
    {
        return $this->hasOne(SqlRole::class);
    }

    public function clients()
    {
        return $this->belongsToMany(Client::class);
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'groups', 'users', 'groups', 'id', 'id', 'groups');
    }

    public function photos()
    {
        return $this->morphMany(Photo::class, 'has_image');
    }

    public function labels()
    {
        return $this->morphToMany(Label::class, 'labelled');
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('l jS \of F Y h:i:s A');
    }

    protected function username(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value,
            set: fn ($value) => Str::slug($value),
        );
    }

    public function prunable(): Builder
    {
        return $this->where('age', '>', 18);
    }

    /**
     * Check if we need to run the schema.
     */
    public static function executeSchema()
    {
        $schema = Schema::connection('elasticsearch');

        $schema->dropIfExists('users');
        $schema->create('users', function (Blueprint $table) {
            $table->text('name', hasKeyword: true);
            $table->integer('age');
            $table->text('title', true);
            $table->date('birthday');
            $table->date('created_at');
            $table->date('updated_at');
        });
    }
}
