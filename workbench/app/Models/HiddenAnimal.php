<?php

namespace Workbench\App\Models;

use PDPhilip\Elasticsearch\Eloquent\Model;

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
