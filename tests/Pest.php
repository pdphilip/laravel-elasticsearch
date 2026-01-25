<?php

declare(strict_types=1);

use PDPhilip\Elasticsearch\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/*
|--------------------------------------------------------------------------
| ID Strategy Configuration
|--------------------------------------------------------------------------
|
| Set ES_CLIENT_SIDE_IDS=true in your environment to run tests with
| client-generated UUIDs instead of Elasticsearch-generated IDs.
|
| Example:
|   ES_CLIENT_SIDE_IDS=true ./vendor/bin/pest
|
*/
