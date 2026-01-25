<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Tests\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use PDPhilip\Elasticsearch\Eloquent\Model;
use PDPhilip\Elasticsearch\Tests\Concerns\TestsWithIdStrategies;

/** @property Carbon $created_at */
class Group extends Model
{
    use TestsWithIdStrategies;

    protected $connection = 'elasticsearch';

    protected $table = 'groups';

    protected static $unguarded = true;

    public function groupUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'users', 'groups', 'users', 'id', 'id', 'users');
    }
}
