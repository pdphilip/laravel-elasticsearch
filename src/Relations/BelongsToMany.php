<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Relations;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany as EloquentBelongsToMany;
use PDPhilip\Elasticsearch\Relations\Traits\InteractsWithPivotTable;
use PDPhilip\Elasticsearch\Relations\Traits\ManagesManyToMany;

class BelongsToMany extends EloquentBelongsToMany
{
    use InteractsWithPivotTable;
    use ManagesManyToMany;
    use ManagesRefresh;

    public function getHasCompareKey()
    {
        return $this->getForeignKey();
    }

    protected function getSelectColumns(array $columns = ['*'])
    {
        return $columns;
    }

    // ------------------------------------------------------------------
    // Constraint & sync hooks
    // ------------------------------------------------------------------

    protected function setWhere()
    {
        $foreign = $this->getForeignKey();
        $this->query->whereMatch($foreign, $this->parent->{$this->parentKey});

        return $this;
    }

    protected function getCurrentSyncIds(): array
    {
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
    // ------------------------------------------------------------------

    public function attach($id, array $attributes = [], $touch = true)
    {
        if ($id instanceof Model) {
            $model = $id;
            $id = $this->parseId($model);
            $model->push($this->foreignPivotKey, $this->parent->{$this->parentKey}, true);
        } else {
            if ($id instanceof Collection) {
                $id = $this->parseIds($id);
            }

            $query = $this->newRelatedQuery();
            $query->whereIn($this->relatedKey, (array) $id);
            $query->push($this->foreignPivotKey, $this->parent->{$this->parentKey}, true);
        }

        $this->isElasticParent()
            ? $this->parent->push($this->relatedPivotKey, (array) $id, true)
            : $this->addIdToParentRelationData($id);

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

        $this->isElasticParent()
            ? $this->parent->pull($this->relatedPivotKey, $ids)
            : $this->removeIdsFromParentRelationData($ids);

        $query = $this->newRelatedQuery();
        if (count($ids) > 0) {
            $query->whereIn($this->relatedKey, $ids);
        }

        $query->pull($this->foreignPivotKey, $this->parent->{$this->parentKey});

        if ($touch) {
            $this->touchIfTouching();
        }

        return count($ids);
    }

    // ------------------------------------------------------------------
    // Dictionary & key helpers
    // ------------------------------------------------------------------

    protected function buildDictionary(Collection $results)
    {
        $foreign = $this->foreignPivotKey;
        $dictionary = [];

        foreach ($results as $result) {
            foreach ($result->$foreign as $item) {
                $dictionary[$item][] = $result;
            }
        }

        return $dictionary;
    }

    public function getForeignKey()
    {
        return $this->foreignPivotKey;
    }

    public function getQualifiedForeignPivotKeyName()
    {
        return $this->foreignPivotKey;
    }
}
