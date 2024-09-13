<?php

declare(strict_types=1);

use PDPhilip\Elasticsearch\Schema\Schema;
use Workbench\App\Models\HiddenAnimal;

beforeEach(function () {
    Schema::deleteIfExists('hidden_animals');
});

test('Can Hide Certain Properties', function () {
    HiddenAnimal::create([
        'name' => 'Sheep',
        'country' => 'Ireland',
        'can_be_eaten' => true,
    ]);

    $hiddenAnimal = HiddenAnimal::sole();
    expect($hiddenAnimal)->toBeInstanceOf(HiddenAnimal::class)
        ->and($hiddenAnimal->country)->toBe('Ireland')
        ->and($hiddenAnimal->can_be_eaten)->toBeTrue()
        ->and($hiddenAnimal->toArray())->toHaveKeys(['name', 'can_be_eaten'])
        ->and(! array_key_exists('country', $hiddenAnimal->toArray()))->toBeTrue();

});
