<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Relations\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use PDPhilip\Elasticsearch\Eloquent\Model as ElasticModel;

/**
 * Shared overrides for ES many-to-many relationships (BelongsToMany and MorphToMany).
 *
 * ES stores many-to-many relationships as embedded ID arrays on each document
 * rather than using a separate pivot table. This trait provides the common method
 * overrides that both relationship types need to work with this storage model.
 */
trait ManagesManyToMany
{
    /**
     * Whether the parent model is an Elasticsearch model.
     * Used to determine the correct storage strategy for relationship data.
     */
    protected function isElasticParent(): bool
    {
        return ElasticModel::isElasticsearchModel($this->parent);
    }

    // ------------------------------------------------------------------
    // Abstract hooks — implemented by each concrete class
    // ------------------------------------------------------------------

    /**
     * Apply the constraint query to load related models.
     */
    abstract protected function setWhere();

    /**
     * Get the currently synced IDs from the parent model.
     * Used by sync() to determine which IDs to attach/detach.
     */
    abstract protected function getCurrentSyncIds(): array;

    // ------------------------------------------------------------------
    // Laravel overrides — ES has no pivot table
    // ------------------------------------------------------------------

    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        return $query;
    }

    protected function hydratePivotRelation(array $models): void
    {
        // ES stores relationships as embedded arrays, not pivot tables.
    }

    protected function shouldSelect(array $columns = ['*'])
    {
        return $columns;
    }

    public function addConstraints()
    {
        if (static::$constraints) {
            $this->setWhere();
        }
    }

    public function updateExistingPivot($id, array $attributes, $touch = true)
    {
        // ES stores relationships as embedded arrays, not pivot tables.
        return $this;
    }

    public function newPivotQuery()
    {
        return $this->newRelatedQuery();
    }

    public function newRelatedQuery()
    {
        return $this->related->newQuery();
    }

    public function getQualifiedRelatedPivotKeyName()
    {
        return $this->relatedPivotKey;
    }

    protected function whereInMethod(Model $model, $key)
    {
        return 'whereIn';
    }

    // ------------------------------------------------------------------
    // Shared relationship operations
    // ------------------------------------------------------------------

    public function save(Model $model, array $pivotAttributes = [], $touch = true)
    {
        $model->save(['touch' => false]);
        $this->attach($model, $pivotAttributes, $touch);

        return $model;
    }

    public function create(array $attributes = [], array $joining = [], $touch = true)
    {
        $instance = $this->related->newInstance($attributes);
        $instance->save(['touch' => false]);
        $this->attach($instance, $joining, $touch);

        return $instance;
    }

    public function sync($ids, $detaching = true)
    {
        $changes = [
            'attached' => [],
            'detached' => [],
            'updated' => [],
        ];

        if ($ids instanceof Collection || $ids instanceof Model) {
            $ids = $this->parseIds($ids);
        }

        $records = $this->formatRecordsList($ids);
        $syncedIds = Arr::wrap($this->getCurrentSyncIds());

        $current = [];
        $detach = [];
        foreach ($syncedIds as $id) {
            $id = (string) $id;
            $current[] = $id;
            if (! isset($records[$id])) {
                $detach[] = $id;
            }
        }

        if ($detaching && $detach) {
            $this->detach($detach);
            $changes['detached'] = $detach;
        }

        $attachChanges = $this->attachNew($records, $current, false);

        $changes = [...$changes, ...$attachChanges];

        if (count($changes['attached']) || count($changes['updated'])) {
            $this->touchIfTouching();
        }

        return $changes;
    }

    // ------------------------------------------------------------------
    // Helpers for hybrid relationships (ES parent vs SQL parent)
    // ------------------------------------------------------------------

    /**
     * Add a related model ID to the parent's in-memory relation collection.
     * Used when the parent is a SQL model in a hybrid relationship.
     */
    protected function addIdToParentRelationData($id): void
    {
        $instance = new $this->related;
        $instance->forceFill([$this->relatedKey => $id]);
        $relationData = $this->parent->{$this->relationName}->push($instance)->unique($this->relatedKey);
        $this->parent->setRelation($this->relationName, $relationData);
    }

    /**
     * Remove IDs from the parent's in-memory relation collection.
     * Used when the parent is a SQL model in a hybrid relationship.
     */
    protected function removeIdsFromParentRelationData(array $ids): void
    {
        $value = $this->parent->{$this->relationName}
            ->filter(fn ($rel) => ! in_array($rel->{$this->relatedKey}, $ids));
        $this->parent->setRelation($this->relationName, $value);
    }
}
