<?php

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
    
    protected $index;
    
    protected $recordIndex;
    
    protected $primaryKey = '_id';
    
    protected $keyType = 'string';
    
    protected $parentRelation;
    
    protected $_meta = [];
    
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setIndex();
        $this->setRecordIndex();
        $this->forcePrimaryKey();
    }
    
    
    public function setIndex($index = null)
    {
        if ($index) {
            return $this->index = $index;
        }
        $this->index = $this->index ?? $this->getTable();
        unset($this->table);
    }
    
    public function setRecordIndex($recordIndex = null)
    {
        if ($recordIndex) {
            return $this->recordIndex = $recordIndex;
        }
        
        return $this->recordIndex = $this->index;
    }
    
    public function getRecordIndex()
    {
        return $this->recordIndex;
    }
    
    public function setTable($index)
    {
        $this->index = $index;
        unset($this->table);
        
        return $this;
    }
    
    
    public function forcePrimaryKey()
    {
        $this->primaryKey = '_id';
    }
    
    
    public function getMaxSize()
    {
        return static::MAX_SIZE;
    }
    
    public function getIdAttribute($value = null)
    {
        // If no value for id, then set ES's _id
        if (!$value && array_key_exists('_id', $this->attributes)) {
            $value = $this->attributes['_id'];
        }
        
        return $value;
    }
    
    /**
     * @inheritdoc
     */
    public function getQualifiedKeyName()
    {
        return $this->getKeyName();
    }
    
    /**
     * @inheritdoc
     */
    public function fromDateTime($value)
    {
        return parent::asDateTime($value);
    }
    
    /**
     * @inheritdoc
     */
    protected function asDateTime($value)
    {
        
        return parent::asDateTime($value);
    }
    
    /**
     * @inheritdoc
     */
    public function getDateFormat()
    {
        return $this->dateFormat ? : 'Y-m-d H:i:s';
    }
    
    public function setMeta($meta)
    {
        $this->_meta = $meta;
        
        return $this;
    }
    
    public function getMeta()
    {
        return (object)$this->_meta;
    }
    
    public function getSearchHighlightsAttribute()
    {
        if (!empty($this->_meta['highlights'])) {
            $data = [];
            $this->_mergeFlatKeysIntoNestedArray($data, $this->_meta['highlights']);
            
            return (object)$data;
        }
        
        return null;
    }
    
    public function getSearchHighlightsAsArrayAttribute()
    {
        if (!empty($this->_meta['highlights'])) {
            return $this->_meta['highlights'];
        }
        
        return [];
    }
    
    public function getWithHighlightsAttribute()
    {
        $data = $this->attributes;
        $mutators = array_values(array_diff($this->getMutatedAttributes(), ['id', 'search_highlights', 'search_highlights_as_array', 'with_highlights']));
        if ($mutators) {
            foreach ($mutators as $mutator) {
                $data[$mutator] = $this->{$mutator};
            }
        }
        if (!empty($this->_meta['highlights'])) {
            $this->_mergeFlatKeysIntoNestedArray($data, $this->_meta['highlights']);
        }
        
        return (object)$data;
    }
    
    /**
     * @inheritdoc
     */
    public function freshTimestamp()
    {
//        return Carbon::now()->toIso8601String();
        return Carbon::now()->format($this->getDateFormat());
    }
    
    public function getIndex()
    {
        return $this->index ? : parent::getTable();
    }
    
    /**
     * @inheritdoc
     */
    public function getTable()
    {
        return $this->getIndex();
    }
    
    /**
     * @inheritdoc
     */
    public function getAttribute($key)
    {
        if (!$key) {
            return;
        }
        
        // Dot notation support.
        if (Str::contains($key, '.') && Arr::has($this->attributes, $key)) {
            return $this->getAttributeValue($key);
        }
        
        return parent::getAttribute($key);
    }
    
    /**
     * @inheritdoc
     */
    protected function getAttributeFromArray($key)
    {
        // Support keys in dot notation.
        if (Str::contains($key, '.')) {
            return Arr::get($this->attributes, $key);
        }
        
        return parent::getAttributeFromArray($key);
    }
    
    /**
     * @inheritdoc
     */
    public function setAttribute($key, $value)
    {
        
        if (Str::contains($key, '.')) {
            if (in_array($key, $this->getDates()) && $value) {
                $value = $this->fromDateTime($value);
            }
            
            Arr::set($this->attributes, $key, $value);
            
            return;
        }
        
        return parent::setAttribute($key, $value);
    }
    
    /**
     * @inheritdoc
     */
    public function getCasts()
    {
        return $this->casts;
    }
    
    /**
     * @inheritdoc
     */
    public function originalIsEquivalent($key)
    {
        if (!array_key_exists($key, $this->original)) {
            return false;
        }
        
        $attribute = Arr::get($this->attributes, $key);
        $original = Arr::get($this->original, $key);
        
        if ($attribute === $original) {
            return true;
        }
        
        if (null === $attribute) {
            return false;
        }
        
        if ($this->hasCast($key, static::$primitiveCastTypes)) {
            return $this->castAttribute($key, $attribute) ===
                $this->castAttribute($key, $original);
        }
        
        return is_numeric($attribute) && is_numeric($original) && strcmp((string)$attribute, (string)$original) === 0;
    }
    
    
    /**
     * Append one or more values to the underlying attribute value and sync with original.
     *
     * @param    string    $column
     * @param    array    $values
     * @param    bool    $unique
     */
    protected function pushAttributeValues($column, array $values, $unique = false)
    {
        $current = $this->getAttributeFromArray($column) ? : [];
        
        foreach ($values as $value) {
            // Don't add duplicate values when we only want unique values.
            if ($unique && (!is_array($current) || in_array($value, $current))) {
                continue;
            }
            
            $current[] = $value;
        }
        
        $this->attributes[$column] = $current;
        
        $this->syncOriginalAttribute($column);
    }
    
    /**
     * Remove one or more values to the underlying attribute value and sync with original.
     *
     * @param    string    $column
     * @param    array    $values
     */
    protected function pullAttributeValues($column, array $values)
    {
        $current = $this->getAttributeFromArray($column) ? : [];
        
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
     * @inheritdoc
     */
    public function getForeignKey()
    {
        return Str::snake(class_basename($this)).'_'.ltrim($this->primaryKey, '_');
    }
    
    /**
     * Set the parent relation.
     *
     * @param    \Illuminate\Database\Eloquent\Relations\Relation    $relation
     */
    public function setParentRelation(Relation $relation)
    {
        $this->parentRelation = $relation;
    }
    
    /**
     * Get the parent relation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     */
    public function getParentRelation()
    {
        return $this->parentRelation;
    }
    
    /**
     * @inheritdoc
     */
    public function newEloquentBuilder($query)
    {
        $builder = new Builder($query);
        
        return $builder;
    }
    
    /**
     * @inheritdoc
     */
    protected function newBaseQueryBuilder()
    {
        $connection = $this->getConnection();
        if (!($connection instanceof Connection)) {
            $config = $connection->getConfig() ?? null;
            if (!empty($config['driver'])) {
                throw new RuntimeException('Invalid connection settings; expected "elasticsearch", got "'.$config['driver'].'"');
            } else {
                throw new RuntimeException('Invalid connection settings; expected "elasticsearch"');
            }
        }
        
        $connection->setIndex($this->getTable());
        $connection->setMaxSize($this->getMaxSize());
        
        
        return new QueryBuilder($connection, $connection->getPostProcessor());
    }
    
    /**
     * @inheritdoc
     */
    protected function removeTableFromKey($key)
    {
        return $key;
    }
    
    
    /**
     * Get loaded relations for the instance without parent.
     *
     * @return array
     */
    protected function getRelationsWithoutParent()
    {
        $relations = $this->getRelations();
        
        if ($parentRelation = $this->getParentRelation()) {
            unset($relations[$parentRelation->getQualifiedForeignKeyName()]);
        }
        
        return $relations;
    }
    
    
    protected function isGuardableColumn($key)
    {
        return true;
    }
    
    
    public function saveWithoutRefresh(array $options = [])
    {
        $this->mergeAttributesFromCachedCasts();
        
        $query = $this->newModelQuery();
        $query->setRefresh(false);
        
        if ($this->exists) {
            $saved = $this->isDirty() ? $this->performUpdate($query) : true;
        } else {
            $saved = $this->performInsert($query);
        }
        
        if ($saved) {
            $this->finishSave($options);
        }
        
        return $saved;
    }
    
    
    //----------------------------------------------------------------------
    // Helpers
    //----------------------------------------------------------------------
    
    protected function _mergeFlatKeysIntoNestedArray(&$data, $attrs)
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
                        if (!isset($current[$part]) || !is_array($current[$part])) {
                            $current[$part] = [];
                        }
                        $current = &$current[$part];
                    }
                }
            }
            
        }
    }
    
}
