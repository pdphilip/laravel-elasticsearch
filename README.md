Laravel x Elasticsearch
=======================

This package extends Laravel's Eloquent model and query builder for Elasticsearch. The goal of this package is to use
Elasticsearch in laravel as if it were native to Laravel, meaning:

- Work with your [Eloquent](#eloquent) models the way you're used to, including:

    - Standard query building: `Model::where('status','>',3)->orderByDesc('created_at')->get()`
    - Model [Relationships](#relationships) (Including cross-database)
    - [Mutators & Casting](#mutators--casting)
    - Data returned as Collections
    - [Soft Deletes](#soft-deletes)
    - [Aggregations](#aggregation)
    - [Migrations](#schema/index)

- No need to write your own DSL queries ([unless you want to](#raw-dsl)!)
- This plugin is not a search wrapper for your existing models

> # Alpha release notice
> ______
> This package is being released prematurely to an interested community of testers. It is not ready for production just yet only due to a lack of testing mileage. Once deemed stable, the plugin will move to V1. Elasticsearch is a deep topic on its own and there are many native features that have not yet been included. I built this because I needed it but this plugin is for everyone; submit issues (there's no way I could have found all the edge cases on my own) and feel free to submit pull requests.
>
> #### Versioning Schema: {plugin_version}.{laravel_version}.{iteration}
>
> ex: 0.8.1
>
> Version 0 will be alpha & beta    
> Version 1 will be RC & stable     
> Version 2+ will be next gen feature set

____

Installation
------------

Install the package via Composer:

```bash
$ composer require pdphilip/elasticsearch
```

____

Configuration
-------------

Proposed .env settings:

```dotenv
ES_AUTH_TYPE=http
ES_HOSTS="http://localhost:9200"
ES_USERNAME=
ES_PASSWORD=
ES_CLOUD_ID=
ES_API_ID=
ES_API_KEY=
ES_SSL_CERT=
```

For multiple nodes, pass in as comma separated:

```dotenv
ES_HOSTS="http://es01:9200,http://es02:9200,http://es03:9200"
```

Add the `elasticsearch` connection in `config/database.php`

```php
'connections' => [
    'elasticsearch' => [
            'driver'       => 'elasticsearch',
            'auth_type'    => env('ES_AUTH_TYPE', 'http'), //http, cloud or api
            'hosts'        => explode(',', env('ES_HOSTS', 'http://localhost:9200')),
            'username'     => env('ES_USERNAME', ''),
            'password'     => env('ES_PASSWORD', ''),
            'cloud_id'     => env('ES_CLOUD_ID', ''),
            'api_id'       => env('ES_API_ID', ''),
            'api_key'      => env('ES_API_KEY', ''),
            'ssl_cert'     => env('ES_SSL_CERT', ''),
            'index_prefix' => false, //prefix all Laravel administered indices
            'query_log'    => [
                'index'      => 'laravel_query_logs', //Or false to disable query logging
                'error_only' => true, //If false, the all queries are logged
            ],
        ],
    .....

```

Add the service provider to `config/app.php` (If your Laravel version does not autoload packages)

```php
'providers' => [
    ...
    ...
    PDPhilip\Elasticsearch\ElasticServiceProvider::class,
    ...

```

____

Eloquent
---------

### Extending the base model

Define your Eloquent models by extending the `PDPhilip\Elasticsearch\Eloquent\Model` class;

```php
use PDPhilip\Elasticsearch\Eloquent\Model;
/**
 * @mixin \Eloquent
 */
class Product extends Model
{
    protected $connection = 'elasticsearch';
}
```

In this example, the corresponding index for `Product` is `products`. In most cases, the `elasticsearch` connection
won't be the default connection. In that case you'll need to include `protected $connection = 'elasticsearch'` in your
model.

To change the inferred index name, pass the `$index` property:

```php
use PDPhilip\Elasticsearch\Eloquent\Model;
/**
 * @mixin \Eloquent
 */
class Product extends Model
{
    protected $index = 'my_products';	    
}
```

### Querying Models

****

#### ALL

Retrieving all records for a model

```php
$products = Product::all();
```

#### Find

Retrieving a record by primary key** (_id)

```php
$product = Product::find('IiLKG38BCOXW3U9a4zcn');
$product = Product::findOrFail('IiLKG38BCOXW3U9a4zcn');
```

#### First

```php
$product = Product::where('status',1)->first();
```

#### Where

```php
$products = Product::where('status',1)->take(10)->get();
$products = Product::where('manufacturer.country', 'England')->take(10)->get();
$products = Product::where('status','>=', 3)->take(10)->get();  
$products = Product::where('color','!=', 'red')->take(10)->get(); //*See notes
```

*Note: this query will also include collections where the color field does not exist, to exclude these,
use [whereNotNull()](#whereNotNull)

#### Where LIKE

```php
$products = Product::where('color', 'like', 'bl')->orderBy('color.keyword')->get();
// Will find blue and black
// No need to use SQL LIKE %bl%
// Text field is used for searching, keyword is used for ordering
```

#### OR Statements

```php
$products = Product::where('is_active', true)->orWhere('in_stock', '>=', 50)->get();
```

#### Chaining OR/AND statements

```php
$products = Product::where('type', 'coffee')
                ->where('is_approved', true)
                ->orWhere('type', 'tea')
                ->where('is_approved', true)
                ->get(); //Returns approved coffee or approved tea
```

Note: **Order of chaining matters** , It reads naturally from left to write having `where() as AND where `
& `orWhere() as OR where `. In the above example, the query would be:

`"((name:"coffee") AND (is_approved:"1")) OR ((name:"tea") AND (is_approved:"1"))"`

#### WhereIn

```php
$products = Product::whereIn('status', [1,5,11])->get();
```

#### WhereNotIn

```php
$products = Product::whereNotIn('color', ['red','green'])->get();
```

#### WhereNotNull

> Can be read as Where {field} Exists

When using `whereNotIn` objects will be returned if the field is non-existent. Combine with `whereNotNull('status')` to
leave out those documents. Ex:

```php
$products = Product::whereNotIn('color', ['red','green'])->whereNotNull('color')->get();
```

#### WhereNull

> Can be read as Where {field} does not exist

```php
$products = Product::whereNull('color')->get(); //Return all collections that doesn't have a 'color' field
```

#### WhereBetween

```php
$products = Product::whereBetween('in_stock', [10, 100])->get();
$products = Product::whereBetween('orders', [1, 20])->orWhereBetween('orders', [100, 200])->get();
```

### Dates

Elasticsearch by default converts a date into a timestamp, and applies the `strict_date_optional_time||epoch_millis`
format. If you have not changed the format at the index then acceptable values are:

- 2022-01-29
- 2022-01-29T13:05:59
- 2022-01-29T13:05:59+0:00
- 2022-01-29T12:10:30Z
- 1643500799 (timestamp)

With Carbon

```php
Carbon::now()->modify('-1 week')->toIso8601String()
```

You can use these values in a normal [where](#where) clause, or use the built-in date clause, ie:

**WhereDate**

```php
$products = Product::whereDate('created_at', '2022-01-29')->get();
```

**Note:** The usage for `whereMonth` / `whereDay` / `whereYear` / `whereTime` has disabled for the current version of
this plugin

### Aggregation

The usual:

```php
$totalSales = Sale::count();
$highestPrice = Sale::max('price');
$lowestPrice = Sale::min('price');
$averagePricePerSale = Sale::avg('price');
$totalEarnings = Sale::sum('price');
```

Combined with where clauses:

```php
$averagePrice = Product::whereNotIn('color', ['red','green'])->avg('price');
```

Elasticsearch [Matrix](https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-matrix-stats-aggregation.html)

```php
$stats = Product::whereNotIn('color', ['red','green'])->matrix('price');
$stats = Product::whereNotIn('color', ['red', 'green'])->matrix(['price', 'orders']);
```

### Ordering

When searching text fields Elasticsearch uses an internal scoring to rank and sort by the most relevant results as a
default return ordering. You can override this by ordering by and fields you like (**except for Text fields**,
see: [Ordering by Text field](#ordering-by-text-field) )

#### OrderBy

```php
$products = Product::orderBy('status')->get();
$products = Product::orderBy('created_at','desc')->get();
```

#### OrderByDesc

```php
$products = Product::orderByDesc('created_at')->get();
```

#### Offset & Limit (skip & take)

```php
$products = Product::skip(10)->take(5)->get();
```

#### Pagination

```php
$products = Product::where('is_active',true)
$products = $products->paginate(50)  
```

Pagination links (Blade)

```php+HTML
{{ $products->appends(request()->query())->links() }}
```

___

### Saving Models

The same as you always have with Laravel:

#### Save

[Option A] Attribute assigning:

```php
$log = new UserLog;
$log->user_id = $userId;
$log->title = $title;
$log->status = 1;
$log->save();
```

#### Create

[Option B] via `create()`

```php
$log = UserLog::create([
    'user_id' => $userId,
    'title'   => $title,
    'status'  => 1,
]);
```

> Keep in mind, the `$fillable` and `$guarded` attributes are honored when using `create()` or `update()`

#### Update

Same goes for updating

```php
$log = UserLog::where('status',1)->first();
$log->status = 2;
$log->save();
```

### Mass updating

```php
$updates = Product::where('status', 1)->update(['status' => 4]); //Updates all statuses from 1 to 4
// $updates => int (number of affected collections)
```

#### Fast Saves

**Saving 'without refresh'**

Elasticsearch will write a new document and return the `_id` before it has been indexed. This means that there could be
a delay in looking up the document that has just been created. To keep the indexed data consistent, the default is to *
write a new document and wait until it has been indexed* - If you know that you won't need to look up or manipulate the
new document immediately, then you can leverage the speed benefit of `write and move on` with `saveWithoutRefresh()`
and `createWithoutRefresh()`

```php
$log->saveWithoutRefresh();
//and
UserLog::createWithoutRefresh($attributes);
```

Example with undesired outcome:

```php
//BAD, AVOID:
$log = new UserLog;
$log->user_id = $userId;
$log->title = $title;
$log->status = 1;
$log->saveWithoutRefresh();
$log->company_id = 'ABC-123'
$log->saveWithoutRefresh();
//Will result in two separate records
```

___

### Deleting

#### Delete

The same as you always have with Laravel:

```php
$product = Product::find('IiLKG38BCOXW3U9a4zcn');
$product->delete();
//Or by mass
$product = Product::whereNull('color')->delete(); //Delete all records that doesn't have a color field

```

#### Truncate

Removes all records in index, *but keeps the index*, to remove index completely
use [Schema: Index Delete](#index-delete)

```php
Product::truncate(); 
```

#### Destroy by ID

```php
Product::destroy('9iKKHH8BCOXW3U9ag1_4'); //as single _id 
Product::destroy('4yKKHH8BCOXW3U9ag1-8', '_iKKHH8BCOXW3U9ahF8Q'); //as multiple _ids
Product::destroy(['7CKKHH8BCOXW3U9ag1_a', '7iKKHH8BCOXW3U9ag1_h']); //as array of _ids
```

### Soft Deletes

When soft deleting a model, it is not actually removed from the index. Instead, a deleted_at timestamp is set on the
record and is excluded from any queries unless explicitly called on.

```php
use PDPhilip\Elasticsearch\Eloquent\Model;
use PDPhilip\Elasticsearch\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;
		
}
```

Example:

```php
//With soft delete enabled
Product::destroy('wCIfHX8BCOXW3U9ahWH9');
Product::withTrashed()->where('_id', 'wCIfHX8BCOXW3U9ahWH9')->get();
Product::withTrashed()->where('_id', 'wCIfHX8BCOXW3U9ahWH9')->restore(); //restore by query

//Force delete
$product = Product::find('wCIfHX8BCOXW3U9ahWH9');
$product->forceDelete();

```

____

### Mutators & Casting

All Laravel's Mutating and casting features are inherited:

See [https://laravel.com/docs/8.x/eloquent-mutators](https://laravel.com/docs/8.x/eloquent-mutators)

Cool!

___

## Relationships

Model Relationships are the lifeblood of any Laravel App, for that you can use them with `belongsTo` , `hasMany`
, `hasOne`, `morphOne` and `morphMany` as you have before:

#### Elasticsearch <-> Elasticsearch

Full Example:

Company:

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

CompanyLog:

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

CompanyProfile:

```php
/**
 * App\Models\CompanyProfile
 *
 ******Fields*******
 * @property string $_id
 * @property string $company_id
 * @property string $address
 * @property string $website
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 ******Relationships*******
 * @property-read Company $company
 *
 * @mixin \Eloquent
 *
 */
class CompanyProfile extends Model
{
    protected $connection = 'elasticsearch';

    //Relationships  =====================================
  
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

}
```

Avatar:

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

Photo:

```php
/**
 * App\Models\Photo
 ******Fields*******
 * @property string $_id
 * @property string $url
 * @property string $photoable_id
 * @property string $photoable_type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
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

Example Usage:

```php
$company = Company::first();
$company->companyLogs->toArray(); //Shows all company logs (has many)
$company->companyProfile->toArray(); //Shows the company profile (has one)
$company->avatar->toArray(); //Shows the company avatar (morph one)
$company->photos->toArray(); //Shows the company photos (morph many)

```

#### Elasticsearch <-> MySQL

Since it's unlikely that you will use Elasticsearch in isolation for your App; we've ensured that you can have hybrid
relationships between Elasticsearch and MySQL (Or any native Laravel datasource) models.

**For the MySQL(or similar) model that you wish to bind to Elasticsearch relationbships, please
use**: `use PDPhilip\Elasticsearch\Eloquent\HybridRelations`

Example, mysql User model:

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use PDPhilip\Elasticsearch\Eloquent\HybridRelations;

/**
 * App\Models\User
 *
 * *****Relationships*******
 * @property-read User $user
 */
class User extends Authenticatable
{
  use HybridRelations;
  
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

UserLog

```php
/**
 * App\Models\UserLog
 ******Fields*******
 * @property string $_id
 * @property string $company_id
 * @property string $title
 * @property integer $code
 * @property mixed $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
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

UserProfile

```php
/**
 * App\Models\UserProfile
 *
 ******Fields*******
 *
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
 ******Attributes*******
 * @property-read mixed $status_name
 * @property-read mixed $status_color
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

- Company (as example before) where user has the filed `company_id` as $company->_id
- Avatar: (as before) having `imageable_id` as $user->id and `imageable_type` as 'App\Models\User'
- Photo: (as before) having `photoable_id` as $user->id and `photoable_type` as 'App\Models\User'

Example usage:

```php
$user = User::first();
$user->company->name; //Company name for the user
$user->userProfile->twitter;
$user->avatar->url; //Link to Avatar
$user->photos->toArray(); //Array of photos

$userLog = UserLog::first();
$userLog->user->name; 
```

____

Schema/Index
-----------------

Since there is very little overlap with how Elasticsearch handles index management to how MySQL and related tencologioes
handle Schema manipulation; the schema feature of this plugin has been written from the ground up to work 100% with
Elasticsearch.

You can still create a migration class as normal (and it's recommened that you do), however the `up()` and `down()`
methods will need to encapsualte the following:

- **Schema** via `PDPhilip\Elasticsearch\Schema\Schema`
- **IndexBlueprint** via `PDPhilip\Elasticsearch\Schema\IndexBlueprint`
- **AnalyzerBlueprint** via `PDPhilip\Elasticsearch\Schema\AnalyzerBlueprint`

Full example:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use PDPhilip\Elasticsearch\Schema\Schema;
use PDPhilip\Elasticsearch\Schema\IndexBlueprint;
use PDPhilip\Elasticsearch\Schema\AnalyzerBlueprint;

class MyIndexes extends Migration
{
    public function up()
    {
        Schema::create('contacts', function (IndexBlueprint $index) {
          //first_name & last_name is automatically added to this field, 
          //you can search by full_name without ever writing to full_name  
          $index->text('first_name')->copyTo('full_name');
          $index->text('last_name')->copyTo('full_name');
          $index->text('full_name');

          //Multiple types => Order matters ::
          //Top level `email` will be a searchable text field
          //Sub Property will be a keyword type which can be sorted using orderBy('email.keyword')            
          $index->text('email');
          $index->keyword('email');

          //Dates have an optional formatting as second parameter
          $index->date('first_contact', 'epoch_second');
            
          //Objects are defined with dot notation:
          $index->text('products.name');
          $index->float('products.price')->coerce(false);

          //Disk space considerations ::
          //Not indexed and not searchable:
          $index->text('internal_notes')->docValues(false);  
          //Remove scoring for search:
          $index->array('tags')->norms(false);  
          //Remove from index, can't search by this field but can still use for aggregations:
          $index->integer('score')->index(false);  

          //If null is passed as value, then it will be saved as 'NA' which is searchable
          $index->keyword('favorite_color')->nullValue('NA');  

          //Alias Example
          $index->text('notes');
          $index->alias('comments', 'notes');

          $index->geo('last_login');
          $index->date('created_at');
          $index->date('updated_at');

          //Settings
          $index->settings('number_of_shards', 3);
          $index->settings('number_of_replicas', 2);

          //Other Mappings
          $index->map('dynamic', false);
          $index->map('date_detection', false);
        });
        
        //Example analyzer builder
        Schema::setAnalyser('contacts', function (AnalyzerBlueprint $settings) {
            $settings->analyzer('my_custom_analyzer')
                ->type('custom')
                ->tokenizer('punctuation')
                ->filter(['lowercase', 'english_stop'])
                ->charFilter(['emoticons']);
            $settings->tokenizer('punctuation')
                ->type('pattern')
                ->pattern('[ .,!?]');
            $settings->charFilter('emoticons')
                ->type('mapping')
                ->mappings([":) => _happy_", ":( => _sad_"]);
            $settings->filter('english_stop')
                ->type('stop')
                ->stopwords('_english_');
        });
    }
    
    public function down()
    {
        Schema::deleteIfExists('contacts');
    }
}

```

All methods

```php
Schema::getIndices();
Schema::getMappings('my_index')
Schema::getSettings('my_index')
Schema::create('my_index',function (IndexBlueprint $index) {
    //......
})
Schema::createIfNotExists('my_index',function (IndexBlueprint $index) {
    //......
})
Schema::reIndex('from_index','to_index') {
    //......
})
Schema::modify('my_index',function (IndexBlueprint $index) {
    //......
});
Schema::delete('my_index')
Schema::deleteIfExists('my_index')
Schema::setAnalyser('my_index',function (AnalyzerBlueprint $settings) {
	//......
});
//Booleans
Schema::hasField('my_index','my_field')
Schema::hasFields('my_index',['field_a','field_b','field_c'])
Schema::hasIndex('my_index')
//DIY
Schema::dsl('indexMethod',$dslParams)

```

Example manual DSL:

```php
Schema::dsl('close',['index' => 'my_index'])
$dslParams = [
  'index' => 'my_index',
  'body' => .........
];
Schema::dsl('putSettings',$dslParams)
Schema::dsl('open',['index' => 'my_index'])
```

Behind the scenes it uses the official elasticsearch PHP client, it will call `$client->indices()->{$method}($params);`

____

Queues
----------
_[Coming]_



___

Elasticsearch-specific operators
--------------------------------

#### RAW DSL

BYO query, sure! We'll get out the way and try to return the values in a collection for you:

Searching Models:

```php
$bodyParams = [
  'query' => [
    'match' => [
      'color' => 'silver',
    ],
  ],
];

return Product::rawSearch($bodyParams); //Will search within the products index
```

___

Elasticsearchisms
--------------------------------

#### Elasticsearch's default search limit is to return 10 collections

This plugin sets the default limit to 1000, however you can set your own with `MAX_SIZE`:

```php
use PDPhilip\Elasticsearch\Eloquent\Model;
/**
 * @mixin \Eloquent
 */
class Product extends Model
{
    const MAX_SIZE = 10000;
    protected $connection = 'elasticsearch';
}

```

**Remember, you can use chunking if you need to cycle through all the records**

#### Empty Text Fields

By default, empty text fields are not searchable as they are not indexed. If you need to be able to search for empty
values you have two options:

1. Exclude the field on Saving/Updating , then use [#wherenull](#wherenull)
2. Create an index where the field is set to have a null value see [Schema](#Schema/index)
   where `$index->keyword('favorite_color')->nullValue('NA');  `

#### Ordering by Text field

Elasticsearch can not order by text fields due to how the values are indexed and tokenized. If you do not define a
string value upfront in your [Schema](#Schema/index) then Elasticsearch will default to saving the field as a `text`
field. If you try to sort by that field the database engine will fail. Options:

1. If you do not need to search the text within the field and ordering is important, then use a `keyword` field type: To
   do so define your index upfront in the  [Schema](#Schema/index) and set `$index->keyword('email')`

2. If you need to have the field both searchable and sortable, then you'll need to have a multi type definition upfront
   in your [Schema](#Schema/index) , ex:

   ```php
   $index->text('description')
   $index->keyword('description')
   ```

   The order matters, the field will primarily be a text field, required for searching with a keyword sub-type. To be
   able to order by this field you would have to use `orderBy('description.keyword')` to tell elasticsearch which type
   to use.

#### Saving and refresh

Refresh requests are synchronous and do not return a response until the refresh operation completes.

All saves are by default done with `refresh=wait_for` parameter - this is to ensure that the data is available
immediately after it has been written. However, there is response delay which may not be optimal. If you intend to write
once and not update immediately or won't need to search for the record immediately, then do `saveWithoutRefresh()`



_____

### Unsupported Eloquent methods

- _[Coming]_

___
Acknowledgements
----------------

This package was inspired by [jenssegers/laravel-mongodb](https://github.com/jenssegers/laravel-mongodb), a MongoDB
implementation of Laravel's Eloquent ORM - Thank you!
