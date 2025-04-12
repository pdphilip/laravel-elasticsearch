<?php

namespace PDPhilip\Elasticsearch\Tests\Benchmarks;

use AllowDynamicProperties;
use PDPhilip\Elasticsearch\Utils\Helpers;
use PDPhilip\Elasticsearch\Utils\TimeBasedUUIDGenerator;
use PDPhilip\Elasticsearch\Utils\Timer;

#[AllowDynamicProperties] class UuidBenchmarks
{
    use Timer;

    public function __construct($memoryLimit = '2048M')
    {
        ini_set('memory_limit', $memoryLimit);
    }

    public function getId($key)
    {
        return match ($key) {
            'uuid' => Helpers::uuid(),
            'eid' => TimeBasedUUIDGenerator::generate(),
            default => null,
        };
    }

    public function testCollisions($type = 'base', $count = 1000000)
    {
        return $this->processTest($count, function () use ($type) {
            return $this->getId($type);
        });
    }

    public function testSpeed($type = 'base', $count = 1000000)
    {
        return $this->processTestRaw($count, function () use ($type) {
            return $this->getId($type);
        });
    }

    protected function processTest($count, $callback)
    {
        $this->startTimer();
        $ids = [];
        while ($count) {
            $ids[] = $callback();
            $count--;
        }
        $time = $this->getTime();
        $uniqueIds = array_unique($ids);

        $totalIds = count($ids);
        $totalUniqueIds = count($uniqueIds);
        $collisions = count($ids) - count($uniqueIds);

        return [
            'time' => $time,
            'total_ids' => $totalIds,
            'total_unique_ids' => $totalUniqueIds,
            'collisions' => $collisions,
            'samples' => [
                'first_10' => array_slice($ids, 0, 10),
                'last_10' => array_slice($ids, -10),
            ],
        ];
    }

    protected function processTestRaw($count, $callback)
    {
        $originalCount = $count;
        $this->startTimer();
        while ($count) {
            $callback();
            $count--;
        }
        $time = $this->getTime();

        return [
            'count' => $originalCount,
            'μs_per_id' => $time['μs'] / $originalCount,
            'time' => $time,
        ];
    }
}
