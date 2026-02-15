<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use PDPhilip\Elasticsearch\Tests\Models\TrackingEvent;
use PDPhilip\Elasticsearch\Utils\TimeOrderedUUIDGenerator;

beforeEach(function () {
    TrackingEvent::executeSchema();
});

// ─────────────────────────────────────────────────────────────
//  ID generation on create
// ─────────────────────────────────────────────────────────────

it('generates a time-ordered ID on create', function () {
    $event = TrackingEvent::create(['event' => 'page_view', 'payload' => 'test']);

    expect($event->id)->toBeString()
        ->and($event->id)->toHaveLength(20)
        ->and(TimeOrderedUUIDGenerator::isValid($event->id))->toBeTrue();
});

it('generates a time-ordered ID on new + save', function () {
    $event = new TrackingEvent;
    $event->event = 'click';
    $event->save();

    expect($event->id)->toHaveLength(20)
        ->and(TimeOrderedUUIDGenerator::isValid($event->id))->toBeTrue();
});

it('assigns ID before save so it can be returned immediately', function () {
    $event = new TrackingEvent;

    expect($event->id)->toHaveLength(20)
        ->and(TimeOrderedUUIDGenerator::isValid($event->id))->toBeTrue();
});

it('preserves the ID across save and find', function () {
    $event = TrackingEvent::create(['event' => 'signup']);
    $id = $event->id;

    $found = TrackingEvent::find($id);

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($id)
        ->and($found->event)->toBe('signup');
});

it('generates unique IDs for multiple records', function () {
    $ids = [];
    for ($i = 0; $i < 20; $i++) {
        $ids[] = TrackingEvent::create(['event' => "event_$i"])->id;
    }

    expect(array_unique($ids))->toHaveCount(20);
});

// ─────────────────────────────────────────────────────────────
//  getRecordTimestamp / getRecordDate
// ─────────────────────────────────────────────────────────────

it('returns record timestamp in milliseconds', function () {
    $before = (int) (microtime(true) * 1000);
    $event = TrackingEvent::create(['event' => 'test']);
    $after = (int) (microtime(true) * 1000);

    $ts = $event->getRecordTimestamp();

    expect($ts)->toBeInt()
        ->and($ts)->toBeGreaterThanOrEqual($before)
        ->and($ts)->toBeLessThanOrEqual($after);
});

it('returns record date as Carbon', function () {
    $event = TrackingEvent::create(['event' => 'test']);

    $date = $event->getRecordDate();

    expect($date)->toBeInstanceOf(Carbon::class)
        ->and($date->isToday())->toBeTrue();
});

it('returns record date with millisecond precision', function () {
    $event = TrackingEvent::create(['event' => 'test']);

    $ts = $event->getRecordTimestamp();
    $date = $event->getRecordDate();

    expect($date->getTimestampMs())->toBe($ts);
});

// ─────────────────────────────────────────────────────────────
//  Idiot-proof: mixed old/new IDs
// ─────────────────────────────────────────────────────────────

it('returns null timestamp for a record with a non-time-ordered ID', function () {
    // Simulate a record that existed before the trait was added
    $event = new TrackingEvent;
    $event->forceFill(['id' => 'boFxYZwBVwDi2PtlSd7u', 'event' => 'old']);

    expect($event->getRecordTimestamp())->toBeNull();
});

it('returns null date for a record with a non-time-ordered ID', function () {
    $event = new TrackingEvent;
    $event->forceFill(['id' => 'boFxYZwBVwDi2PtlSd7u', 'event' => 'old']);

    expect($event->getRecordDate())->toBeNull();
});

it('returns null for a record with no ID', function () {
    $event = new TrackingEvent;
    // Override the auto-generated ID
    $event->setAttribute('id', null);

    expect($event->getRecordTimestamp())->toBeNull();
});
