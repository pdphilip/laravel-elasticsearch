<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Relations\Traits;

use BackedEnum;

trait InteractsWithPivotTable
{
    /**
     * Coerce all record keys to strings for ES consistency.
     *
     * {@inheritdoc}
     */
    protected function formatRecordsList(array $records)
    {
        $formatted = [];

        foreach ($records as $id => $attributes) {
            if (! is_array($attributes)) {
                $id = $attributes;
                $attributes = [];
            }

            if ($id instanceof BackedEnum) {
                $id = $id->value;
            }

            $formatted[(string) $id] = $attributes;
        }

        return $formatted;
    }

    /**
     * Coerce all parsed IDs to strings for ES consistency.
     *
     * {@inheritdoc}
     */
    protected function parseIds($value)
    {
        $parsed = [];

        foreach (parent::parseIds($value) as $key => $val) {
            $parsed[(string) $key] = $val;
        }

        return $parsed;
    }
}
