<?php

namespace PDPhilip\Elasticsearch\DSL;

use Illuminate\Support\Str;

trait IndexInterpreter
{
    public static function buildIndexMap($index, $raw)
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

//        dd($params);

        return $params;

    }

    public static function buildAnalyzerSettings($index, $raw)
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


    public static function catIndices($data, $all = false)
    {
        if (!$all && $data) {
            $indices = $data;
            $data = [];
            foreach ($indices as $index) {
                if (!(substr($index['index'], 0, 1) === ".")) {
                    $data[] = $index;
                }
            }
        }

        return $data;

    }

}
