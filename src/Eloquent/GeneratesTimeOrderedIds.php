<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Carbon;
use PDPhilip\Elasticsearch\Utils\TimeOrderedUUIDGenerator;

/**
 * Generates time-ordered, sortable IDs for Elasticsearch models.
 *
 * Use this trait when you need IDs that sort chronologically across
 * multiple processes/workers — ideal for high-volume APIs where
 * time-sequenced analytics matter.
 *
 * IDs are 20 characters, URL-safe, and sort lexicographically in
 * the same order they were created (at millisecond granularity).
 *
 * Usage:
 *   class TrackingEvent extends Model {
 *       use GeneratesTimeOrderedIds;
 *   }
 *
 *   $event->getRecordTimestamp(); // 1771160093773 (ms)
 *   $event->getRecordDate();     // Carbon instance
 */
trait GeneratesTimeOrderedIds
{
    use HasUuids;

    public function initializeGeneratesTimeOrderedIds(): void
    {
        $this->generatesUniqueIds = true;
    }

    public function newUniqueId(): string
    {
        return TimeOrderedUUIDGenerator::generate();
    }

    public function getRecordTimestamp(): ?int
    {
        return TimeOrderedUUIDGenerator::extractTimestamp($this->id);
    }

    public function getRecordDate(): ?Carbon
    {
        $ms = $this->getRecordTimestamp();

        if ($ms === null) {
            return null;
        }

        return Carbon::createFromTimestampMs($ms);
    }
}
