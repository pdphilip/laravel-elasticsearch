<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Query\Grammar\Concerns;

use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Support\Str;
use PDPhilip\Elasticsearch\Exceptions\BuilderException;
use PDPhilip\Elasticsearch\Query\Builder;

/**
 * Order, sort, and highlight compilation.
 * The stuff that makes results actually useful.
 */
trait CompilesOrders
{
    /**
     * Compile orderBy clauses into ES sort format.
     *
     * @throws BuilderException
     */
    protected function compileOrders(Builder|BaseBuilder $builder, $orders = []): array
    {
        $compiledOrders = [];

        foreach ($orders as $order) {
            $column = $order['column'];
            if (Str::startsWith($column, $builder->from.'.')) {
                $column = Str::replaceFirst($builder->from.'.', '', $column);
            }
            $column = $this->prependParentField($column, $builder);
            $column = $this->getIndexableField($column, $builder);

            $type = $order['type'] ?? 'basic';

            switch ($type) {
                case 'geoDistance':
                    $orderSettings = [
                        $column => $order['options']['coordinates'],
                        'order' => $order['direction'] < 0 ? 'desc' : 'asc',
                    ];
                    if (! empty($order['options']['unit'])) {
                        $orderSettings['unit'] = $order['options']['unit'];
                    }
                    if (! empty($order['options']['mode'])) {
                        $orderSettings['mode'] = $order['options']['mode'];
                    }
                    if (! empty($order['options']['distance_type'])) {
                        $orderSettings['distance_type'] = $order['options']['distance_type'];
                    }
                    $column = '_geo_distance';
                    break;
                default:
                    $orderSettings = [
                        'order' => $order['direction'] < 0 ? 'desc' : 'asc',
                    ];

                    $allowedOptions = ['missing', 'mode', 'nested', 'unmapped_type'];
                    $options = isset($order['options']) ? array_intersect_key($order['options'], array_flip($allowedOptions)) : [];
                    $orderSettings = array_merge($options, $orderSettings);
            }

            $compiledOrders[] = [
                $column => $orderSettings,
            ];
        }

        return $compiledOrders;
    }

    /**
     * Merge additional sort options into compiled orders.
     * Handles the case where orderBy and sort() are both used.
     *
     * @throws BuilderException
     */
    protected function compileSorts(Builder $builder, $sorts, $compiledOrders): array
    {
        foreach ($sorts as $column => $sort) {
            $found = false;
            $column = $this->prependParentField($column, $builder);
            $column = $this->getIndexableField($column, $builder);

            if ($compiledOrders) {
                foreach ($compiledOrders as $i => $compiledOrder) {
                    if (array_key_exists($column, $compiledOrder)) {
                        $compiledOrders[$i][$column] = array_merge($compiledOrder[$column], $sort);
                        $found = true;
                        break;
                    }
                }
            }
            if (! $found) {
                $compiledOrders[] = [$column => $sort];
            }
        }

        return $compiledOrders;
    }

    /**
     * Compile highlight settings.
     * For when you want to show users why they got these results.
     */
    protected function compileHighlight(Builder $builder, mixed $highlight = []): array
    {
        $allowedArgs = [
            'boundary_chars',
            'boundary_max_scan',
            'boundary_scanner',
            'boundary_scanner_locale',
            'encoder',
            'fragmenter',
            'force_source', // @deprecated Removed in Elasticsearch 9.x. Only works with ES 8.x (deprecated since 8.11)
            'fragment_offset',
            'fragment_size',
            'highlight_query',
            'matched_fields',
            'number_of_fragments',
        ];

        $compiledHighlights = $this->getAllowedOptions($highlight['options'], $allowedArgs);

        foreach ($highlight['column'] as $column => $value) {
            if (is_array($value)) {
                $compiledHighlights['fields'][] = [$column => $value];
            } elseif ($value != '*') {
                $compiledHighlights['fields'][] = [$value => (object) []];
            } else {
                $compiledHighlights['fields'][] = ['*' => (object) []];
            }
        }

        $compiledHighlights['pre_tags'] = $highlight['preTag'];
        $compiledHighlights['post_tags'] = $highlight['postTag'];

        return $compiledHighlights;
    }

    /**
     * Build inner_hits options for nested queries.
     * When you need to know which nested doc matched.
     */
    public function _buildInnerHits($innerQuery)
    {
        $innerHits = [];
        $compiledOrders = [];

        if ($innerQuery->orders) {
            $compiledOrders = $this->compileOrders($innerQuery, $innerQuery->orders);
        }
        if ($innerQuery->sorts) {
            $compiledOrders = $this->compileSorts($innerQuery, $innerQuery->sorts, $compiledOrders);
        }
        if ($compiledOrders) {
            $innerHits['sort'] = $compiledOrders;
        }

        if ($innerQuery->offset) {
            $innerHits['from'] = $innerQuery->offset;
        }
        if ($size = $innerQuery->getSetLimit()) {
            $innerHits['size'] = $size;
        }

        return $innerHits;
    }
}
