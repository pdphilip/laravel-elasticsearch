<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Query\Concerns;

/**
 * Geo queries - bounding boxes, distance filters, geo sorting.
 * For when location matters.
 */
trait BuildsGeoQueries
{
    /**
     * Filter docs within a bounding box.
     * https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-geo-bounding-box-query.html
     */
    public function whereGeoBox($field, array $topLeft, array $bottomRight, $validationMethod = null, $boolean = 'and', bool $not = false): self
    {
        $column = $field;
        $bounds = [
            'top_left' => $topLeft,
            'bottom_right' => $bottomRight,
        ];
        $options = [];
        if ($validationMethod) {
            $options['validation_method'] = $validationMethod;
        }
        $type = 'GeoBox';
        $this->wheres[] = [
            'column' => $column,
            'bounds' => $bounds,
            'type' => $type,
            'boolean' => $boolean,
            'not' => $not,
            'options' => $options,
        ];

        return $this;
    }

    public function orWhereGeoBox($field, array $topLeft, array $bottomRight, $validationMethod = null): self
    {
        return $this->whereGeoBox($field, $topLeft, $bottomRight, $validationMethod, 'or');
    }

    public function whereNotGeoBox($field, array $topLeft, array $bottomRight, $validationMethod = null): self
    {
        return $this->whereGeoBox($field, $topLeft, $bottomRight, $validationMethod, 'and', true);
    }

    public function orWhereNotGeoBox($field, array $topLeft, array $bottomRight, $validationMethod = null): self
    {
        return $this->whereGeoBox($field, $topLeft, $bottomRight, $validationMethod, 'or', true);
    }

    /**
     * Filter docs within a distance from a point.
     * https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-geo-distance-query.html
     */
    public function whereGeoDistance($field, string $distance, array $location, $distanceType = null, $validationMethod = null, $boolean = 'and', bool $not = false): self
    {
        $column = $field;
        $type = 'GeoDistance';
        $options = [];
        if ($distanceType) {
            $options['distance_type'] = $distanceType;
        }
        if ($validationMethod) {
            $options['validation_method'] = $validationMethod;
        }

        $this->wheres[] = compact('column', 'location', 'distance', 'type', 'boolean', 'not', 'options');

        return $this;
    }

    public function orWhereGeoDistance($field, string $distance, array $location, $distanceType = null, $validationMethod = null): self
    {
        return $this->whereGeoDistance($field, $distance, $location, $distanceType, $validationMethod, 'or', false);
    }

    public function whereNotGeoDistance($field, string $distance, array $location, $distanceType = null, $validationMethod = null): self
    {
        return $this->whereGeoDistance($field, $distance, $location, $distanceType, $validationMethod, 'and', true);
    }

    public function orWhereNotGeoDistance($field, string $distance, array $location, $distanceType = null, $validationMethod = null): self
    {
        return $this->whereGeoDistance($field, $distance, $location, $distanceType, $validationMethod, 'or', true);
    }

    // ----------------------------------------------------------------------
    // Geo Ordering
    // ----------------------------------------------------------------------

    /**
     * Order by distance from a point.
     */
    public function orderByGeo(string $column, array $coordinates, $direction = 1, array $options = []): self
    {
        if (is_array($direction)) {
            $options = $direction;
            $direction = 1;
        }
        $options = [
            ...$options,
            'type' => 'geoDistance',
            'coordinates' => $coordinates,
        ];

        return $this->orderBy($column, $direction, $options);
    }

    public function orderByGeoDesc(string $column, array $coordinates, array $options = []): self
    {
        $options = [
            ...$options,
            'type' => 'geoDistance',
            'coordinates' => $coordinates,
        ];

        return $this->orderBy($column, -1, $options);
    }

    // ----------------------------------------------------------------------
    // Deprecated - V4 Backwards Compatibility
    // ----------------------------------------------------------------------

    /**
     * @deprecated v5.0.0
     * @see whereGeoDistance()
     */
    public function filterGeoPoint($field, $distance, $geoPoint)
    {
        return $this->whereGeoDistance($field, $distance, $geoPoint);
    }

    /**
     * @deprecated v5.0.0
     * @see whereGeoBox()
     */
    public function filterGeoBox($field, $topLeft, $bottomRight)
    {
        return $this->whereGeoBox($field, $topLeft, $bottomRight);
    }
}
