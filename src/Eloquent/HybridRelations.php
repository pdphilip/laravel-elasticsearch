<?php
/**
 * @credit https://github.com/jenssegers/laravel-mongodb/
 */

namespace PDPhilip\Elasticsearch\Eloquent;

use Illuminate\Support\Str;
use PDPhilip\Elasticsearch\Relations\BelongsTo;
use PDPhilip\Elasticsearch\Relations\BelongsToMany;
use PDPhilip\Elasticsearch\Relations\HasMany;
use PDPhilip\Elasticsearch\Relations\HasOne;
use PDPhilip\Elasticsearch\Relations\MorphMany;
use PDPhilip\Elasticsearch\Relations\MorphTo;
use PDPhilip\Elasticsearch\Relations\MorphOne;
use PDPhilip\Elasticsearch\Eloquent\Model as ParentModel;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

trait HybridRelations
{
    /**
     * @inheritDoc
     */
    public function hasOne($related, $foreignKey = null, $localKey = null)
    {
        $foreignKey = $foreignKey ? : $this->getForeignKey();

        $instance = new $related;

        $localKey = $localKey ? : $this->getKeyName();

        return new HasOne($instance->newQuery(), $this, $foreignKey, $localKey);
    }

    /**
     * @inheritDoc
     */
    public function morphOne($related, $name, $type = null, $id = null, $localKey = null)
    {
        $instance = new $related;

        [$type, $id] = $this->getMorphs($name, $type, $id);

        $localKey = $localKey ? : $this->getKeyName();

        return new MorphOne($instance->newQuery(), $this, $type, $id, $localKey);

    }

    /**
     * @inheritDoc
     */
    public function hasMany($related, $foreignKey = null, $localKey = null)
    {
        $foreignKey = $foreignKey ? : $this->getForeignKey();

        $instance = new $related;

        $localKey = $localKey ? : $this->getKeyName();

        return new HasMany($instance->newQuery(), $this, $foreignKey, $localKey);
    }

    /**
     * @inheritDoc
     */
    public function morphMany($related, $name, $type = null, $id = null, $localKey = null)
    {

        $instance = new $related;

        [$type, $id] = $this->getMorphs($name, $type, $id);

        $table = $instance->getTable();

        $localKey = $localKey ? : $this->getKeyName();

        return new MorphMany($instance->newQuery(), $this, $type, $id, $localKey);
    }

    /**
     * @inheritDoc
     */
    public function belongsTo($related, $foreignKey = null, $otherKey = null, $relation = null)
    {

        if ($relation === null) {
            [$current, $caller] = debug_backtrace(false, 2);

            $relation = $caller['function'];
        }


        if ($foreignKey === null) {
            $foreignKey = Str::snake($relation).'_id';
        }

        $instance = new $related;

        $query = $instance->newQuery();

        $otherKey = $otherKey ? : $instance->getKeyName();

        return new BelongsTo($query, $this, $foreignKey, $otherKey, $relation);
    }

    /**
     * @inheritDoc
     */
    public function morphTo($name = null, $type = null, $id = null, $ownerKey = null)
    {
        if ($name === null) {
            [$current, $caller] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);

            $name = Str::snake($caller['function']);
        }

        [$type, $id] = $this->getMorphs($name, $type, $id);

        if (($class = $this->$type) === null) {
            return new MorphTo(
                $this->newQuery(), $this, $id, $ownerKey, $type, $name
            );
        }

        $class = $this->getActualClassNameForMorph($class);

        $instance = new $class;

        $ownerKey = $ownerKey ?? $instance->getKeyName();

        return new MorphTo(
            $instance->newQuery(), $this, $id, $ownerKey, $type, $name
        );
    }

    /**
     * @inheritDoc
     */
    public function belongsToMany($related, $collection = null, $foreignKey = null, $otherKey = null, $parentKey = null, $relatedKey = null, $relation = null)
    {

        if ($relation === null) {
            $relation = $this->guessBelongsToManyRelation();
        }


        if (!is_subclass_of($related, ParentModel::class)) {
            return parent::belongsToMany($related, $collection, $foreignKey, $otherKey, $parentKey, $relatedKey, $relation);
        }

        $foreignKey = $foreignKey ? : $this->getForeignKey().'s';
        $instance = new $related;
        $otherKey = $otherKey ? : $instance->getForeignKey().'s';

        if ($collection === null) {
            $collection = $instance->getTable();
        }

        $query = $instance->newQuery();

        return new BelongsToMany($query, $this, $collection, $foreignKey, $otherKey, $parentKey ? : $this->getKeyName(), $relatedKey ? : $instance->getKeyName(), $relation
        );
    }

    /**
     * @inheritDoc
     */

    protected function guessBelongsToManyRelation()
    {
        if (method_exists($this, 'getBelongsToManyCaller')) {
            return $this->getBelongsToManyCaller();
        }

        return parent::guessBelongsToManyRelation();
    }

    /**
     * @inheritdoc
     */
    public function newEloquentBuilder($query)
    {
        if (is_subclass_of($this, ParentModel::class)) {
            return new Builder($query);
        }

        return new EloquentBuilder($query);
    }
}
