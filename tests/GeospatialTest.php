<?php

declare(strict_types=1);

use PDPhilip\Elasticsearch\Tests\Models\Location;

beforeEach(function () {
    Location::executeSchema();

    Location::create([
        'name' => 'Picadilly',
        'location' => [
            'type' => 'LineString',
            'coordinates' => [
                [
                    -0.1450383,
                    51.5069158,
                ],
                [
                    -0.1367563,
                    51.5100913,
                ],
                [
                    -0.1304123,
                    51.5112908,
                ],
            ],
        ],
    ]);

    Location::create([
        'name' => 'St. James\'s Palace',
        'point' => [
            -0.1392173,
            51.5045037,
        ],
    ]);

});

it('finds locations within a defined polygon', function () {

    $locations = Location::whereGeoBox('location', ['lon' => -0.1450383, 'lat' => 51.5069158], ['lon' => -0.1270247, 'lat' => 51.5013233])->get();

    expect($locations->count())->toBe(1);

    $locations->get()->each(function ($item) {
        expect($item->name)->toBe('StJamesPalace');
    });
})->todo('need to add more checks around geo bounding box');

it('finds locations near a point within max distance', function () {
    $locations = Location::whereGeoDistance('point', '200m', ['lat' => 51.5049537, 'lon' => -0.1392173])->get();

    expect($locations->count())->toBe(1);

    $locations->each(function ($item) {
        expect($item->name)->toBe('St. James\'s Palace');
    });
});

it('order by distance', function () {
    Location::create([
        'name' => 'Paris',
        'point' => [
            2.3488,
            48.85341,
        ],
    ]);

    Location::create([
        'name' => 'London',
        'point' => [
            -0.12574,
            51.50853,
        ],
    ]);

    $locations = Location::orderByGeo('point', [-0.12574, 51.50853])->get();

    expect($locations->count())->toBe(4)
        ->and($locations->first()->name)->toBe('London');

    $locations = Location::orderByGeo('point', ['lat' => 51.50853, 'lon' => -0.12574])->get();

    expect($locations->count())->toBe(4)
        ->and($locations->first()->name)->toBe('London');

    $locations = Location::orderByGeoDesc('point', [-0.12574, 51.50853], ['unit' => 'mi', 'distance_type' => 'plane', 'mode' => 'median'])->get();

    expect($locations->count())->toBe(4)
        ->and($locations->first()->name)->toBe('Picadilly');

});
