<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use PDPhilip\Elasticsearch\Eloquent\Model as Eloquent;
use Workbench\Database\Factories\StaticPageFactory;

/**
 * App\Models\StaticPage
 *
 ******Fields*******
 *
 * @property string $_id
 * @property string $title
 * @property string $content
 * @property array $comments
 *
 * @mixin Eloquent
 */
class StaticPage extends Eloquent
{
    use HasFactory;

    // Disable timestamps for testing purposes
    public $timestamps = false;

    public $connection = 'elasticsearch';

    public static function newFactory(): StaticPageFactory
    {
        return StaticPageFactory::new();
    }
}
