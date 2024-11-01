<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Relations;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany as EloquentBelongsToMany;
use Illuminate\Support\Arr;
use PDPhilip\Elasticsearch\Exceptions\RuntimeException;

class BelongsToMany extends EloquentBelongsToMany
{

  public function qualifyPivotColumn($column)
  {
    if ($this->query->getQuery()->getGrammar()->isExpression($column)) {
      return $column;
    }

    // We treat Joins differently in elastic so we need to NOT have the parent table name to be able to search the pivot.
    return $column;
  }


  /** @inheritdoc */
  protected function shouldSelect(array $columns = ['*'])
  {
    return $columns;
  }

  public function get($columns = ['*'])
  {
    // First we'll add the proper select columns onto the query so it is run with
    // the proper columns. Then, we will get the results and hydrate our pivot
    // models with the result of those columns as a separate model relation.
    $builder = $this->query->applyScopes();

    $columns = $builder->getQuery()->columns ? [] : $columns;

    $models = $builder->from($this->table)->addSelect(
      $this->shouldSelect($columns)
    )->getModels();

    # Since we receive an elastic collection back we transform it here.
    $models = $models['results'];

    $this->hydratePivotRelation($models);

    // If we actually found models we will also eager load any relationships that
    // have been specified as needing to be eager loaded. This will solve the
    // n + 1 query problem for the developer and also increase performance.
    if (count($models) > 0) {
      $models = $builder->eagerLoadRelations($models);
    }

    return $this->query->applyAfterQueryCallbacks(
      $this->related->newCollection($models)
    );
  }

  public function attach($id, array $attributes = [], $touch = true)
  {
    if ($this->using) {
      $this->attachUsingCustomClass($id, $attributes);
    } else {
      // Here we will insert the attachment records into the pivot table. Once we have
      // inserted the records, we will touch the relationships if necessary and the
      // function will return. We can parse the IDs before inserting the records.

      $records = $this->formatAttachRecords(
        $this->parseIds($id), $attributes
      );

      // here we can be sneaky since our key can be a string we create a MD5 Hash sum to be the ID of the record this as a result keeps things unique.
      // This is only possible on ID type relationships since that type of data never changes.
      // TODO: Map Id's and convert them all to strings.
      $records = collect($records)->map(function($record) {
        $record = array_map('strval', $record);
        $record['id'] = md5(json_encode($record));
        return $record;
      })->all();

      $this->newPivotStatement()->insert($records);
    }

    if ($touch) {
      $this->touchIfTouching();
    }
  }

  /**
   * Get the name of the "where in" method for eager loading.
   *
   * @param string $key
   *
   * @return string
   */
  protected function whereInMethod(Model $model, $key)
  {
    return 'whereIn';
  }

}
