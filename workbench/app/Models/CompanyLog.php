<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use PDPhilip\Elasticsearch\Eloquent\HybridRelations;
use PDPhilip\Elasticsearch\Eloquent\Model;
use Workbench\Database\Factories\CompanyLogFactory;

/**
 * App\Models\
 *
 ******Fields*******
 *
 * @property string $_id
 * @property string $company_id
 * @property string $title
 * @property string $desc
 * @property int $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 ******Relationships*******
 * @property-read Company $company
 *
 ******Attributes*******
 *
 * @mixin \Eloquent
 */
class CompanyLog extends Model
{
    use HasFactory, HybridRelations;

    protected $connection = 'elasticsearch';

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public static function newFactory(): CompanyLogFactory
    {
        return CompanyLogFactory::new();
    }
}
