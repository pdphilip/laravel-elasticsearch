<?php

namespace PDPhilip\Elasticsearch\DSL;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

trait IndexInterpreter
{
    public function buildIndexMap($index, $raw): array
    {
        $params = [];
        if ($index) {
            $params['index'] = $index;
        }
        if (!empty($raw['settings'])) {
            $params['body']['settings'] = $raw['settings'];
        }
        if (!empty($raw['map'])) {
            foreach ($raw['map'] as $key => $value) {
                $params['body']['mappings'][$key] = $value;
            }
        }
        if (!empty($raw['properties'])) {
            $properties = [];
            foreach ($raw['properties'] as $prop) {
                $field = $prop['field'];
                unset($prop['field']);
                if (!empty($properties[$field])) {
                    $type = $prop['type'];
                    foreach ($prop as $key => $value) {
                        $properties[$field]['fields'][$type][Str::snake($key)] = $value;
                    }
                } else {
                    foreach ($prop as $key => $value) {
                        $properties[$field][Str::snake($key)] = $value;
                    }
                }
            }
            if (!empty($properties)) {
                $params['body']['mappings']['properties'] = $properties;
            }
        }

        return $params;
    }

    public function buildAnalyzerSettings($index, $raw): array
    {
        $params = [];
        $params['index'] = $index;
        $analysis = [];
        if ($raw) {
            foreach ($raw['analysis'] as $setting) {
                $config = $setting['config'];
                $name = $setting['name'];
                unset($setting['config']);
                unset($setting['name']);
                if ($setting) {
                    foreach ($setting as $key => $value) {
                        $analysis[$config][$name][$key] = $value;
                    }
                }
            }
        }

        $params['body']['settings']['analysis'] = $analysis;

        return $params;
    }


    public function catIndices($data, $all = false): array
    {
        if (!$all && $data) {
            $indices = $data;
            $data = [];
            foreach ($indices as $index) {
                if (!(str_starts_with($index['index'], "."))) {
                    $data[] = $index;
                }
            }
        }

        return $data;
    }

    public function cleanData($data): array
    {
        if ($data) {
            array_walk_recursive($data, function (&$item) {
                if ($item instanceof Carbon) {
                    $item = $item->toIso8601String();
                }
            });
        }

        return $data;
    }

}
