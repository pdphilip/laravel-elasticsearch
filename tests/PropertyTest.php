<?php

declare(strict_types=1);

use PDPhilip\Elasticsearch\Tests\Models\HiddenAnimal;

beforeEach(function () {
    HiddenAnimal::executeSchema();
});

it('can hide certain properties', function () {
    HiddenAnimal::create([
        'name' => 'Sheep',
        'country' => 'Ireland',
        'can_be_eaten' => true,
    ]);

    $hiddenAnimal = HiddenAnimal::sole();
    expect($hiddenAnimal)->toBeInstanceOf(HiddenAnimal::class)
        ->and($hiddenAnimal->country)->toBe('Ireland')
        ->and($hiddenAnimal->can_be_eaten)->toBeTrue()
        ->and($hiddenAnimal->toArray())->toHaveKey('name')
        ->and($hiddenAnimal->toArray())->not->toHaveKey('country', 'the country column should be hidden')
        ->and($hiddenAnimal->toArray())->toHaveKey('can_be_eaten');

});
