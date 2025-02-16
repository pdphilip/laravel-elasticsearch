<?php

namespace PDPhilip\Elasticsearch\Tests\Benchmarks;

use PDPhilip\Elasticsearch\Helpers\Helpers;
use PDPhilip\Elasticsearch\Utils\TimeBasedUUIDGenerator;
use PDPhilip\Elasticsearch\Utils\Timer;

class UuidBenchmarks
{
    use Timer;

    public function __construct($memoryLimit = '2048M')
    {
        ini_set('memory_limit', $memoryLimit);
    }

    public function testUuidStandard($count = 1000000)
    {
        return $this->processTest($count, function () {
            return Helpers::uuid();
        });
    }

    public function testUuidTimebased($count = 1000000)
    {
        return $this->processTest($count, function () {
            return Helpers::timeBasedUUID();
        });
    }

    public function testUuidTimebasedInstantiated($count = 1000000)
    {
        $generator = new TimeBasedUUIDGenerator;

        return $this->processTest($count, function () use ($generator) {
            return $generator->getBase64UUID();
        });
    }

    public function testUuidStandardRaw($count = 1000000)
    {
        return $this->processTestRaw($count, function () {
            return Helpers::uuid();
        });
    }

    public function testUuidTimebasedRaw($count = 1000000)
    {
        return $this->processTestRaw($count, function () {
            return Helpers::timeBasedUUID();
        });
    }

    public function testUuidTimebasedInstantiatedRaw($count = 1000000)
    {
        $generator = new TimeBasedUUIDGenerator;

        return $this->processTestRaw($count, function () use ($generator) {
            return $generator->getBase64UUID();
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
