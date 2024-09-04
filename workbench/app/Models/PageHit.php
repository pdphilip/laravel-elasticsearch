<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use PDPhilip\Elasticsearch\Eloquent\Model;
use Workbench\Database\Factories\PageHitFactory;

class PageHit extends Model
{
    use HasFactory;

    protected $connection = 'elasticsearch';
    protected $index = 'page_hits_*';

    public static function newFactory(): PageHitFactory
    {
        return PageHitFactory::new();
    }
}
