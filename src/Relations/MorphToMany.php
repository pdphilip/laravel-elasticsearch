<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Relations;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany as EloquentMorphToMany;
use Illuminate\Support\Arr;
use PDPhilip\Elasticsearch\Relations\Traits\InteractsWithPivotTable;
use PDPhilip\Elasticsearch\Relations\Traits\ManagesManyToMany;

class MorphToMany extends EloquentMorphToMany
{
    use InteractsWithPivotTable;
    use ManagesManyToMany;
    use ManagesRefresh;

    // ------------------------------------------------------------------
    // Constraint & sync hooks
    // ------------------------------------------------------------------

    public function addEagerConstraints(array $models)
    {
        if ($this->getInverse()) {
            $ids = $this->getKeys($models, $this->table);
            $ids = $this->extractIds($ids[0] ?? []);
            $this->query->whereIn($this->relatedKey, $ids);
        } else {
            parent::addEagerConstraints($models);
            $this->query->where($this->qualifyPivotColumn($this->morphType), $this->morphClass);
        }
    }

    protected function setWhere()
    {
        if ($this->getInverse()) {
            if ($this->isElasticParent()) {
                $ids = $this->extractIds((array) $this->parent->{$this->table});
                $this->query->whereIn($this->relatedKey, $ids);
            } else {
                $this->query->whereIn($this->foreignPivotKey, (array) $this->parent->{$this->parentKey});
            }
        } else {
            if ($this->isElasticParent()) {
                $this->query->whereIn($this->relatedKey, (array) $this->parent->{$this->relatedPivotKey});
            } else {
                $this->query->whereIn($this->getQualifiedForeignPivotKeyName(), (array) $this->parent->{$this->parentKey});
            }
        }

        return $this;
    }

    protected function getCurrentSyncIds(): array
    {
        if ($this->getInverse()) {
            $current = $this->isElasticParent()
                ? ($this->parent->{$this->table} ?: [])
                : ($this->parent->{$this->relationName} ?: []);

            if ($current instanceof Collection) {
                return collect($this->parseIds($current))->flatten()->toArray();
            }

            return $this->extractIds($current);
        }

        $current = $this->isElasticParent()
            ? ($this->parent->{$this->relatedPivotKey} ?: [])
            : ($this->parent->{$this->relationName} ?: []);

        if ($current instanceof Collection) {
            return $this->parseIds($current);
        }

        return $current;
    }

    // ------------------------------------------------------------------
    // Attach / Detach
    //
    // Each operation has two sides:
    //   1. Update the parent  — push/pull IDs or morph entries
    //   2. Update the related — push/pull parent reference
    //
    // The inverse flag flips which side stores flat IDs vs morph entries:
    //   morphToMany:   related stores morph entries, parent stores flat IDs
    //   morphedByMany: parent stores morph entries, related stores flat IDs
    // ------------------------------------------------------------------

    public function attach($id, array $attributes = [], $touch = true)
    {
        if ($id instanceof Model) {
            $model = $id;
            $id = $this->parseId($model);

            $this->attachIdsToParent([(string) $id]);
            $this->attachParentToRelatedModel($model);
        } else {
            if ($id instanceof Collection) {
                $id = $this->parseIds($id);
            }

            $id = array_map(fn ($item) => (string) $item, Arr::wrap($id));

            $query = $this->newRelatedQuery();
            $query->whereIn($this->relatedKey, $id);

            $this->attachIdsToParent($id);
            $this->attachParentToRelatedQuery($query);
        }

        if ($touch) {
            $this->touchIfTouching();
        }
    }

    public function detach($ids = [], $touch = true)
    {
        if ($ids instanceof Model) {
            $ids = $this->parseIds($ids);
        }

        $ids = (array) $ids;

        $this->detachIdsFromParent($ids);

        $query = $this->newRelatedQuery();
        if (count($ids) > 0) {
            $query->whereIn($this->relatedKey, $ids);
        }

        $this->detachParentFromRelated($query);

        if ($touch) {
            $this->touchIfTouching();
        }

        return count($ids);
    }

    // ------------------------------------------------------------------
    // Attach helpers — encapsulate inverse × ES/SQL branching
    // ------------------------------------------------------------------

    /**
     * Push related IDs onto the parent model.
     *
     * Inverse:     parent stores morph entries in $this->table
     * Non-inverse: parent stores flat IDs in $this->relatedPivotKey
     */
    private function attachIdsToParent(array $ids): void
    {
        if ($this->getInverse()) {
            $morphClass = $this->related instanceof Model ? $this->related->getMorphClass() : null;

            if ($this->isElasticParent()) {
                foreach ($ids as $id) {
                    $this->parent->push($this->table, [
                        $this->buildMorphEntry($this->relatedPivotKey, $id, $morphClass),
                    ], true);
                }
            } else {
                foreach ($ids as $id) {
                    $this->addIdToParentRelationData($id);
                }
            }
        } else {
            if ($this->isElasticParent()) {
                $this->parent->push($this->relatedPivotKey, $ids, true);
            } else {
                foreach ($ids as $id) {
                    $this->addIdToParentRelationData($id);
                }
            }
        }
    }

    /**
     * Push parent reference onto a single related model instance.
     *
     * Inverse:     related stores flat parent IDs in foreignPivotKey
     * Non-inverse: related stores morph entries in $this->table
     */
    private function attachParentToRelatedModel(Model $model): void
    {
        if ($this->getInverse()) {
            $model->push($this->foreignPivotKey, (array) $this->parent->{$this->parentKey}, true);
        } else {
            $morphClass = $this->parent instanceof Model ? $this->parent->getMorphClass() : null;
            $model->push($this->table, [
                $this->buildMorphEntry($this->foreignPivotKey, $this->parent->{$this->parentKey}, $morphClass),
            ], true);
        }
    }

    /**
     * Push parent reference onto related models matched by query.
     *
     * Same logic as attachParentToRelatedModel but operates via bulk query.
     */
    private function attachParentToRelatedQuery($query): void
    {
        if ($this->getInverse()) {
            $query->push($this->foreignPivotKey, $this->parent->{$this->parentKey});
        } else {
            $morphClass = $this->parent instanceof Model ? $this->parent->getMorphClass() : null;
            $query->push($this->table, [
                $this->buildMorphEntry($this->foreignPivotKey, $this->parent->{$this->parentKey}, $morphClass),
            ], true);
        }
    }

    // ------------------------------------------------------------------
    // Detach helpers
    // ------------------------------------------------------------------

    /**
     * Remove related IDs from the parent model.
     *
     * Inverse:     removes morph entries from $this->table
     * Non-inverse: removes flat IDs from $this->relatedPivotKey
     */
    private function detachIdsFromParent(array $ids): void
    {
        if ($this->getInverse()) {
            $data = array_map(fn ($item) => $this->buildMorphEntry(
                $this->relatedPivotKey, $item, $this->related->getMorphClass()
            ), $ids);

            if ($this->isElasticParent()) {
                $this->parent->pull($this->table, $data);
            } else {
                $this->removeIdsFromParentRelationData($this->extractIds($data));
            }
        } else {
            if ($this->isElasticParent()) {
                $this->parent->pull($this->relatedPivotKey, $ids);
            } else {
                $this->removeIdsFromParentRelationData($ids);
            }
        }
    }

    /**
     * Remove parent reference from the related models.
     *
     * Inverse:     removes flat parent ID from foreignPivotKey
     * Non-inverse: removes morph entry from $this->table
     */
    private function detachParentFromRelated($query): void
    {
        if ($this->getInverse()) {
            $query->pull($this->foreignPivotKey, $this->parent->{$this->parentKey});
        } else {
            $query->pull($this->table, [
                $this->buildMorphEntry(
                    $this->foreignPivotKey, $this->parent->{$this->parentKey}, $this->parent->getMorphClass()
                ),
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Morph entry builder
    // ------------------------------------------------------------------

    /**
     * Build a morph entry: [idKey => idValue, morphType => morphClass].
     *
     * These entries are stored on documents to track polymorphic relationships,
     * combining the foreign ID with the model type for disambiguation.
     */
    private function buildMorphEntry(string $idKey, $idValue, ?string $morphClass): array
    {
        return [
            $idKey => $idValue,
            $this->morphType => $morphClass,
        ];
    }

    // ------------------------------------------------------------------
    // Dictionary & helpers
    // ------------------------------------------------------------------

    protected function buildDictionary(Collection $results)
    {
        $foreign = $this->foreignPivotKey;
        $dictionary = [];

        foreach ($results as $result) {
            if ($this->getInverse()) {
                foreach ($result->$foreign as $item) {
                    $dictionary[$item][] = $result;
                }
            } else {
                $items = $this->extractIds($result->{$this->table} ?? [], $foreign);
                foreach ($items as $item) {
                    $dictionary[$item][] = $result;
                }
            }
        }

        return $dictionary;
    }

    public function extractIds(array $data, ?string $relatedPivotKey = null)
    {
        $relatedPivotKey = $relatedPivotKey ?: $this->relatedPivotKey;

        return array_reduce($data, function ($carry, $item) use ($relatedPivotKey) {
            if (is_array($item) && array_key_exists($relatedPivotKey, $item)) {
                $carry[] = $item[$relatedPivotKey];
            }

            return $carry;
        }, []);
    }
}
