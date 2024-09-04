<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use PDPhilip\Elasticsearch\Eloquent\Model;
use Workbench\Database\Factories\CompanyProfileFactory;

/**
 * App\Models\
 *
 ******Fields*******
 *
 * @property string $_id
 * @property string $company_id
 * @property string $address
 * @property string $website
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
class CompanyProfile extends Model
{
    use HasFactory;

    protected $connection = 'elasticsearch';

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public static function newFactory(): CompanyProfileFactory
    {
        return CompanyProfileFactory::new();
    }
}
