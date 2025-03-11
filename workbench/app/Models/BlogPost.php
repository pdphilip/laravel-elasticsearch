<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use PDPhilip\Elasticsearch\Eloquent\Model as Eloquent;
use Workbench\Database\Factories\BlogPostFactory;

/**
 * App\Models\BlogPost
 *
 ******Fields*******
 *
 * @property string $_id
 * @property string $title
 * @property string $content
 * @property array $comments
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @mixin \Eloquent
 */
class BlogPost extends Eloquent
{
    use HasFactory;

    public $connection = 'elasticsearch';
    // ----------------------------------------------------------------------
    // Model Definition/Config
    // ----------------------------------------------------------------------

    // ----------------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------------

    // ----------------------------------------------------------------------
    // Attributes
    // ----------------------------------------------------------------------

    // ----------------------------------------------------------------------
    // Statics
    // ----------------------------------------------------------------------

    // ----------------------------------------------------------------------
    // Entities
    // ----------------------------------------------------------------------

    // ----------------------------------------------------------------------
    // Privates/Helpers
    // ----------------------------------------------------------------------

    public static function newFactory(): BlogPostFactory
    {
        return BlogPostFactory::new();
    }
}
