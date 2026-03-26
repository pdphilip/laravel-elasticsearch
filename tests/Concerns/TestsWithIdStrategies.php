<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Tests\Concerns;

use PDPhilip\Elasticsearch\Utils\Helpers;

/**
 * Enables conditional UUID generation for test models based on environment.
 *
 * When ES_CLIENT_SIDE_IDS=true, models will generate UUIDs client-side.
 * Otherwise, Elasticsearch generates the IDs.
 *
 * Usage: Add this trait to test models instead of GeneratesUuids.
 */
trait TestsWithIdStrategies
{
    public function initializeTestsWithIdStrategies(): void
    {
        if ($this->shouldGenerateClientSideIds()) {
            $this->generatesUniqueIds = true;
        }
    }

    public function uniqueIds(): array
    {
        if ($this->shouldGenerateClientSideIds()) {
            return [$this->getKeyName()];
        }

        return [];
    }

    public function newUniqueId(): ?string
    {
        if ($this->shouldGenerateClientSideIds()) {
            return Helpers::uuid();
        }

        return null;
    }

    protected function shouldGenerateClientSideIds(): bool
    {
        return env('ES_CLIENT_SIDE_IDS', false) === true
            || env('ES_CLIENT_SIDE_IDS', false) === 'true';
    }
}
