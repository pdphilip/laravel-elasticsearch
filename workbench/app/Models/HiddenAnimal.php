<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use PDPhilip\Elasticsearch\Eloquent\Model;
use Workbench\Database\Factories\AvatarFactory;

/**
 * @property string $name
 * @property string $country
 * @property bool $can_be_eaten
 */
final class HiddenAnimal extends Model
{
  protected $connection = 'elasticsearch';

  protected $keyType = 'string';
  protected $fillable = [
    'name',
    'country',
    'can_be_eaten',
  ];

  protected $hidden = ['country'];
}
