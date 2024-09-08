# Model Relationships in Elasticsearch
In Laravel applications, model relationships are crucial for structuring complex data and interactions between different entities. The Laravel-Elasticsearch integration supports a variety of relationship types, enabling Elasticsearch documents to relate to each other similarly to how models relate in traditional relational databases. This section provides a comprehensive guide on implementing Elasticsearch-to-Elasticsearch model relationships within a Laravel application.
## Defining Relationships
Just like in a traditional Laravel Eloquent model, you can define `belongsTo`, `hasMany`, `hasOne`, `morphOne`, and `morphMany` relationships in models that use Elasticsearch as their data storage. Here's a full example illustrating various relationship types in an Elasticsearch context:
### Full example
#### Relationship Diagram
The models will define the following relationships:
![relationships image](/es-es.webp)
#### Company Model
```php
/**
 * App\Models\Company
 *
 ******Fields*******
 * @property string $_id
 * @property string $name
 * @property integer $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 ******Relationships*******
 * @property-read CompanyLog $companyLogs
 * @property-read CompanyProfile $companyProfile
 * @property-read Avatar $avatar
 * @property-read Photos $photos
 *
 * @mixin \Eloquent
 *
 */
class Company extends Model
{
    protected $connection = 'elasticsearch';

    //Relationships  =====================================

    public function companyLogs()
    {
        return $this->hasMany(CompanyLog::class);
    }

    public function companyProfile()
    {
        return $this->hasOne(CompanyProfile::class);
    }

    public function avatar()
    {
        return $this->morphOne(Avatar::class, 'imageable');
    }

    public function photos()
    {
        return $this->morphMany(Photo::class, 'photoable');
    }
}
```
#### CompanyLog Model
```php
/**
 * App\Models\CompanyLog
 *
 ******Fields*******
 * @property string $_id
 * @property string $company_id
 * @property string $title
 * @property integer $code
 * @property mixed $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 ******Relationships*******
 * @property-read Company $company
 *
 * @mixin \Eloquent
 *
 */
class CompanyLog extends Model
{
    protected $connection = 'elasticsearch';

    //Relationships  =====================================

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
```

#### Avatar Model
```php
/**
 * App\Models\Avatar
 *
 ******Fields*******
 * @property string $_id
 * @property string $url
 * @property string $imageable_id
 * @property string $imageable_type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 ******Relationships*******
 * @property-read Company $company
 *
 * @mixin \Eloquent
 *
 */
class Avatar extends Model
{
    protected $connection = 'elasticsearch';

    //Relationships  =====================================

    public function imageable()
    {
        return $this->morphTo();
    }
}
```

#### Photo Model
```php
/**
 * App\Models\Photo
 *
 ******Fields*******
 * @property string $_id
 * @property string $url
 * @property string $photoable_id
 * @property string $photoable_type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 ******Relationships*******
 * @property-read Company $company
 *
 * @mixin \Eloquent
 *
 */
class Photo extends Model
{
    protected $connection = 'elasticsearch';

    //Relationships  =====================================

    public function photoable()
    {
        return $this->morphTo();
    }
}
```

#### Example Usage
```php
$company = Company::first();
$companyLogs = $company->companyLogs->toArray(); //Shows all company logs (has many)
$companyProfile = $company->companyProfile->toArray(); //Shows the company profile (has one)
$companyAvatar = $company->avatar->toArray(); //Shows the company avatar (morph one)
$companyPhotos = $company->photos->toArray(); //Shows the company photos (morph many)
```
This process is exactly the same as defining relationships in traditional Laravel Eloquent models.

Important to note that these are not real joins as Elasticsearch does not support joins. Instead, the relationships are defined in the models themselves and are mapped and retrieved by separate queries internally.
