<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Relations;

use Illuminate\Database\Eloquent\Relations\MorphToMany as EloquentMorphToMany;

class MorphToMany extends EloquentMorphToMany
{

  /** @inheritdoc */
  protected function shouldSelect(array $columns = ['*'])
  {
    return $columns;
  }

  public function qualifyPivotColumn($column)
  {
    if ($this->query->getQuery()->getGrammar()->isExpression($column)) {
      return $column;
    }

    // We treat Joins differently in elastic so we need to NOT have the parent table name to be able to search the pivot.
    return $column;
  }

  public function getResults()
  {
    return ! is_null($this->parent->{$this->parentKey})
      ? $this->get()
      : $this->related->newCollection();
  }

  /** @inheritdoc */
  public function attach($id, array $attributes = [], $touch = true)
  {
    // Before we run the attachment we need to delete

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
      $records = collect($records)->map(function($record) {
        $record['id'] = md5(json_encode($record));
        return $record;
      })->all();

      $this->newPivotStatement()->insert($records);
    }

    if ($touch) {
      $this->touchIfTouching();
    }
  }


  public function get($columns = ['*'])
  {
    // First we'll add the proper select columns onto the query so it is run with
    // the proper columns. Then, we will get the results and hydrate our pivot
    // models with the result of those columns as a separate model relation.
    $builder = $this->query->applyScopes();

    $columns = $builder->getQuery()->columns ? [] : $columns;

    // We modify our table here. Since elastic cant left or right join we create pull the relation from the pivot directly then hydrate.
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

}
