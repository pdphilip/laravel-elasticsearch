# Elasticsearch to MySQL Model Relationships
In many applications, Elasticsearch is used in conjunction with a relational database like MySQL. The Laravel-Elasticsearch integration allows you to define hybrid relationships between Elasticsearch and MySQL models seamlessly. This enables a flexible and powerful architecture for applications that need the speed and scalability of Elasticsearch along with the reliability and consistency of a relational database.

## Implementing Hybrid Relationships
To establish a relationship between an Elasticsearch model and a MySQL (or any native Laravel datasource) model, you will need to use the `HybridRelations` trait provided by the Laravel-Elasticsearch package in your non-Elasticsearch model to bind the two models together.
```php
use PDPhilip\Elasticsearch\Eloquent\HybridRelations;
```

## Full example
#### Relationship Diagram
The models will define the following relationships:
![relationships image](/es-mysql.webp)

#### User model (MySQL)
```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use PDPhilip\Elasticsearch\Eloquent\HybridRelations;  // [!code highlight]

/**
 * App\Models\User
 *
 * *****Relationships*******
 * @property-read UserLog $userLogs
 * @property-read UserProfile $userProfile
 * @property-read Company $company
 * @property-read Avatar $avatar
 * @property-read Photo $photos
 */
class User extends Authenticatable
{
    use HybridRelations;  // [!code highlight]

    protected $connection = 'mysql';

    //Relationships  =====================================
    // With Elasticsearch models

    public function userLogs()
    {
        return $this->hasMany(UserLog::class);
    }

    public function userProfile()
    {
        return $this->hasOne(UserProfile::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
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

#### UserLog model (Elasticsearch)
```php
/**
 * App\Models\UserLog
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
 * @property-read User $user
 *
 * @mixin \Eloquent
 */
class UserLog extends Model
{

    protected $connection = 'elasticsearch';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
```

#### UserProfile model (Elasticsearch)
```php
/**
 * App\Models\UserProfile
 *
 ******Fields*******
 * @property string $_id
 * @property string $user_id
 * @property string $twitter
 * @property string $facebook
 * @property string $address
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 ******Relationships*******
 * @property-read User $user
 *
 * @mixin \Eloquent
 *
 */
class UserProfile extends Model
{

    protected $connection = 'elasticsearch';

    //Relationships  =====================================

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
```

#### Company Model (Elasticsearch)
```php
/**
 * App\Models\CompanyLog
 *
 ******Fields*******
 * @property string $_id
 * @property string $name
 * @property integer $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 ******Relationships*******
 * @property-read User $user
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
        return $this->hasMany(User::class);
    }
}
```

#### Avatar Model (Elasticsearch)
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
#### Photo Model (Elasticsearch)
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
// Retrieve User and related Elasticsearch models
$user = User::first();
$userCompanyName = $user->company->name; //Company name for the user
$userTwitter = $user->userProfile->twitter;
$userAvatar = $user->avatar->url; //Link to Avatar
$userPhotos = $user->photos->toArray(); //Array of photos

// Retrieve UserLog and related MySQL User
$userLog = UserLog::first();
$userName = $userLog->user->name;

// Retrieve Company and related MySQL Users
$company = Company::first();
$companyUsers = $company->users->toArray(); //Array of users
```
