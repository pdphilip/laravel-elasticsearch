<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Traits\Bridge;

use PDPhilip\Elasticsearch\Data\Result;

trait SanitizeResponses
{
    private ?array $stashedMeta;

    public function sanitizeGetResponse($response, $params, $softDeleteColumn)
    {
        $data['id'] = $params['id'];
        $softDeleted = false;
        if ($softDeleteColumn) {
            $softDeleted = ! empty($response['_source'][$softDeleteColumn]);
        }

        if (! $response || $softDeleted) {
            //Was not found
            $result = new Result($data, [], $params);
            $result->setError($data['id'].' not found', 404);

            return $result;
        }

        if (! empty($response['_source'])) {
            foreach ($response['_source'] as $key => $value) {
                $data[$key] = $value;
            }
        }
        if ($softDeleteColumn) {
            unset($data[$softDeleteColumn]);
        }

        return new Result($data, [], $params);
    }

    private function sanitizeSearchResponse($response, $params)
    {
        $meta['took'] = $response['took'] ?? 0;
        $meta['timed_out'] = $response['timed_out'];
        $meta['total'] = $response['hits']['total']['value'] ?? 0;
        $meta['max_score'] = $response['hits']['max_score'] ?? 0;
        $meta['shards'] = $response['_shards'] ?? [];
        $data = [];
        if (! empty($response['hits']['hits'])) {
            foreach ($response['hits']['hits'] as $hit) {
                $datum = [];
                $datum['_index'] = $hit['_index'];
                $datum['id'] = $hit['_id'];
                if (! empty($hit['_source'])) {

                    foreach ($hit['_source'] as $key => $value) {
                        $datum[$key] = $value;
                    }
                }
                if (! empty($hit['inner_hits'])) {
                    foreach ($hit['inner_hits'] as $innerKey => $innerHit) {
                        $datum[$innerKey] = $this->filterInnerHits($innerHit);
                    }
                }

                //Meta data
                if (! empty($hit['highlight'])) {
                    $datum['_meta']['highlights'] = $this->sanitizeHighlights($hit['highlight']);
                }

                $datum['_meta']['_index'] = $hit['_index'];
                $datum['_meta']['_id'] = $hit['_id'];
                if (! empty($hit['_score'])) {
                    $datum['_meta']['_score'] = $hit['_score'];
                }
                $datum['_meta']['_query'] = $meta;
                // If we are sorting we need to store it to be able to pass it on in the search after.
                if (! empty($hit['sort'])) {
                    $datum['_meta']['sort'] = $hit['sort'];
                }
                $datum['_meta'] = $this->attachStashedMeta($datum['_meta']);
                $data[] = $datum;
            }
        }

        return new Result($data, $meta, $params);
    }

    private function sanitizeDistinctResponse($response, $columns, $includeDocCount): array
    {
        $keys = [];
        foreach ($columns as $column) {
            $keys[] = 'by_'.$column;
        }

        return $this->processBuckets($columns, $keys, $response, 0, $includeDocCount);
    }

    private function processBuckets($columns, $keys, $response, $index, $includeDocCount, $currentData = []): array
    {
        $data = [];
        if (! empty($response[$keys[$index]]['buckets'])) {
            foreach ($response[$keys[$index]]['buckets'] as $res) {

                $datum = $currentData;

                $col = $columns[$index];
                if (str_contains($col, '.keyword')) {
                    $col = str_replace('.keyword', '', $col);
                }

                $datum[$col] = $res['key'];

                if ($includeDocCount) {
                    $datum[$col.'_count'] = $res['doc_count'];
                }

                if (isset($columns[$index + 1])) {
                    $nestedData = $this->processBuckets($columns, $keys, $res, $index + 1, $includeDocCount, $datum);

                    if (! empty($nestedData)) {
                        $data = array_merge($data, $nestedData);
                    } else {
                        $data[] = $datum;
                    }
                } else {
                    $data[] = $datum;
                }
            }
        }

        return $data;
    }

    private function sanitizeRawAggsResponse($response, $params, $queryTag): Result
    {
        $meta['timed_out'] = $response['timed_out'];
        $meta['total'] = $response['hits']['total']['value'] ?? 0;
        $meta['max_score'] = $response['hits']['max_score'] ?? 0;
        $meta['sorts'] = [];
        $data = [];
        if (! empty($response['aggregations'])) {
            foreach ($response['aggregations'] as $key => $values) {
                $data[$key] = $this->formatAggs($key, $values)[$key];
            }
        }

        return new Result($data, $meta, $params, $queryTag);
    }

    private function formatAggs($key, $values): array
    {
        $data[$key] = [];
        $aggTypes = ['buckets', 'values'];

        foreach ($values as $subKey => $value) {
            if (in_array($subKey, $aggTypes)) {
                $data[$key] = $this->formatAggs($subKey, $value)[$subKey];
            } elseif (is_array($value)) {
                $data[$key][$subKey] = $this->formatAggs($subKey, $value)[$subKey];
            } else {
                $data[$key][$subKey] = $value;
            }
        }

        return $data;
    }

    private function sanitizeHighlights($highlights)
    {
        //remove keyword results
        foreach ($highlights as $field => $vals) {
            if (str_contains($field, '.keyword')) {
                $cleanField = str_replace('.keyword', '', $field);
                if (isset($highlights[$cleanField])) {
                    unset($highlights[$field]);
                } else {
                    $highlights[$cleanField] = $vals;
                }
            }
        }

        return $highlights;
    }

    private function stashMeta($meta): void
    {
        $this->stashedMeta = $meta;
    }

    private function attachStashedMeta($meta): mixed
    {
        if (! empty($this->stashedMeta)) {
            $meta = array_merge($meta, $this->stashedMeta);
        }

        return $meta;
    }

    private function filterInnerHits($innerHit)
    {
        $hits = [];
        foreach ($innerHit['hits']['hits'] as $inner) {
            $innerDatum = [];
            if (! empty($inner['_source'])) {
                foreach ($inner['_source'] as $innerSourceKey => $innerSourceValue) {
                    $innerDatum[$innerSourceKey] = $innerSourceValue;
                }
            }
            $hits[] = $innerDatum;
        }

        return $hits;
    }
}
