<?php

namespace PDPhilip\Elasticsearch\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use PDPhilip\Elasticsearch\Utils\Helpers;

trait GeneratesUuids
{
    use HasUuids;

    public function initializeGeneratesUuids()
    {
        $this->generatesUniqueIds = true;
    }

    public function newUniqueId(): string
    {
        return Helpers::uuid();
    }
}
