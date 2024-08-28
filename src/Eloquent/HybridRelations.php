<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Eloquent;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Str;
use PDPhilip\Elasticsearch\Eloquent\Model as ParentModel;
use PDPhilip\Elasticsearch\Relations\BelongsTo;
use PDPhilip\Elasticsearch\Relations\HasMany;
use PDPhilip\Elasticsearch\Relations\HasOne;
use PDPhilip\Elasticsearch\Relations\MorphMany;
use PDPhilip\Elasticsearch\Relations\MorphOne;
use PDPhilip\Elasticsearch\Relations\MorphTo;

trait HybridRelations
{
    /**
     * {@inheritDoc}
     *
     * @phpstan-ignore-next-line
     */
    public function hasOne($related, $foreignKey = null, $localKey = null): HasOne
    {
        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $instance = new $related;

        $localKey = $localKey ?: $this->getKeyName();

        return new HasOne($instance->newQuery(), $this, $foreignKey, $localKey);
    }

    /**
     * {@inheritDoc}
     *
     *  @phpstan-ignore-next-line
     */
    public function morphOne($related, $name, $type = null, $id = null, $localKey = null): MorphOne
    {
        $instance = new $related;

        [$type, $id] = $this->getMorphs($name, $type, $id);

        $localKey = $localKey ?: $this->getKeyName();

        return new MorphOne($instance->newQuery(), $this, $type, $id, $localKey);

    }

    /**
     * {@inheritDoc}
     *
     *  @phpstan-ignore-next-line
     */
    public function hasMany($related, $foreignKey = null, $localKey = null): HasMany
    {
        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $instance = new $related;

        $localKey = $localKey ?: $this->getKeyName();

        return new HasMany($instance->newQuery(), $this, $foreignKey, $localKey);
    }

    /**
     * {@inheritDoc}
     *
     *  @phpstan-ignore-next-line
     */
    public function morphMany($related, $name, $type = null, $id = null, $localKey = null): MorphMany
    {

        $instance = new $related;

        [$type, $id] = $this->getMorphs($name, $type, $id);

        $table = $instance->getTable();

        $localKey = $localKey ?: $this->getKeyName();

        return new MorphMany($instance->newQuery(), $this, $type, $id, $localKey);
    }

    /**
     * {@inheritDoc}
     *
     *  @phpstan-ignore-next-line
     */
    public function belongsTo($related, $foreignKey = null, $otherKey = null, $relation = null): BelongsTo
    {

        if ($relation === null) {
            [$current, $caller] = debug_backtrace(0, 2);

            $relation = $caller['function'];
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
     *
     *  @phpstan-ignore-next-line
     */
    public function morphTo($name = null, $type = null, $id = null, $ownerKey = null): MorphTo
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
     * {@inheritdoc}
     *
     *  @phpstan-ignore-next-line
     */
    public function newEloquentBuilder($query): EloquentBuilder|Builder
    {
        if (is_subclass_of($this, ParentModel::class)) {
            return new Builder($query);
        }

        return new EloquentBuilder($query);
    }

    /**
     * {@inheritDoc}
     *
     *  @phpstan-ignore-next-line
     */
    protected function guessBelongsToManyRelation(): string
    {
        if (method_exists($this, 'getBelongsToManyCaller')) {
            return $this->getBelongsToManyCaller();
        }

        return parent::guessBelongsToManyRelation();
    }
}
