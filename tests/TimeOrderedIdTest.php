<?php

declare(strict_types=1);

use PDPhilip\Elasticsearch\Utils\TimeOrderedUUIDGenerator;

// ─────────────────────────────────────────────────────────────
//  Format
// ─────────────────────────────────────────────────────────────

it('generates 20-character IDs', function () {
    $id = TimeOrderedUUIDGenerator::generate();

    expect($id)->toHaveLength(20);
});

it('generates URL-safe characters only', function () {
    $ids = collect(range(1, 100))->map(fn () => TimeOrderedUUIDGenerator::generate());

    $ids->each(function ($id) {
        expect($id)->toMatch('/^[A-Za-z0-9\-_]+$/');
    });
});

it('generates unique IDs', function () {
    $ids = collect(range(1, 10000))->map(fn () => TimeOrderedUUIDGenerator::generate());

    expect($ids->unique())->toHaveCount(10000);
});

// ─────────────────────────────────────────────────────────────
//  Ordering
// ─────────────────────────────────────────────────────────────

it('generates IDs that sort in creation order', function () {
    $ids = [];
    for ($i = 0; $i < 1000; $i++) {
        $ids[] = TimeOrderedUUIDGenerator::generate();
    }

    $sorted = $ids;
    sort($sorted);

    expect($ids)->toBe($sorted);
});

it('maintains sort order across millisecond boundaries', function () {
    $first = TimeOrderedUUIDGenerator::generate();
    usleep(2000); // 2ms gap
    $second = TimeOrderedUUIDGenerator::generate();

    expect($first < $second)->toBeTrue();
});

// ─────────────────────────────────────────────────────────────
//  Timestamp extraction
// ─────────────────────────────────────────────────────────────

it('extracts timestamp from a valid ID', function () {
    $before = (int) (microtime(true) * 1000);
    $id = TimeOrderedUUIDGenerator::generate();
    $after = (int) (microtime(true) * 1000);

    $extracted = TimeOrderedUUIDGenerator::extractTimestamp($id);

    expect($extracted)->toBeInt()
        ->and($extracted)->toBeGreaterThanOrEqual($before)
        ->and($extracted)->toBeLessThanOrEqual($after);
});

it('extracts datetime from a valid ID', function () {
    $before = now();
    usleep(1000);
    $id = TimeOrderedUUIDGenerator::generate();
    usleep(1000);
    $after = now();

    $dt = TimeOrderedUUIDGenerator::extractDateTime($id);

    expect($dt)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($dt->getTimestamp())->toBeGreaterThanOrEqual($before->timestamp)
        ->and($dt->getTimestamp())->toBeLessThanOrEqual($after->timestamp);
});

it('roundtrips timestamp through encode and decode', function () {
    $ids = collect(range(1, 100))->map(function () {
        usleep(100);

        return TimeOrderedUUIDGenerator::generate();
    });

    $ids->each(function ($id) {
        $ms = TimeOrderedUUIDGenerator::extractTimestamp($id);
        expect($ms)->toBeInt()
            ->and($ms)->toBeGreaterThan(1577836800000)
            ->and($ms)->toBeLessThan(4102444800000);
    });
});

// ─────────────────────────────────────────────────────────────
//  Validation
// ─────────────────────────────────────────────────────────────

it('validates its own IDs', function () {
    $ids = collect(range(1, 50))->map(fn () => TimeOrderedUUIDGenerator::generate());

    $ids->each(function ($id) {
        expect(TimeOrderedUUIDGenerator::isValid($id))->toBeTrue();
    });
});

it('rejects wrong-length strings', function () {
    expect(TimeOrderedUUIDGenerator::isValid(''))->toBeFalse()
        ->and(TimeOrderedUUIDGenerator::isValid('short'))->toBeFalse()
        ->and(TimeOrderedUUIDGenerator::isValid('this-is-way-too-long-for-an-id'))->toBeFalse();
});

it('rejects random 20-char strings', function () {
    expect(TimeOrderedUUIDGenerator::isValid('abcdefghijklmnopqrst'))->toBeFalse()
        ->and(TimeOrderedUUIDGenerator::isValid('ZZZZZZZZZZZZZZZZZZZZ'))->toBeFalse()
        ->and(TimeOrderedUUIDGenerator::isValid('00000000000000000000'))->toBeFalse();
});

it('returns null timestamp for invalid IDs', function () {
    expect(TimeOrderedUUIDGenerator::extractTimestamp('not-a-valid-id'))->toBeNull()
        ->and(TimeOrderedUUIDGenerator::extractTimestamp('abcdefghijklmnopqrst'))->toBeNull()
        ->and(TimeOrderedUUIDGenerator::extractTimestamp(''))->toBeNull();
});

it('returns null datetime for invalid IDs', function () {
    expect(TimeOrderedUUIDGenerator::extractDateTime('not-a-valid-id'))->toBeNull()
        ->and(TimeOrderedUUIDGenerator::extractDateTime('abcdefghijklmnopqrst'))->toBeNull();
});
