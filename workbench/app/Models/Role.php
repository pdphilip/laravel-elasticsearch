<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use PDPhilip\Elasticsearch\Eloquent\Model;

/**
 * @property string $title
 * @property string $author
 * @property array $chapters
 */
class Role extends Model
{
    protected $connection = 'elasticsearch';

    protected $index = 'skills';

    protected static $unguarded = true;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sqlUser()
    {
        return $this->belongsTo(SqlUser::class);
    }
}
