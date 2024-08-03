<?php

  namespace Workbench\App\Models;

  use Illuminate\Database\Eloquent\Factories\HasFactory;
  use PDPhilip\Elasticsearch\Eloquent\Model;
  use Workbench\Database\Factories\PersonFactory;

  /**
   * App\Models\UserJob
   *
   ******Fields*******
   *
   * @property string $_id
   * @property string $name
   * @property int $status
   * @property array $jobs
   * @property \Illuminate\Support\Carbon|null $created_at
   * @property \Illuminate\Support\Carbon|null $updated_at
   *
   ******Relationships*******
   * @property-read User $user
   *
   ******Attributes*******
   * @property-read mixed xx
   *
   * @mixin \Eloquent
   *
   */
  class Person extends Model
  {
    use HasFactory;

    //----------------------------------------------------------------------
    // Model Definition/Config
    //----------------------------------------------------------------------
    protected $connection = 'elasticsearch';

    protected $fillable = [
      'name',
      'status',
      'jobs',
    ];

    const CREATED_AT = null;
    const UPDATED_AT = null;
    const MAX_SIZE = 2;

    //----------------------------------------------------------------------
    // Relationships
    //----------------------------------------------------------------------


    //----------------------------------------------------------------------
    // Attributes
    //----------------------------------------------------------------------


    //----------------------------------------------------------------------
    // Statics
    //----------------------------------------------------------------------


    //----------------------------------------------------------------------
    // Entities
    //----------------------------------------------------------------------


    //----------------------------------------------------------------------
    // Privates/Helpers
    //----------------------------------------------------------------------

    public static function newFactory(): PersonFactory
    {
      return PersonFactory::new();
    }

  }
