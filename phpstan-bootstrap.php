<?php

require_once __DIR__.'/vendor/autoload.php';

class_alias('PDPhilip\\Elasticsearch\\Laravel\\v11\\Connection\\ConnectionCompatibility', 'PDPhilip\\Elasticsearch\\Laravel\\Compatibility\\Connection\\ConnectionCompatibility');
class_alias('PDPhilip\\Elasticsearch\\Laravel\\v11\\Schema\\BlueprintCompatibility', 'PDPhilip\\Elasticsearch\\Laravel\\Compatibility\\Schema\\BlueprintCompatibility');
class_alias('PDPhilip\\Elasticsearch\\Laravel\\v11\\Schema\\BuilderCompatibility', 'PDPhilip\\Elasticsearch\\Laravel\\Compatibility\\Schema\\BuilderCompatibility');
class_alias('PDPhilip\\Elasticsearch\\Laravel\\v11\\Schema\\GrammarCompatibility', 'PDPhilip\\Elasticsearch\\Laravel\\Compatibility\\Schema\\GrammarCompatibility');
