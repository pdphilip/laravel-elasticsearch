<?php

  namespace Workbench\App\Models;

  use Illuminate\Database\Eloquent\Factories\HasFactory;
  use PDPhilip\Elasticsearch\Eloquent\Model as Eloquent;
  use Workbench\Database\Factories\ClientProfileFactory;

  /**
   * App\Models\ClientProfile
   *
   ******Fields*******
   *
   * @property string $_id
   * @property string $client_id
   * @property string $contact_name
   * @property string $contact_email
   * @property string $website
   * @property integer $status
   * @property \Illuminate\Support\Carbon|null $created_at
   * @property \Illuminate\Support\Carbon|null $updated_at
   *
   ******Relationships*******
   * @property-read User $user
   *
   ******Attributes*******
   * @property-read mixed $status_name
   * @property-read mixed $status_color
   *
   * @mixin \Eloquent
   *
   */
  class ClientProfile extends Eloquent
  {
    use HasFactory;

    public $connection = 'elasticsearch';

    //Relationships  =====================================

    public function client()
    {
      return $this->belongsTo(Client::class);
    }
    public static function newFactory(): ClientProfileFactory
    {
      return ClientProfileFactory::new();
    }

  }
