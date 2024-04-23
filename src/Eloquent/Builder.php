<?php

namespace PDPhilip\Elasticsearch\Eloquent;

use Illuminate\Database\Eloquent\Builder as BaseEloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PDPhilip\Elasticsearch\Helpers\QueriesRelationships;
use RuntimeException;

class Builder extends BaseEloquentBuilder
{
    use QueriesRelationships;
    
    /**
     * The methods that should be returned from query builder.
     *
     * @var array
     */
    protected $passthru = [
        
        'average',
        'avg',
        'count',
        'dd',
        'doesntexist',
        'dump',
        'exists',
        'getbindings',
        'getconnection',
        'getgrammar',
        'insert',
        'insertgetid',
        'insertorignore',
        'insertusing',
        'max',
        'min',
        'pluck',
        'pull',
        'push',
        'raw',
        'sum',
        'toSql',
        //ES only:
        'matrix',
        'query',
        'rawsearch',
        'rawaggregation',
        'getindexsettings',
        'getindexmappings',
        'deleteindexifexists',
        'deleteindex',
        'truncate',
        'indexexists',
        'createindex',
        'search',
    ];
    
    
    /**
     * @inheritDoc
     */
    public function getConnection()
    {
        return $this->query->getConnection();
    }
    
    /**
     * @inerhitDoc
     */
    public function getModels($columns = ['*'])
    {
        
        $data = $this->query->get($columns);
        $results = $this->model->hydrate($data->all())->all();
        
        return ['results' => $results];
        
    }
    
    /**
     * @see getModels($columns = ['*'])
     */
    public function searchModels($columns = ['*'])
    {
        
        $data = $this->query->search($columns);
        $results = $this->model->hydrate($data->all())->all();
        
        return ['results' => $results];
        
    }
    
    /**
     * @inerhitDoc
     */
    public function get($columns = ['*'])
    {
        $builder = $this->applyScopes();
        $fetch = $builder->getModels($columns);
        if (count($models = $fetch['results']) > 0) {
            $models = $builder->eagerLoadRelations($models);
        }
        
        return $builder->getModel()->newCollection($models);
        
    }
    
    /**
     * @see get($columns = ['*'])
     */
    public function search($columns = ['*'])
    {
        $builder = $this->applyScopes();
        $fetch = $builder->searchModels($columns);
        if (count($models = $fetch['results']) > 0) {
            $models = $builder->eagerLoadRelations($models);
        }
        
        return $builder->getModel()->newCollection($models);
    }
    
    /**
     * @param    array    $values
     *
     * @return array
     */
    protected function addUpdatedAtColumn(array $values)
    {
        if (!$this->model->usesTimestamps() || $this->model->getUpdatedAtColumn() === null) {
            return $values;
        }
        
        $column = $this->model->getUpdatedAtColumn();
        $values = array_merge([$column => $this->model->freshTimestampString()], $values);
        
        return $values;
    }
    
    
    public function firstOrCreate(array $attributes = [], array $values = [])
    {
        $instance = $this->_instanceBuilder($attributes);
        if (!is_null($instance)) {
            return $instance;
        }
        
        return $this->create(array_merge($attributes, $values));
    }
    
    
    /**
     *
     * Fast create method for 'write and forget'
     *
     * @param    array    $attributes
     *
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Support\HigherOrderTapProxy|mixed|Builder
     */
    public function createWithoutRefresh(array $attributes = [])
    {
        return tap($this->newModelInstance($attributes), function ($instance) {
            $instance->saveWithoutRefresh();
        });
    }
    
    public function updateWithoutRefresh(array $attributes = [])
    {
        $query = $this->toBase();
        $query->setRefresh(false);
        
        return $query->update($this->addUpdatedAtColumn($attributes));
    }
    
    
    public function firstOrCreateWithoutRefresh(array $attributes = [], array $values = [])
    {
        $instance = $this->_instanceBuilder($attributes);
        if (!is_null($instance)) {
            return $instance;
        }
        
        return $this->createWithoutRefresh(array_merge($attributes, $values));
    }
    
    /**
     * @inheritdoc
     */
    public function chunkById($count, callable $callback, $column = '_id', $alias = null, $keepAlive = '5m')
    {
        $column ??= $this->defaultKeyName();
        $alias ??= $column;
        //remove sort
        $this->query->orders = [];
        
        if ($column === '_id') {
            //Use PIT
            
            
            return $this->_chunkByPit($count, $callback, $keepAlive);
        } else {
            $lastId = null;
            $page = 1;
            do {
                $clone = clone $this;
                $results = $clone->forPageAfterId($count, $lastId, $column)->get();
                $countResults = $results->count();
                if ($countResults == 0) {
                    break;
                }
                if ($callback($results, $page) === false) {
                    return false;
                }
                $aliasClean = $alias;
                if (substr($aliasClean, -8) == '.keyword') {
                    $aliasClean = substr($aliasClean, 0, -8);
                }
                $lastId = data_get($results->last(), $aliasClean);
                
                if ($lastId === null) {
                    throw new RuntimeException("The chunkById operation was aborted because the [{$aliasClean}] column is not present in the query result.");
                }
                
                unset($results);
                
                $page++;
            } while ($countResults == $count);
            
            return true;
        }
        
        
    }
    
    
    public function chunk($count, callable $callback, $keepAlive = '5m')
    {
        //default to using PIT
        return $this->_chunkByPit($count, $callback, $keepAlive);
    }
    
    
    
    
    //----------------------------------------------------------------------
    // ES Filters
    //----------------------------------------------------------------------
    
    /**
     * @param    string    $field
     * @param    array    $topLeft
     * @param    array    $bottomRight
     *
     * @return $this
     */
    public function filterGeoBox(string $field, array $topLeft, array $bottomRight)
    {
        $this->query->filterGeoBox($field, $topLeft, $bottomRight);
        
        return $this;
    }
    
    /**
     * @param    string    $field
     * @param    string    $distance
     * @param    array    $geoPoint
     *
     * @return $this
     */
    public function filterGeoPoint(string $field, string $distance, array $geoPoint)
    {
        $this->query->filterGeoPoint($field, $distance, $geoPoint);
        
        return $this;
    }
    
    //----------------------------------------------------------------------
    // ES Search query builders
    //----------------------------------------------------------------------
    
    /**
     * @param    string    $term
     * @param    int|null    $boostFactor
     *
     * @return $this
     */
    public function term(string $term, int $boostFactor = null)
    {
        $this->query->searchQuery($term, $boostFactor);
        
        return $this;
    }
    
    /**
     * @param    string    $term
     * @param    int|null    $boostFactor
     *
     * @return $this
     */
    public function andTerm(string $term, int $boostFactor = null)
    {
        $this->query->searchQuery($term, $boostFactor, 'AND');
        
        return $this;
    }
    
    /**
     * @param    string    $term
     * @param    int|null    $boostFactor
     *
     * @return $this
     */
    public function orTerm(string $term, int $boostFactor = null)
    {
        $this->query->searchQuery($term, $boostFactor, 'OR');
        
        return $this;
    }
    
    /**
     * @param    string    $term
     * @param    int|null    $boostFactor
     *
     * @return $this
     */
    public function fuzzyTerm(string $term, int $boostFactor = null)
    {
        $this->query->searchQuery($term, $boostFactor, null, 'fuzzy');
        
        return $this;
    }
    
    /**
     * @param    string    $term
     * @param    int|null    $boostFactor
     *
     * @return $this
     */
    public function andFuzzyTerm(string $term, int $boostFactor = null)
    {
        $this->query->searchQuery($term, $boostFactor, 'AND', 'fuzzy');
        
        return $this;
    }
    
    /**
     * @param    string    $term
     * @param    int|null    $boostFactor
     *
     * @return $this
     */
    public function orFuzzyTerm(string $term, int $boostFactor = null)
    {
        $this->query->searchQuery($term, $boostFactor, 'OR', 'fuzzy');
        
        return $this;
    }
    
    /**
     * @param    string    $regEx
     * @param    int|null    $boostFactor
     *
     * @return $this
     */
    public function regEx(string $regEx, int $boostFactor = null)
    {
        $this->query->searchQuery($regEx, $boostFactor, null, 'regex');
        
        return $this;
    }
    
    /**
     * @param    string    $regEx
     * @param    int|null    $boostFactor
     *
     * @return $this
     */
    public function andRegEx(string $regEx, int $boostFactor = null)
    {
        $this->query->searchQuery($regEx, $boostFactor, 'AND', 'regex');
        
        return $this;
    }
    
    /**
     * @param    string    $regEx
     * @param    int|null    $boostFactor
     *
     * @return $this
     */
    public function orRegEx(string $regEx, int $boostFactor = null)
    {
        $this->query->searchQuery($regEx, $boostFactor, 'OR', 'regex');
        
        return $this;
    }
    
    /**
     * @param $value
     *
     * @return $this
     */
    public function minShouldMatch($value)
    {
        $this->query->minShouldMatch($value);
        
        return $this;
    }
    
    /**
     * @param    float    $value
     *
     * @return $this
     */
    public function minScore(float $value)
    {
        $this->query->minScore($value);
        
        return $this;
    }
    
    /**
     * @param    string    $field
     * @param    int|null    $boostFactor
     *
     * @return $this
     */
    public function field(string $field, int $boostFactor = null)
    {
        $this->query->searchField($field, $boostFactor);
        
        return $this;
    }
    
    /**
     * @param    array    $fields
     *
     * @return $this
     */
    public function fields(array $fields)
    {
        $this->query->searchFields($fields);
        
        return $this;
    }
    
    public function hydrate(array $items)
    {
        $instance = $this->newModelInstance();
        
        return $instance->newCollection(array_map(function ($item) use ($items, $instance) {
            $recordIndex = null;
            if (is_array($item)) {
                $recordIndex = !empty($item['_index']) ? $item['_index'] : null;
                if ($recordIndex) {
                    unset($item['_index']);
                }
            }
            $meta = [];
            if (isset($item['_meta'])) {
                $meta = $item['_meta'];
                unset($item['_meta']);
            }
            $model = $instance->newFromBuilder($item);
            if ($recordIndex) {
                $model->setRecordIndex($recordIndex);
                $model->setIndex($recordIndex);
                
            }
            if ($meta) {
                $model->setMeta($meta);
            }
            if (count($items) > 1) {
                $model->preventsLazyLoading = Model::preventsLazyLoading();
            }
            
            return $model;
        }, $items));
    }
    
    
    
    //----------------------------------------------------------------------
    // Private methods
    //----------------------------------------------------------------------
    
    private function _instanceBuilder(array $attributes = [])
    {
        $instance = clone $this;
        
        foreach ($attributes as $field => $value) {
            $method = is_string($value) ? 'whereExact' : 'where';
            
            if (is_array($value)) {
                foreach ($value as $v) {
                    $specificMethod = is_string($v) ? 'whereExact' : 'where';
                    $instance = $instance->$specificMethod($field, $v);
                }
            } else {
                $instance = $instance->$method($field, $value);
            }
        }
        
        return $instance->first();
    }
    
    
    private function _chunkByPit($count, callable $callback, $keepAlive = '5m')
    {
        $pitId = $this->query->openPit($keepAlive);
        
        $searchAfter = null;
        $page = 1;
        do {
            $clone = clone $this;
            $search = $clone->query->pitFind($count, $pitId, $searchAfter, $keepAlive);
            $meta = $search->getMetaData();
            $searchAfter = $meta['last_sort'];
            $results = $this->hydrate($search->data);
            $countResults = $results->count();
            
            if ($countResults == 0) {
                break;
            }
            
            if ($callback($results, $page) === false) {
                return false;
            }
            
            unset($results);
            
            $page++;
        } while ($countResults == $count);
        
        $this->query->closePit($pitId);
    }
}
