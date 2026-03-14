<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->modelsPath = app_path('Models');

    if (! File::isDirectory($this->modelsPath)) {
        File::makeDirectory($this->modelsPath, 0755, true);
    }
});

afterEach(function () {
    File::cleanDirectory($this->modelsPath);
});

it('creates a model file', function () {
    $this->artisan('elastic:make', ['name' => 'Product'])
        ->assertSuccessful();

    $path = app_path('Models/Product.php');
    expect(File::exists($path))->toBeTrue();

    $content = File::get($path);
    expect($content)
        ->toContain('namespace App\Models;')
        ->toContain('class Product extends Eloquent')
        ->toContain("protected \$connection = 'elasticsearch';");
});

it('fails when the model already exists', function () {
    $path = app_path('Models/Product.php');
    File::put($path, '<?php // existing');

    $this->artisan('elastic:make', ['name' => 'Product'])
        ->assertFailed();
});

it('creates a model in a subdirectory', function () {
    $this->artisan('elastic:make', ['name' => 'Elastic/SearchLog'])
        ->assertSuccessful();

    $path = app_path('Models/Elastic/SearchLog.php');
    expect(File::exists($path))->toBeTrue();

    $content = File::get($path);
    expect($content)
        ->toContain('namespace App\Models\Elastic;')
        ->toContain('class SearchLog extends Eloquent');
});

it('converts the name to StudlyCase', function () {
    $this->artisan('elastic:make', ['name' => 'audit_log'])
        ->assertSuccessful();

    $path = app_path('Models/AuditLog.php');
    expect(File::exists($path))->toBeTrue();

    $content = File::get($path);
    expect($content)->toContain('class AuditLog extends Eloquent');
});

it('includes the mapping definition from the stub', function () {
    $this->artisan('elastic:make', ['name' => 'Event'])
        ->assertSuccessful();

    $content = File::get(app_path('Models/Event.php'));
    expect($content)
        ->toContain('implements HasMappingDefinition')
        ->toContain('public static function mappingDefinition(Blueprint $index): void')
        ->toContain("\$index->date('created_at');")
        ->toContain("\$index->date('updated_at');");
});

it('includes the docblock from the stub', function () {
    $this->artisan('elastic:make', ['name' => 'Metric'])
        ->assertSuccessful();

    $content = File::get(app_path('Models/Metric.php'));
    expect($content)
        ->toContain('App\Models\Metric')
        ->toContain('@property string $id')
        ->toContain('@property Carbon $created_at')
        ->toContain('@property Carbon $updated_at');
});

it('creates nested subdirectories', function () {
    $this->artisan('elastic:make', ['name' => 'Analytics/Events/ClickEvent'])
        ->assertSuccessful();

    $path = app_path('Models/Analytics/Events/ClickEvent.php');
    expect(File::exists($path))->toBeTrue();

    $content = File::get($path);
    expect($content)
        ->toContain('namespace App\Models\Analytics\Events;')
        ->toContain('class ClickEvent extends Eloquent');
});
