<?php

  declare(strict_types=1);

  namespace Workbench\App\Models;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use PDPhilip\Elasticsearch\Eloquent\Builder;
use PDPhilip\Elasticsearch\Eloquent\HybridRelations;
use PDPhilip\Elasticsearch\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use PDPhilip\Elasticsearch\Schema\IndexBlueprint;
use PDPhilip\Elasticsearch\Schema\Schema;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string $title
 * @property int $age
 * @property Carbon $birthday
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property string $username
 * @property MemberStatus member_status
 */
class User extends Model implements AuthenticatableContract, CanResetPasswordContract
{
    use HasFactory, HybridRelations, Notifiable;

    use Authenticatable;
    use CanResetPassword;
    use Notifiable;
    use MassPrunable;

  protected $connection = 'elasticsearch';


  protected $casts = [
    'birthday' => 'datetime',
    'entry.date' => 'datetime',
    'member_status' => MemberStatus::class,
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

    $schema->deleteIfExists('users');
    $schema->create('users', function (IndexBlueprint $table) {
      $table->text('name');
      $table->keyword('name');
      $table->date('birthday');
      $table->date('created_at');
      $table->date('updated_at');
    });
  }

}
