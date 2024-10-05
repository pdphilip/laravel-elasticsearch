# Re-indexing Process
Re-indexing in Elasticsearch is a critical operation, especially when you need to alter the mapping of existing data. This process involves creating a new index with the desired mappings and copying the data from the old index to the new one. Here's a step-by-step example of how this can be achieved using the schema functionality of this plugin.

## Missed Mapping
To create a real-world scenario, let's consider a site log system that stores the URL, user IP, and location of visitors.

We'll intentionally overlook the mapping of the `location` field, which should be mapped as a `geo_point` type.

```php
Schema::create('site_logs', function (IndexBlueprint $index) {
    $index->keyword('url');
    $index->ip('user_ip');
    // Initially, the 'location' field might be overlooked or incorrectly mapped
});
```
Assuming there's a model for this index, you create a record like so:
```php
SiteLog::create([
    'url' => 'https://example.com/contact-us',
    'user_ip' => '0.0.0.0',
    'location' => ['lat' => -7.3, 'lon' => 3.1] // This field is not correctly mapped yet
]);
```
After some time, you realize that the `location` field should be mapped as a `geo_point` type. If you try to filter records based on the `location` field, you'll get an error because the field is not correctly mapped.

## Re-indexing

### Step 1: Create a Temporary Index with Correct Mapping
Create a temporary index with the correct mapping for the location field.
```php
Schema::create('temp_site_logs', function (IndexBlueprint $index) {
    $index->keyword('url');
    $index->ip('user_ip');
    $index->geo('location'); // Correct mapping for the 'location' field
});
```

### Step 2: Re-indexing Data to the Temporary Index
Copy all records from the original site_logs index to the temp_site_logs index.
```php
$result = Schema::reIndex('site_logs', 'temp_site_logs');
```
::: tip Verify the success of the re-indexing operation, analyze $result->data for any errors and make sure all the collections were copied to the new index.
:::
```php
$copiedRecords = DB::connection('elasticsearch')->table('my_prefix_temp_site_logs')->count();
```

### Step 3: Delete the Original Index
Once you've confirmed that all data has been successfully copied, delete the original index.
```php
Schema::delete('site_logs');
```

### Step 4: Recreating the Original Index with Correct Mapping
Now, recreate the `site_logs` index with the correct mappings.
```php
Schema::create('site_logs', function (IndexBlueprint $index) {
    $index->keyword('url');
    $index->ip('user_ip');
    $index->geo('location'); // Now with the correct mapping
});
```
### Step 5: Re-indexing Data Back to the Original Index
Copy the data from the temporary index back to the original index.
```php
$result = Schema::reIndex('temp_site_logs', 'site_logs');
```
Again, verify the success of the re-indexing operation:
```php
$copiedRecords = DB::connection('elasticsearch')->table('my_prefix_site_logs')->count();
```

### Step 6: Verifying the Correct Functionality
Now, with the location field correctly mapped, filtering operations should work as expected.
```php
$logs = SiteLog::filterGeoBox('location', [-10, 10], [10, -10])->get();
```
If there is an issue then delete the index and go back to step 4

### Step 7: Delete the Temporary Index
Finally, delete the temporary index.
```php
Schema::delete('temp_site_logs');
```
