<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use PDPhilip\Elasticsearch\Exceptions\InvalidFieldTypeException;
use PDPhilip\Elasticsearch\Schema\Blueprint;
use PDPhilip\Elasticsearch\Schema\Schema;
use PDPhilip\Elasticsearch\Tests\Models\User;

beforeEach(function () {
    Schema::dropIfExists('newcollection');
    Schema::dropIfExists('newcollection_two');
});

it('creates a new index', function () {
    Schema::create('newcollection');
    expect(Schema::hasTable('newcollection'))->toBeTrue();
});

it('creates a new index if one dosen\'t exsist ', function () {
    expect(Schema::hasTable('newcollection'))->toBeFalse();

    Schema::createIfNotExists('newcollection', function (Blueprint $index) {
        // Define fields, settings, and mappings
    });

    expect(Schema::hasTable('newcollection'))->toBeTrue();
});

it('creates a new index with callback', function () {
    Schema::create('newcollection', function ($table) {
        expect($table)->toBeInstanceOf(Blueprint::class);
    });
    expect(Schema::hasTable('newcollection'))->toBeTrue();
});

it('drops an existing index', function () {
    Schema::create('newcollection');
    Schema::drop('newcollection');
    expect(Schema::hasTable('newcollection'))->toBeFalse();
});

it('applies Blueprint instance in table method', function () {
    Schema::create('newcollection');
    Schema::table('newcollection', function ($table) {
        expect($table)->toBeInstanceOf(Blueprint::class);
    });
});

it('sets a primary key on a index', function () {
    Schema::create('newcollection', function (Blueprint $table) {
        $table->string('mykey');
    });

    $index = getIndexMapping('newcollection');
    expect($index['mykey']['type'])->toBe('text');
});

it('adds soft deletes to a collection', function () {
    Schema::create('newcollection', function (Blueprint $table) {
        $table->softDeletes();
    });

    // Alias for table may as well test it here.
    Schema::index('newcollection', function (Blueprint $table) {
        $table->string('email');
    });

    $index = getIndexMapping('newcollection');
    expect($index['email']['type'])->toBe('text')
        ->and($index['deleted_at']['type'])->toBe('date');
});

it('can add index settings, Meta Data, and an Analyser', function () {
    Schema::create('newcollection', function (Blueprint $table) {
        $table->withSetting('number_of_shards', 3);
        $table->meta(['class' => 'MyApp2::User3']);

        $table->addAnalyzer('contacts')
            ->type('custom')
            ->tokenizer('punctuation')
            ->filter(['lowercase', 'english_stop'])
            ->charFilter(['emoticons']);
        $table->addAnalyzer('autocomplete')
            ->type('custom')
            ->tokenizer('standard')
            ->filter([
                'lowercase',
                'english_stop',
            ]);
        $table->addTokenizer('punctuation')
            ->type('pattern')
            ->pattern('[ .,!?]');

        $table->addCharFilter('emoticons')
            ->type('mapping')
            ->mappings([':) => _happy_', ':( => _sad_']);
        $table->addFilter('english_stop')
            ->type('stop')
            ->stopwords('_english_');
    });

    $mapping = DB::elastic()->indices()->get(['index' => 'newcollection'])->asArray();
    expect($mapping['newcollection']['mappings']['_meta']['class'])->toBe('MyApp2::User3')
        ->and($mapping['newcollection']['settings']['index']['number_of_shards'])->toBe('3')
        ->and($mapping['newcollection']['settings']['index']['analysis']['analyzer'])->toHaveKeys(['autocomplete', 'contacts']);
});

it('maps default laravel schemas to ES', function () {
    Schema::create('newcollection', function (Blueprint $table) {
        $table->bigInteger('big_integer');
        $table->bigInteger('big_integer_unsigned', unsigned: true);
        $table->binary('binary');
        $table->boolean('boolean');
        $table->char('char');
        $table->dateTimeTz('date_time_tz');
        $table->date('date');
        $table->decimal('decimal', places: 6);
        $table->double('double');
        $table->enum('enum', ['easy', 'hard']);
        $table->float('float_25', precision: 20);
        $table->float('float_53', precision: 53);
        $table->float('float_70', precision: 70);
        $table->foreignId('foreign_id');
        $table->foreignIdFor(User::class);
        $table->foreignUlid('foreignUlid');
        $table->foreignUuid('foreignUuid');
        $table->geography('geography_point', subtype: 'point', srid: 4326);
        $table->geography('geography_shape', subtype: 'shape', srid: 4326);
        $table->id();
        $table->integer('integer');
        $table->integer('integer_unsigned', unsigned: true);
        $table->ipAddress('ip_address');
        $table->longText('long_text');
        $table->macAddress('mac_address');
        $table->mediumInteger('medium_integer');
        $table->mediumInteger('medium_integer_unsigned', unsigned: true);
        $table->mediumText('medium_text');
        $table->morphs('morphs');
        $table->nullableTimestamps(precision: 0);
        $table->rememberToken();
        $table->smallInteger('small_integer');
        $table->softDeletesTz('soft_deletes_tz', precision: 0);
        $table->softDeletes('soft_deletes', precision: 0);
        $table->string('string', length: 100);
        $table->text('text');
        $table->text('text_with_keyword', true);
        $table->timeTz('time_tz');
        $table->time('time');
        $table->timestampTz('timestamp_tz');
        $table->timestamp('timestamp');
        $table->tinyInteger('tiny_integer');
        $table->tinyText('tiny_text');
        $table->unsignedBigInteger('unsigned_big_integer');
        $table->unsignedInteger('unsigned_integer');
        $table->unsignedMediumInteger('unsigned_medium_integer');
        $table->unsignedSmallInteger('unsigned_small_integer');
        $table->unsignedTinyInteger('unsigned_tiny_integer');
        $table->ulidMorphs('ulid_morphs');
        $table->uuidMorphs('uuid_morphs');
        $table->ulid('ulid');
        $table->uuid('uuid');
        $table->year('year');

    });

    $index = getIndexMapping('newcollection');
    expect($index['big_integer']['type'])->toBe('long')
        ->and($index['big_integer_unsigned']['type'])->toBe('unsigned_long')
        ->and($index['binary']['type'])->toBe('binary')
        ->and($index['boolean']['type'])->toBe('boolean')
        ->and($index['char']['type'])->toBe('keyword')
        ->and($index['date_time_tz']['type'])->toBe('date')
        ->and($index['decimal']['type'])->toBe('scaled_float')
        ->and($index['decimal']['scaling_factor'])->toBe(1000000.0)
        ->and($index['enum']['type'])->toBe('keyword')
        ->and($index['enum']['script']['source'])->toContain("def allowed = ['easy', 'hard']")
        ->and($index['float_25']['type'])->toBe('float')
        ->and($index['float_53']['type'])->toBe('double')
        ->and($index['float_70']['type'])->toBe('scaled_float')
        ->and($index['float_70']['scaling_factor'])->toBeGreaterThanOrEqual(1.0E+60)
        ->and($index['foreign_id']['type'])->toBe('keyword')
        ->and($index['foreign_ulid']['type'])->toBe('keyword')
        ->and($index['foreign_uuid']['type'])->toBe('keyword')
        ->and($index['user_id']['type'])->toBe('keyword')
        ->and($index['geography_point']['type'])->toBe('geo_point')
        ->and($index['geography_shape']['type'])->toBe('geo_shape')
        ->and($index['id']['type'])->toBe('keyword')
        ->and($index['integer']['type'])->toBe('integer')
        ->and($index['integer_unsigned']['type'])->toBe('unsigned_long')
        ->and($index['ip_address']['type'])->toBe('ip')
        ->and($index['long_text']['type'])->toBe('text')
        ->and($index['mac_address']['type'])->toBe('keyword')
        ->and($index['medium_integer']['type'])->toBe('integer')
        ->and($index['medium_integer_unsigned']['type'])->toBe('unsigned_long')
        ->and($index['medium_text']['type'])->toBe('text')
        ->and($index['morphs_id']['type'])->toBe('keyword')
        ->and($index['morphs_type']['type'])->toBe('keyword')
        ->and($index['created_at']['type'])->toBe('date')
        ->and($index['updated_at']['type'])->toBe('date')
        ->and($index['remember_token']['type'])->toBe('keyword')
        ->and($index['small_integer']['type'])->toBe('short')
        ->and($index['soft_deletes']['type'])->toBe('date')
        ->and($index['soft_deletes_tz']['type'])->toBe('date')
        ->and($index['text']['type'])->toBe('text')
        ->and($index['text_with_keyword']['type'])->toBe('text')
        ->and($index['text_with_keyword']['fields']['keyword']['type'])->toBe('keyword')
        ->and($index['text_with_keyword']['fields']['keyword']['ignore_above'])->toBe(256)
        ->and($index['time_tz']['type'])->toBe('date')
        ->and($index['time_tz']['format'])->toBe('hour_minute_second||strict_hour_minute_second||HH:mm:ssZ')
        ->and($index['time']['type'])->toBe('date')
        ->and($index['time']['format'])->toBe('hour_minute_second||strict_hour_minute_second||HH:mm:ssZ')
        ->and($index['timestamp_tz']['type'])->toBe('date')
        ->and($index['timestamp']['type'])->toBe('date')
        ->and($index['tiny_integer']['type'])->toBe('byte')
        ->and($index['tiny_text']['type'])->toBe('text')
        ->and($index['unsigned_big_integer']['type'])->toBe('unsigned_long')
        ->and($index['unsigned_integer']['type'])->toBe('unsigned_long')
        ->and($index['unsigned_medium_integer']['type'])->toBe('unsigned_long')
        ->and($index['unsigned_tiny_integer']['type'])->toBe('unsigned_long')
        ->and($index['ulid_morphs_id']['type'])->toBe('keyword')
        ->and($index['ulid_morphs_type']['type'])->toBe('keyword')
        ->and($index['uuid_morphs_id']['type'])->toBe('keyword')
        ->and($index['uuid_morphs_type']['type'])->toBe('keyword')
        ->and($index['ulid']['type'])->toBe('keyword')
        ->and($index['uuid']['type'])->toBe('keyword')
        ->and($index['year']['type'])->toBe('date')
        ->and($index['year']['format'])->toBe('yyyy');
});

it('maps ES schemas', function () {
    Schema::create('newcollection', function (Blueprint $table) {
        $table->dateRange('date_range');
        $table->doubleRange('double_range');
        $table->floatRange('float_range');
        $table->geoPoint('geo_point');
        $table->geoShape('geo_shape');
        $table->integerRange('integer_range');
        $table->ip('ip');
        $table->ipRange('ip_range');
        $table->long('long');
        $table->longRange('long_range');
        $table->nested('nested');
        $table->percolator('percolator');
        $table->range('integer_range', 'integer_range_2');
        $table->property('text', 'custom_property');
    });

    $index = getIndexMapping('newcollection');
    expect($index['date_range']['type'])->toBe('date_range')
        ->and($index['double_range']['type'])->toBe('double_range')
        ->and($index['float_range']['type'])->toBe('float_range')
        ->and($index['geo_point']['type'])->toBe('geo_point')
        ->and($index['geo_shape']['type'])->toBe('geo_shape')
        ->and($index['integer_range']['type'])->toBe('integer_range')
        ->and($index['integer_range_2']['type'])->toBe('integer_range')
        ->and($index['ip_range']['type'])->toBe('ip_range')
        ->and($index['long']['type'])->toBe('long')
        ->and($index['long_range']['type'])->toBe('long_range')
        ->and($index['nested']['type'])->toBe('nested')
        ->and($index['percolator']['type'])->toBe('percolator')
        ->and($index['custom_property']['type'])->toBe('text');
});

it('throws Invalid Field Type Exception on schemas that ES cant have.', function ($columnType) {
    Schema::create('newcollection', function (Blueprint $table) use ($columnType) {
        $table->$columnType('test', ['foo']);
    });
})->with([
    'bigIncrements',
    'mediumIncrements',
    'tinyIncrements',
    'smallIncrements',
    'set',
    'json',
    'jsonb',
])->throws(InvalidFieldTypeException::class);

it('validates Boost', function () {
    Schema::create('newcollection', function (Blueprint $table) {
        $table->text('test')->boost('bar');
    });
})->throws(InvalidArgumentException::class);

it('validates dynamic', function () {
    Schema::create('newcollection', function (Blueprint $table) {
        $table->text('test')->dynamic('bar');
    });
})->throws(InvalidArgumentException::class);

it('adds null_value and timestamps with schema', function () {
    Schema::create('newcollection', function (Blueprint $table) {
        $table->string('email');
        $table->keyword('token')->nullValue('NULL');
        $table->timestamp('created_at');
    });

    $index = getIndexMapping('newcollection');
    expect($index['email']['type'])->toBe('text')
        ->and($index['token']['type'])->toBe('keyword')
        ->and($index['created_at']['type'])->toBe('date')
        ->and($index['token']['null_value'])->toBe('NULL');
});

it('validates nullValue on string fields.', function () {
    Schema::create('newcollection', function (Blueprint $table) {
        $table->string('email')->nullValue('NULL');
    });
})->throws(InvalidArgumentException::class);

it('adds geospatial indexes to a collection', function () {
    Schema::create('newcollection', function (Blueprint $table) {
        $table->geoPoint('point', ['ignore_malformed' => true]);
        $table->geoShape('area', ['orientation' => 'right', 'ignore_malformed' => true]);
    });

    $index = getIndexMapping('newcollection');
    expect($index['point']['type'])->toBe('geo_point')
        ->and($index['point']['ignore_malformed'])->toBeTrue()
        ->and($index['area']['type'])->toBe('geo_shape')
        ->and($index['area']['ignore_malformed'])->toBeTrue()
        ->and($index['area']['orientation'])->toBe('RIGHT');

});

it('can create multi-fields', function () {
    Schema::create('newcollection', function (Blueprint $table) {
        $table->text('email')->fields(function (Blueprint $field) {
            $field->keyword('keyword', ['ignore_above' => 256]);
        });
    });

    $index = getIndexMapping('newcollection');
    expect($index['email']['type'])->toBe('text')
        ->and($index['email']['fields']['keyword']['type'])->toBe('keyword')
        ->and($index['email']['fields']['keyword']['ignore_above'])->toBe(256);
});

it('can create add a format', function () {
    Schema::create('newcollection', function (Blueprint $table) {
        $table->date('date')->format('yyyy');
    });

    $index = getIndexMapping('newcollection');
    expect($index['date']['type'])->toBe('date')
        ->and($index['date']['format'])->toBe('yyyy');

});

it('can modify index properties', function () {
    Schema::create('newcollection', function (Blueprint $table) {
        $table->date('date');
        $table->alias('bar_foo');
        $table->dynamic();
        $table->routingRequired();
    });

    $index = DB::elastic()->indices()->getMapping(['index' => 'newcollection'])->asArray();
    expect($index['newcollection']['mappings']['dynamic'])->toBe('true');
    expect($index['newcollection']['mappings']['_routing']['required'])->toBeTrue();

    $alias = DB::elastic()->indices()->getAlias(['index' => 'newcollection'])->asArray();
    expect($alias['newcollection']['aliases'])->toHaveKey('bar_foo');
});

it('adds dummy fields to the collection schema', function () {
    Schema::create('newcollection', function ($collection) {
        $collection->boolean('activated')->default(false);
        $collection->integer('user_id')->unsigned();
    });

    $index = getIndexMapping('newcollection');
    expect($index['activated']['type'])->toBe('boolean')
        ->and($index['activated']['null_value'])->toBeFalse()
        ->and($index['user_id']['type'])->toBe('integer');

});

it('checks if columns exist in a collection', function () {

    DB::connection()->table('newcollection')->insert(['column1' => 'value', 'embed' => ['_id' => 1]]);

    expect(Schema::hasColumn('newcollection', 'column1'))->toBeTrue()
        ->and(Schema::hasColumn('newcollection', 'column2'))->toBeFalse()
        ->and(Schema::hasColumn('newcollection', 'embed._id'))->toBeTrue();
});

it('checks if multiple columns exist in a collection', function () {
    DB::connection()->table('newcollection')->insert([
        ['column1' => 'value1', 'column2' => 'value2'],
        ['column1' => 'value3'],
    ]);

    expect(Schema::hasColumns('newcollection', [
        'column1',
        'column2',
    ]))->toBeTrue()
        ->and(
            Schema::hasColumns('newcollection', [
                'column1',
                'column3',
            ])
        )->toBeFalse();
});

it('retrieves tables and verifies details', function () {
    DB::connection('elasticsearch')->table('newcollection')->insert(['test' => 'value']);
    DB::connection('elasticsearch')->table('newcollection_two')->insert(['test' => 'value']);

    $tables = Schema::getTables();
    expect($tables)->toBeArray()
        ->and(count($tables))->toBeGreaterThanOrEqual(2);

    $found = false;
    foreach ($tables as $table) {
        expect($table)->toHaveKeys(['name', 'status']);
        if ($table['name'] === 'newcollection') {
            expect($table['docs_count'])->toBe('1');
            $found = true;
        }
    }

    if (! $found) {
        $this->fail('Collection "newcollection" not found');
    }
});

it('lists table names', function () {
    DB::connection('elasticsearch')->table('newcollection')->insert(['test' => 'value']);
    DB::connection('elasticsearch')->table('newcollection_two')->insert(['test' => 'value']);

    $tables = Schema::getTableListing();

    expect($tables)->toBeArray()
        ->and(count($tables))->toBeGreaterThanOrEqual(2)
        ->and($tables)->toContain('newcollection', 'newcollection_two');
});

it('lists table mappings', function () {
    DB::connection('elasticsearch')->table('newcollection')->insert(['test' => 'value']);
    DB::connection('elasticsearch')->table('newcollection_two')->insert(['test' => 'value']);

    $tables = Schema::getMappings('newcollection', true);
    expect($tables)->toBeArray()
        ->and(count($tables))->toBe(1)
        ->and($tables)->toHaveKeys(['newcollection']);

    $tables = Schema::getMappings(['newcollection', 'newcollection_two'], true);

    expect($tables)->toBeArray()
        ->and(count($tables))->toBe(2)
        ->and($tables)->toHaveKeys(['newcollection', 'newcollection_two']);
});

it('lists table settings', function () {
    DB::connection('elasticsearch')->table('newcollection')->insert(['test' => 'value']);
    DB::connection('elasticsearch')->table('newcollection_two')->insert(['test' => 'value']);

    $tables = Schema::getSettings('newcollection');

    expect($tables)->toBeArray()
        ->and(count($tables))->toBe(1)
        ->and($tables)->toHaveKeys(['newcollection']);

    $tables = Schema::getSettings(['newcollection', 'newcollection_two']);

    expect($tables)->toBeArray()
        ->and(count($tables))->toBe(2)
        ->and($tables)->toHaveKeys(['newcollection', 'newcollection_two']);
});

it('can reindex data', function () {
    DB::connection('elasticsearch')->table('newcollection')->insert(['test' => 'value']);

    $tables = Schema::reindex('newcollection', 'newcollection_two');

    expect($tables)->toBeArray()
        ->and($tables)->toHaveKeys(['took', 'total', 'created'])
        ->and($tables['created'])->toBe(1);
});

it('gets field maping', function () {
    DB::connection('elasticsearch')->table('newcollection')->insert(['test' => 'value']);

    $field = Schema::getFieldMapping('newcollection', 'test', true);

    expect($field)->toBeArray()
        ->and(count($field))->toBe(1)
        ->and($field['newcollection']['mappings'])->toHaveKeys(['test']);

});

it('should create an index will all numeric type mappings', function () {
    Schema::deleteIfExists('nums_lfg');
    Schema::create('nums_lfg', function (Blueprint $index) {
        $index->long('lfg_long');
        $index->integer('lfg_int');
        $index->short('lfg_short');
        $index->byte('lfg_byte');
        $index->double('lfg_double');
        $index->float('lfg_float');
        $index->halfFloat('lfg_half_float');
        $index->scaledFloat('lfg_scaled_float', 140);
    });

    $mappings = Schema::getMappings('nums_lfg', true);
    $this->assertTrue($mappings['nums_lfg']['mappings']['properties']['lfg_long']['type'] == 'long');
    $this->assertTrue($mappings['nums_lfg']['mappings']['properties']['lfg_int']['type'] == 'integer');
    $this->assertTrue($mappings['nums_lfg']['mappings']['properties']['lfg_short']['type'] == 'short');
    $this->assertTrue($mappings['nums_lfg']['mappings']['properties']['lfg_byte']['type'] == 'byte');
    $this->assertTrue($mappings['nums_lfg']['mappings']['properties']['lfg_double']['type'] == 'double');
    $this->assertTrue($mappings['nums_lfg']['mappings']['properties']['lfg_float']['type'] == 'float');
    $this->assertTrue($mappings['nums_lfg']['mappings']['properties']['lfg_half_float']['type'] == 'half_float');
    $this->assertTrue($mappings['nums_lfg']['mappings']['properties']['lfg_scaled_float']['type'] == 'scaled_float');
    $this->assertTrue($mappings['nums_lfg']['mappings']['properties']['lfg_scaled_float']['scaling_factor'] == 140);
    // clean up
    Schema::deleteIfExists('nums_lfg');
});

function getIndexMapping(string $table)
{
    $mapping = DB::connection('elasticsearch')->elastic()->indices()->getMapping(['index' => $table])->asArray();

    return $mapping[$table]['mappings']['properties'];
}
