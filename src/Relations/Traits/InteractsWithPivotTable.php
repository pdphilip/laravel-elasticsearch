<?php

  declare(strict_types=1);

  namespace PDPhilip\Elasticsearch\Relations\Traits;

  use BackedEnum;

  trait InteractsWithPivotTable
  {

    /** {@inheritdoc} */
    protected function formatRecordsList(array $records)
    {
      return collect($records)->mapWithKeys(function ($attributes, $id) {
        if (! is_array($attributes)) {
          [$id, $attributes] = [$attributes, []];
        }

        if ($id instanceof BackedEnum) {
          $id = $id->value;
        }

        // We have to convert all Key Ids to string values to keep it consistent in Elastic.
        return [(string) $id => $attributes];
      })->all();
    }

    /** {@inheritdoc} */
    protected function parseIds($value)
    {
      // We have to convert all Key Ids to string values to keep it consistent in Elastic.
      return collect(parent::parseIds($value))->mapWithKeys(function ($value, $key) {
        return [(string) $key => $value];
      })->all();
    }

  }
