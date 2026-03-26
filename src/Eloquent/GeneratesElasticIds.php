<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use PDPhilip\Elasticsearch\Utils\TimeBasedUUIDGenerator;

trait GeneratesElasticIds
{
    use HasUuids;

    public function initializeGeneratesElasticIds(): void
    {
        $this->generatesUniqueIds = true;
    }

    public function newUniqueId(): string
    {
        return TimeBasedUUIDGenerator::generate();
    }
}
