<?php

namespace PDPhilip\Elasticsearch\Laravel;

use PDPhilip\Elasticsearch\Helpers\Helpers;

$laravelVersion = Helpers::getLaravelMajorVersion();

class_alias("PDPhilip\\Elasticsearch\\Laravel\\v{$laravelVersion}\\Connection\\ConnectionCompatibility", 'PDPhilip\\Elasticsearch\\Laravel\\Compatibility\\Connection\\ConnectionCompatibility');
class_alias("PDPhilip\\Elasticsearch\\Laravel\\v{$laravelVersion}\\Schema\\BuilderCompatibility", 'PDPhilip\\Elasticsearch\\Laravel\\Compatibility\\Schema\\BuilderCompatibility');
class_alias("PDPhilip\\Elasticsearch\\Laravel\\v{$laravelVersion}\\Schema\\BlueprintCompatibility", 'PDPhilip\\Elasticsearch\\Laravel\\Compatibility\\Schema\\BlueprintCompatibility');
class_alias("PDPhilip\\Elasticsearch\\Laravel\\v{$laravelVersion}\\Schema\\GrammarCompatibility", 'PDPhilip\\Elasticsearch\\Laravel\\Compatibility\\Schema\\GrammarCompatibility');
