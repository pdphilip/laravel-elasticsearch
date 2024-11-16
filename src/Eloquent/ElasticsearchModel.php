<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PDPhilip\Elasticsearch\Connection;
use PDPhilip\Elasticsearch\Eloquent\Docs\ModelDocs;
use PDPhilip\Elasticsearch\Enums\WaitFor;
use PDPhilip\Elasticsearch\Exceptions\RuntimeException;
use PDPhilip\Elasticsearch\Meta\ModelMetaData;
use PDPhilip\Elasticsearch\Query\Builder as QueryBuilder;
use PDPhilip\Elasticsearch\Traits\Eloquent\Searchable;

/**
 * @mixin \PDPhilip\Elasticsearch\Query\Builder
 * @mixin \PDPhilip\Elasticsearch\Eloquent\Builder
 */
trait ElasticsearchModel
{
    use HasUuids, HybridRelations, Searchable;

    /**
     * The table suffix associated with the model.
     *
     * @var string|null
     */
    protected ?string $tableSuffix;

    protected ?string $recordIndex;

    protected ?Relation $parentRelation;

    protected ?ModelMetaData $_meta;

    public function newUniqueId()
    {
        // this is the equivelent of how elasticsearch generates UUID
        // see: https://github.com/elastic/elasticsearch/blob/2f2ddad00492fcac8fbfc272607a8db91d279385/server/src/main/java/org/elasticsearch/common/TimeBasedUUIDGenerator.java#L67
        return base64_encode((string) Str::orderedUuid());
    }

  /**
   * {@inheritdoc}
   */
  public function newFromBuilder($attributes = [], $connection = null)
  {
    $model = parent::newFromBuilder($attributes, $connection);
    $model->setTable($attributes['_index']);

    return $model;
  }

    /**
     * {@inheritdoc}
     */
    public function getQualifiedKeyName(): string
    {
        return $this->getKeyName();
    }

    public function getMeta(): ModelMetaData
    {
        return $this->_meta;
    }

    public function getMetaAsArray(): array
    {
        return $this->_meta->asArray();
    }

    public function setMeta($meta): static
    {
        $this->_meta = new ModelMetaData($meta);

        return $this;
    }

    public function getSearchHighlightsAttribute(): ?object
    {
        return $this->_meta->parseHighlights();
    }

    public function getSearchHighlightsAsArrayAttribute(): array
    {
        return $this->_meta->getHighlights();
    }

    public function getWithHighlightsAttribute(): object
    {
        $data = $this->attributes;
        $mutators = array_values(array_diff($this->getMutatedAttributes(), [
            'id',
            'search_highlights',
            'search_highlights_as_array',
            'with_highlights',
        ]));
        if ($mutators) {
            foreach ($mutators as $mutator) {
                $data[$mutator] = $this->{$mutator};
            }
        }

        return (object) $this->_meta->parseHighlights($data);
    }

    /**
     * {@inheritdoc}
     */
    public function freshTimestamp(): string
    {
        // return Carbon::now()->toIso8601String();
        return Carbon::now()->format($this->getDateFormat());
    }

  /**
   * Set the table suffix associated with the model.
   *
   * @param string|null $suffix
   *
   * @return self
   */
  public function setSuffix($suffix): self
  {
    $this->tableSuffix = $suffix;

    return $this;
  }

  /**
   * Get the table suffix associated with the model.
   *
   * @return string
   */
  public function getSuffix(): string
  {
    return $this->tableSuffix ?? '';
  }

    /**
     * {@inheritdoc}
     */
    public function getDateFormat(): string
    {
        return $this->dateFormat ?: 'Y-m-d H:i:s';
    }

    /**
     * {@inheritdoc}
     */
    public function getAttribute($key): mixed
    {
        if (! $key) {
            return null;
        }

        $key = (string) $key;

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

    public function fromDateTime(mixed $value): Carbon
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
            return $this->castAttribute($key, $attribute) === $this->castAttribute($key, $original);
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

    public function saveWithoutRefresh(array $options = []): bool
    {

        //TODO:

        $this->mergeAttributesFromCachedCasts();

        $query = $this->newModelQuery();
        //@phpstan-ignore-next-line
        $query->setRefresh(WaitFor::FALSE);

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

  /** @inheritdoc */
  public function push()
  {
    $parameters = func_get_args();
    if ($parameters) {
      $unique = false;

      if (count($parameters) === 3) {
        [$column, $values, $unique] = $parameters;
      } else {
        [$column, $values] = $parameters;
      }

      // Do batch push by default.
      $values = Arr::wrap($values);

      $query = $this->setKeysForSaveQuery($this->newQuery());

      $this->pushAttributeValues($column, $values, $unique);

      return $query->push($column, $values, $unique);
    }

    return parent::push();
  }


  /**
   * Remove one or more values from an array.
   *
   * @param  string $column
   * @param  mixed  $values
   *
   * @return mixed
   */
  public function pull($column, $values)
  {
    // Do batch pull by default.
    $values = Arr::wrap($values);

    $query = $this->setKeysForSaveQuery($this->newQuery());

    $this->pullAttributeValues($column, $values);

    return $query->pull($column, $values);
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
     * Get the database connection instance.
     *
     *
     * @throws RuntimeException
     */
    public function getConnection(): Connection
    {
        $connection = clone static::resolveConnection($this->getConnectionName());
        if (! ($connection instanceof Connection)) {
            $config = $connection->getConfig() ?? null;
            if (! empty($config['driver'])) {
                throw new RuntimeException('Invalid connection settings; expected "elasticsearch", got "'.$config['driver'].'"');
            } else {
                throw new RuntimeException('Invalid connection settings; expected "elasticsearch"');
            }
        }

        return $connection;
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

      $parentRelation = $this->getParentRelation();
      if ($parentRelation) {
        unset($relations[$parentRelation->getQualifiedForeignKeyName()]);
      }

      return $relations;
    }

    /**
     * Get the parent relation.
     */
    public function getParentRelation(): ?Relation
    {
        return $this->parentRelation ?? null;
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
