<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Eloquent;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Str;
use PDPhilip\Elasticsearch\Eloquent\Model as ParentModel;
use PDPhilip\Elasticsearch\Relations\BelongsTo;
use PDPhilip\Elasticsearch\Relations\BelongsToMany;
use PDPhilip\Elasticsearch\Relations\HasMany;
use PDPhilip\Elasticsearch\Relations\HasOne;
use PDPhilip\Elasticsearch\Relations\MorphMany;
use PDPhilip\Elasticsearch\Relations\MorphOne;
use PDPhilip\Elasticsearch\Relations\MorphTo;
use PDPhilip\Elasticsearch\Relations\MorphToMany;

trait HybridRelations
{
    /**
     * {@inheritDoc}
     */
    public function hasOne($related, $foreignKey = null, $localKey = null): HasOne
    {

        // Check if it is a relation with an original model.
        if (! Model::isElasticsearchModel($related)) {
          return parent::hasOne($related, $foreignKey, $localKey);
        }

        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $instance = new $related;

        $localKey = $localKey ?: $this->getKeyName();

        return new HasOne($instance->newQuery(), $this, $foreignKey, $localKey);
    }

    /**
     * {@inheritDoc}
     */
    public function morphOne($related, $name, $type = null, $id = null, $localKey = null): MorphOne
    {
        // Check if it is a relation with an original model.
        if (! Model::isElasticsearchModel($related)) {
          return parent::morphOne($related, $name, $type, $id, $localKey);
        }

        $instance = new $related;

        [$type, $id] = $this->getMorphs($name, $type, $id);

        $localKey = $localKey ?: $this->getKeyName();

        return new MorphOne($instance->newQuery(), $this, $type, $id, $localKey);
    }

    /**
     * {@inheritDoc}
     */
    public function hasMany($related, $foreignKey = null, $localKey = null): HasMany
    {
        // Check if it is a relation with an original model.
        if (! Model::isElasticsearchModel($related)) {
          return parent::hasMany($related, $foreignKey, $localKey);
        }

        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $instance = new $related;

        $localKey = $localKey ?: $this->getKeyName();

        return new HasMany($instance->newQuery(), $this, $foreignKey, $localKey);
    }

    /**
     * {@inheritDoc}
     */
    public function morphMany($related, $name, $type = null, $id = null, $localKey = null): MorphMany
    {

      // Check if it is a relation with an original model.
      if (! Model::isElasticsearchModel($related)) {
        return parent::morphMany($related, $name, $type, $id, $localKey);
      }

        $instance = new $related;

        [$type, $id] = $this->getMorphs($name, $type, $id);

        $table = $instance->getTable();

        $localKey = $localKey ?: $this->getKeyName();

        return new MorphMany($instance->newQuery(), $this, $type, $id, $localKey);
    }

    /**
     * {@inheritDoc}
     */
    public function belongsTo($related, $foreignKey = null, $otherKey = null, $relation = null): BelongsTo
    {

        if ($relation === null) {
            [$current, $caller] = debug_backtrace(0, 2);

            $relation = $caller['function'];
        }

      // Check if it is a relation with an original model.
      if (! Model::isElasticsearchModel($related)) {
        return parent::belongsTo($related, $foreignKey, $otherKey, $relation);
      }

        if ($foreignKey === null) {
            $foreignKey = Str::snake($relation).'_id';
        }

        $instance = new $related;

        $query = $instance->newQuery();

        $otherKey = $otherKey ?: $instance->getKeyName();

        return new BelongsTo($query, $this, $foreignKey, $otherKey, $relation);
    }

    /**
     * {@inheritDoc}
     */
    public function morphTo($name = null, $type = null, $id = null, $ownerKey = null): MorphTo
    {
        if ($name === null) {
            [$current, $caller] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);

            $name = Str::snake($caller['function']);
        }

        [$type, $id] = $this->getMorphs($name, $type, $id);

        if (($class = $this->$type) === null) {
            //@phpstan-ignore-next-line
            return new MorphTo($this->newQuery(), $this, $id, $ownerKey, $type, $name);
        }

        $class = $this->getActualClassNameForMorph($class);

        $instance = new $class;

        $ownerKey = $ownerKey ?? $instance->getKeyName();

      // Check if it is a relation with an original model.
      if (! Model::isElasticsearchModel($instance)) {
        return parent::morphTo($name, $type, $id, $ownerKey);
      }

        return new MorphTo($instance->newQuery(), $this, $id, $ownerKey, $type, $name);
    }


  /**
   * Define a many-to-many relationship.
   *
   * @see HasRelationships::belongsToMany()
   *
   * @param class-string $related
   * @param string|null  $collection
   * @param string|null  $foreignPivotKey
   * @param string|null  $relatedPivotKey
   * @param string|null  $parentKey
   * @param string|null  $relatedKey
   * @param string|null  $relation
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
   */
  public function belongsToMany(
    $related,
    $collection = null,
    $foreignPivotKey = null,
    $relatedPivotKey = null,
    $parentKey = null,
    $relatedKey = null,
    $relation = null,
  ) {
    // If no relationship name was passed, we will pull backtraces to get the
    // name of the calling function. We will use that function name as the
    // title of this relation since that is a great convention to apply.
    if ($relation === null) {
      $relation = $this->guessBelongsToManyRelation();
    }

    // Check if it is a relation with an original model.
    if (! Model::isElasticsearchModel($related)) {
      return parent::belongsToMany(
        $related,
        $collection,
        $foreignPivotKey,
        $relatedPivotKey,
        $parentKey,
        $relatedKey,
        $relation,
      );
    }

    // First, we'll need to determine the foreign key and "other key" for the
    // relationship. Once we have determined the keys we'll make the query
    // instances as well as the relationship instances we need for this.
    $foreignPivotKey = $foreignPivotKey ?: $this->getForeignKey() . 's';

    $instance = new $related();

    $relatedPivotKey = $relatedPivotKey ?: $instance->getForeignKey() . 's';

    // If no table name was provided, we can guess it by concatenating the two
    // models using underscores in alphabetical order. The two model names
    // are transformed to snake case from their default CamelCase also.
    if ($collection === null) {
      $collection = $instance->getTable();
    }

    // Now we're ready to create a new query builder for the related model and
    // the relationship instances for the relation. The relations will set
    // appropriate query constraint and entirely manages the hydrations.
    $query = $instance->newQuery();

    return new BelongsToMany(
      $query,
      $this,
      $collection,
      $foreignPivotKey,
      $relatedPivotKey,
      $parentKey ?: $this->getKeyName(),
      $relatedKey ?: $instance->getKeyName(),
      $relation,
    );
  }

    /**
     * {@inheritDoc}
     */
  public function morphToMany(
    $related,
    $name,
    $table = null,
    $foreignPivotKey = null,
    $relatedPivotKey = null,
    $parentKey = null,
    $relatedKey = null,
    $relation = null,
    $inverse = false
  ): MorphToMany
    {
      if ($relation === null) {
        $relation = $relation ?: $this->guessBelongsToManyRelation();
      }

      // Check if it is a relation with an original model.
      if (! Model::isElasticsearchModel($related)) {
        return parent::morphToMany(
          $related,
          $name,
          $table,
          $foreignPivotKey,
          $relatedPivotKey,
          $parentKey,
          $relatedKey,
          $relation,
          $inverse,
        );
      }

      $instance = $this->newRelatedInstance($related);

      $foreignPivotKey = $foreignPivotKey ?: $name.'_id';
      $relatedPivotKey = $relatedPivotKey ?: $instance->getForeignKey();

      if (! $table) {
        $words = preg_split('/(_)/u', $name, -1, PREG_SPLIT_DELIM_CAPTURE);
        $lastWord = array_pop($words);
        $table = implode('', $words).Str::plural($lastWord);
      }

        return new MorphToMany(
          $instance->newQuery(), $this, $name, $table,
          $foreignPivotKey, $relatedPivotKey, $parentKey ?: $this->getKeyName(),
          $relatedKey ?: $instance->getKeyName(), $relation, $inverse
        );
    }

  /**
   * Define a polymorphic, inverse many-to-many relationship.
   *
   * @param  string $related
   * @param  string $name
   * @param  null   $table
   * @param  null   $foreignPivotKey
   * @param  null   $relatedPivotKey
   * @param  null   $parentKey
   * @param  null   $relatedKey
   *
   * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
   */
  public function morphedByMany(
    $related,
    $name,
    $table = null,
    $foreignPivotKey = null,
    $relatedPivotKey = null,
    $parentKey = null,
    $relatedKey = null,
    $relation = null,
  ) {
    // If the related model is an instance of eloquent model class, leave pivot keys
    // as default. It's necessary for supporting hybrid relationship
    if (Model::isElasticsearchModel($related)) {
      // For the inverse of the polymorphic many-to-many relations, we will change
      // the way we determine the foreign and other keys, as it is the opposite
      // of the morph-to-many method since we're figuring out these inverses.
      $foreignPivotKey = $foreignPivotKey ?: Str::plural($this->getForeignKey());

      $relatedPivotKey = $relatedPivotKey ?: $name . '_id';
    }

    return $this->morphToMany(
      $related,
      $name,
      $table,
      $foreignPivotKey,
      $relatedPivotKey,
      $parentKey,
      $relatedKey,
      $relatedKey,
      true,
    );
  }

    /**
     * {@inheritdoc}
     */
    public function newEloquentBuilder($query): EloquentBuilder|Builder
    {
        //@phpstan-ignore-next-line
        if (is_subclass_of($this, ParentModel::class)) {
            return new Builder($query);
        }

        return new EloquentBuilder($query);
    }

    /**
     * {@inheritDoc}
     */
    protected function guessBelongsToManyRelation(): string
    {
        if (method_exists($this, 'getBelongsToManyCaller')) {
            return $this->getBelongsToManyCaller();
        }

        return parent::guessBelongsToManyRelation();
    }
}
