 <?php

  use Illuminate\Support\Facades\DB;
use PDPhilip\Elasticsearch\DSL\exceptions\QueryException;
use PDPhilip\Elasticsearch\Schema\IndexBlueprint;
use PDPhilip\Elasticsearch\Schema\Schema;
use Workbench\App\Models\Product;

it('re-indexs data', function () {
    //Drop the Schema
    Schema::deleteIfExists('products');
    Schema::deleteIfExists('holding_products');

    //Create the Schema For this data set before each test
    $productsSchema = Schema::create('products', function (IndexBlueprint $index) {
        $index->text('name');
        $index->float('price');
        $index->integer('status');
        $index->date('created_at');
        $index->date('updated_at');
    });

    $productsHoldingSchema = Schema::create('holding_products', function (IndexBlueprint $index) {
        $index->text('name');
        $index->float('price');
        $index->integer('status');
        $index->geo('manufacturer.location');
        $index->date('created_at');
        $index->date('updated_at');
    });

    expect(! empty($productsSchema['products']['mappings']))->toBeTrue()
        ->and(! empty($productsSchema['products']['settings']))->toBeTrue()
        ->and(! empty($productsHoldingSchema['holding_products']['mappings']))->toBeTrue()
        ->and(! empty($productsHoldingSchema['holding_products']['mappings']['properties']['manufacturer']['properties']['location']['type'] == 'geo_point'))->toBeTrue()
        ->and(! empty($productsHoldingSchema['holding_products']['settings']))->toBeTrue();

    $pf = Product::factory()->count(100)->make();
    $pf->each(function ($product) {
        $product->saveWithoutRefresh();
    });
    sleep(2);
    $find = Product::all();

    expect(count($find) === 100)->toBeTrue();

    try {
        Product::filterGeoPoint('manufacturer.location', '10000km', [0, 0])->get();
    } catch (QueryException $exception) {
        expect($exception->getMessage())->toContain('failed to find geo field')
            ->and($exception->getDetails())->toBeArray('failed to find geo field');
    }

    $reindex = Schema::reIndex('products', 'holding_products');
    expect($reindex->data['created'] == 100)->toBeTrue();

    sleep(2);
    $findOld = DB::connection('elasticsearch')->table('products')->count();
    $findNew = DB::connection('elasticsearch')->table('holding_products')->count();

    expect($findOld === 100)->toBeTrue()
        ->and($findNew === 100)->toBeTrue();

    Schema::deleteIfExists('products');
    expect(Schema::hasIndex('products'))->toBeFalse();

    sleep(2);
    //Now let's create the products index again but with proper mapping
    $product = Schema::create('products', function (IndexBlueprint $index) {
        $index->text('name');
        $index->float('price');
        $index->integer('status');
        $index->geo('manufacturer.location');
        $index->date('created_at');
        $index->date('updated_at');
    });

    expect(! empty($product['products']['mappings']))->toBeTrue()
        ->and(! empty($product['products']['settings']))->toBeTrue();

    //now we move new to old.
    $reindex = Schema::reIndex('holding_products', 'products');
    expect($reindex->data['created'] == 100)->toBeTrue();
    //Sleep to allow ES to catch up
    sleep(2);

    $countOriginal = DB::connection('elasticsearch')->table('products')->count();
    $countHolding = DB::connection('elasticsearch')->table('holding_products')->count();

    expect($countOriginal === 100)->toBeTrue()
        ->and($countHolding === 100)->toBeTrue();

    $found = Product::filterGeoPoint('manufacturer.location', '10000km', [0, 0])->get();
    expect($found->isNotEmpty())->toBeTrue();

    //Cleanup
    Schema::deleteIfExists('products');
    Schema::deleteIfExists('holding_products');

    expect(Schema::hasIndex('products'))->toBeFalse()
        ->and(Schema::hasIndex('holding_products'))->toBeFalse();

})->group('schema')->todo();
