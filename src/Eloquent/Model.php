<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Eloquent;

use Illuminate\Database\Eloquent\Model as BaseModel;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PDPhilip\Elasticsearch\Connection;
use PDPhilip\Elasticsearch\Eloquent\Docs\ModelDocs;
use PDPhilip\Elasticsearch\Query\Builder as QueryBuilder;
use RuntimeException;

/**
 * @property object $searchHighlights
 * @property array $searchHighlightsAsArray
 * @property object $withHighlights
 */
abstract class Model extends BaseModel
{
    use HybridRelations, ModelDocs;

    const MAX_SIZE = 1000;

    /**
     * The table associated with the model.
     *
     * @var string|null
     *
     * @phpstan-ignore-next-line
     */
    protected $index;

    protected ?string $recordIndex;

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    protected ?Relation $parentRelation;

    protected array $_meta = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setIndex();
        $this->setRecordIndex();
        $this->forcePrimaryKey();
    }

    public function forcePrimaryKey(): void
    {
        $this->primaryKey = '_id';
    }

    public function getRecordIndex(): ?string
    {
        return $this->recordIndex;
    }

    public function setRecordIndex($recordIndex = null)
    {
        if ($recordIndex) {
            return $this->recordIndex = $recordIndex;
        }

        return $this->recordIndex = $this->index;
    }

    /**
     * {@inheritdoc}
     */
    public function setTable($index)
    {
        $this->index = $index;
        unset($this->table);

        return $this;
    }

    public function getIdAttribute($value = null)
    {
        // If no value for id, then set ES's _id
        if (! $value && array_key_exists('_id', $this->attributes)) {
            $value = $this->attributes['_id'];
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getQualifiedKeyName(): string
    {
        return $this->getKeyName();
    }

    public function getMeta(): object
    {
        return (object) $this->_meta;
    }

    public function setMeta($meta): static
    {
        $this->_meta = $meta;

        return $this;
    }

    public function getSearchHighlightsAttribute(): ?object
    {
        if (! empty($this->_meta['highlights'])) {
            $data = [];
            $this->_mergeFlatKeysIntoNestedArray($data, $this->_meta['highlights']);

            return (object) $data;
        }

        return null;
    }

    protected function _mergeFlatKeysIntoNestedArray(&$data, $attrs): void
    {
        foreach ($attrs as $key => $value) {
            if ($value) {
                $value = implode('......', $value);
                $parts = explode('.', $key);
                $current = &$data;

                foreach ($parts as $partIndex => $part) {
                    if ($partIndex === count($parts) - 1) {
                        $current[$part] = $value;
                    } else {
                        if (! isset($current[$part]) || ! is_array($current[$part])) {
                            $current[$part] = [];
                        }
                        $current = &$current[$part];
                    }
                }
            }

        }
    }

    public function getSearchHighlightsAsArrayAttribute(): array
    {
        if (! empty($this->_meta['highlights'])) {
            return $this->_meta['highlights'];
        }

        return [];
    }

    public function getWithHighlightsAttribute(): object
    {
        $data = $this->attributes;
        $mutators = array_values(array_diff($this->getMutatedAttributes(), ['id', 'search_highlights', 'search_highlights_as_array', 'with_highlights']));
        if ($mutators) {
            foreach ($mutators as $mutator) {
                $data[$mutator] = $this->{$mutator};
            }
        }
        if (! empty($this->_meta['highlights'])) {
            $this->_mergeFlatKeysIntoNestedArray($data, $this->_meta['highlights']);
        }

        return (object) $data;
    }

    /**
     * {@inheritdoc}
     */
    public function freshTimestamp(): string
    {
        //        return Carbon::now()->toIso8601String();
        return Carbon::now()->format($this->getDateFormat());
    }

    /**
     * {@inheritdoc}
     */
    public function getDateFormat(): string
    {
        return $this->dateFormat ?: 'Y-m-d H:i:s';
    }

    public function getIndex(): string
    {
        return $this->index ?: parent::getTable();
    }

    public function setIndex($index = null)
    {
        if ($index) {
            return $this->index = $index;
        }
        $this->index = $this->index ?? $this->getTable();
        unset($this->table);
    }

    /**
     * {@inheritdoc}
     */
    public function getTable(): string
    {
        return $this->getIndex();
    }

    /**
     * {@inheritdoc}
     */
    public function getAttribute($key): mixed
    {
        if (! $key) {
            return null;
        }

        // Dot notation support.
        if (Str::contains($key, '.') && Arr::has($this->attributes, $key)) {
            return $this->getAttributeValue($key);
        }

        return parent::getAttribute($key);
    }

    /**
     * {@inheritdoc}
     */
    public function setAttribute($key, $value): mixed
    {

        if (Str::contains($key, '.')) {
            if (in_array($key, $this->getDates()) && $value) {
                $value = $this->fromDateTime($value);
            }

            Arr::set($this->attributes, $key, $value);

            return null;
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function fromDateTime($value): Carbon
    {
        return parent::asDateTime($value);
    }

    /**
     * {@inheritdoc}
     */
    protected function asDateTime($value): Carbon
    {

        return parent::asDateTime($value);
    }

    /**
     * {@inheritdoc}
     */
    public function getCasts(): array
    {
        return $this->casts;
    }

    /**
     * {@inheritdoc}
     */
    public function originalIsEquivalent($key): bool
    {
        if (! array_key_exists($key, $this->original)) {
            return false;
        }

        $attribute = Arr::get($this->attributes, $key);
        $original = Arr::get($this->original, $key);

        if ($attribute === $original) {
            return true;
        }

        if ($attribute === null) {
            return false;
        }

        if ($this->hasCast($key, static::$primitiveCastTypes)) {
            return $this->castAttribute($key, $attribute) ===
                $this->castAttribute($key, $original);
        }

        return is_numeric($attribute) && is_numeric($original) && strcmp((string) $attribute, (string) $original) === 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getForeignKey(): string
    {
        return Str::snake(class_basename($this)).'_'.ltrim($this->primaryKey, '_');
    }

    /**
     * {@inheritdoc}
     */
    public function newEloquentBuilder($query): Builder
    {
        $builder = new Builder($query);

        return $builder;
    }

    public function saveWithoutRefresh(array $options = []): bool
    {
        $this->mergeAttributesFromCachedCasts();

        $query = $this->newModelQuery();
        $query->setRefresh(false);

        if ($this->exists) {
            $saved = ! $this->isDirty() || $this->performUpdate($query);
        } else {
            $saved = $this->performInsert($query);
        }

        if ($saved) {
            $this->finishSave($options);
        }

        return $saved;
    }

    /**
     * Append one or more values to the underlying attribute value and sync with original.
     */
    protected function pushAttributeValues(string $column, array $values, bool $unique = false): void
    {
        $current = $this->getAttributeFromArray($column) ?: [];

        foreach ($values as $value) {
            // Don't add duplicate values when we only want unique values.
            if ($unique && (! is_array($current) || in_array($value, $current))) {
                continue;
            }

            $current[] = $value;
        }

        $this->attributes[$column] = $current;

        $this->syncOriginalAttribute($column);
    }

    /**
     * {@inheritdoc}
     */
    protected function getAttributeFromArray($key): mixed
    {
        // Support keys in dot notation.
        if (Str::contains($key, '.')) {
            return Arr::get($this->attributes, $key);
        }

        return parent::getAttributeFromArray($key);
    }

    /**
     * Remove one or more values to the underlying attribute value and sync with original.
     */
    protected function pullAttributeValues(string $column, array $values): void
    {
        $current = $this->getAttributeFromArray($column) ?: [];

        if (is_array($current)) {
            foreach ($values as $value) {
                $keys = array_keys($current, $value);

                foreach ($keys as $key) {
                    unset($current[$key]);
                }
            }
        }

        $this->attributes[$column] = array_values($current);

        $this->syncOriginalAttribute($column);
    }

    /**
     * {@inheritdoc}
     */
    protected function newBaseQueryBuilder()
    {
        $connection = $this->getConnection();
        if (! ($connection instanceof Connection)) {
            $config = $connection->getConfig() ?? null;
            if (! empty($config['driver'])) {
                throw new RuntimeException('Invalid connection settings; expected "elasticsearch", got "'.$config['driver'].'"');
            } else {
                throw new RuntimeException('Invalid connection settings; expected "elasticsearch"');
            }
        }

        $connection->setIndex($this->getTable());
        $connection->setMaxSize($this->getMaxSize());

        return new QueryBuilder($connection, $connection->getPostProcessor());
    }

    public function getMaxSize(): int
    {
        return static::MAX_SIZE;
    }

    /**
     * {@inheritdoc}
     */
    protected function removeTableFromKey($key): string
    {
        return $key;
    }

    /**
     * Get loaded relations for the instance without parent.
     */
    protected function getRelationsWithoutParent(): array
    {
        $relations = $this->getRelations();

        if ($parentRelation = $this->getParentRelation()) {
            unset($relations[$parentRelation->getQualifiedForeignKeyName()]);
        }

        return $relations;
    }

    /**
     * Get the parent relation.
     */
    public function getParentRelation(): \Illuminate\Database\Eloquent\Relations\Relation
    {
        return $this->parentRelation;
    }

    /**
     * Set the parent relation.
     */
    public function setParentRelation(Relation $relation): void
    {
        $this->parentRelation = $relation;
    }

    //----------------------------------------------------------------------
    // Helpers
    //----------------------------------------------------------------------

    protected function isGuardableColumn($key): bool
    {
        return true;
    }
}
