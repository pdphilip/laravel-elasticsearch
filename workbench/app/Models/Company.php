<?php

  namespace Workbench\App\Models;

  use Illuminate\Database\Eloquent\Factories\HasFactory;
  use PDPhilip\Elasticsearch\Eloquent\Model as Eloquent;
  use Workbench\Database\Factories\CompanyFactory;

  /**
   * App\Models\Company
   *
   ******Fields*******
   *
   * @property string $_id
   * @property string $name
   * @property integer $status
   * @property \Illuminate\Support\Carbon|null $created_at
   * @property \Illuminate\Support\Carbon|null $updated_at
   *
   ******Relationships*******
   * @property-read User $user
   * @property-read Avatar $avatar
   * @property-read CompanyLog $companyLogs
   * @property-read CompanyProfile $companyProfile
   * @property-read EsPhoto $esPhotos
   * @property-read Photo $photos
   * @property-read UserLog $userLogs
   * @property-read Client $clients
   *
   *
   ******Attributes*******
   * @property-read mixed $status_name
   * @property-read mixed $status_color
   *
   * @mixin \Eloquent
   *
   */
  class Company extends Eloquent
  {
    use HasFactory;
    protected $connection = 'elasticsearch';

    //model definition =====================================
    public static $statuses = [

      1 => [
        'name'       => 'New',
        'level'      => 1,
        'color'      => 'text-neutral-500',
        'time_model' => 'created_at',
      ],

    ];


    //Relationships  =====================================

    public function users()
    {
      return $this->hasMany(User::class);
    }

    public function userLogs()
    {
      return $this->hasMany(UserLog::class);
    }

    public function companyLogs()
    {
      return $this->hasMany(CompanyLog::class);
    }

    public function companyProfile()
    {
      return $this->hasOne(CompanyProfile::class);
    }

    public function avatar()
    {
      return $this->morphOne(Avatar::class, 'imageable');
    }

    public function photos()
    {
      return $this->morphMany(Photo::class, 'photoable');
    }

    public function esPhotos()
    {
      return $this->morphMany(EsPhoto::class, 'photoable');
    }


    public function clients()
    {
      return $this->hasMany(Client::class);
    }

    public static function newFactory(): CompanyFactory
    {
      return CompanyFactory::new();
    }

  }
