<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Eloquent;

use Illuminate\Database\Eloquent\Model as BaseModel;
use PDPhilip\Elasticsearch\Data\ModelMeta;

/**
 * @property object $searchHighlights
 * @property array $searchHighlightsAsArray
 * @property object $withHighlights
 */
abstract class Model extends BaseModel
{
    use ElasticsearchModel;

    protected $keyType = 'string';

    private static array $documentModelClasses = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        if (empty($this->attributes['id']) && $this->generatesUniqueIds) {
            $this->attributes['id'] = $this->newUniqueId();
        }
        $connection = $this->getConnection();
        $this->_meta = new ModelMeta($this->getTable(), $connection->getTablePrefix());
        if (! $this->defaultLimit) {
            $this->defaultLimit = $connection->getDefaultLimit();
        }

    }

    public function getTable()
    {
        if (! empty($this->index)) {
            return $this->index;
        }

        return parent::getTable();
    }

    /**
     * Indicates if the given model class is a ElasticSearch document model.
     * It must be a subclass of {@see BaseModel} and use the
     * {@see ElasticsearchModel} trait.
     *
     * implementation of https://github.com/mongodb/laravel-mongodb/blob/5.x/src/Eloquent/Model.php
     *
     * @param  class-string|object  $class
     */
    final public static function isElasticsearchModel(string|object $class): bool
    {
        if (is_object($class)) {
            $class = $class::class;
        }

        if (array_key_exists($class, self::$documentModelClasses)) {
            return self::$documentModelClasses[$class];
        }

        // We know all child classes of this class are document models.
        if (is_subclass_of($class, self::class)) {
            return self::$documentModelClasses[$class] = true;
        }

        // Document models must be subclasses of Laravel's base model class.
        if (! is_subclass_of($class, BaseModel::class)) {
            return self::$documentModelClasses[$class] = false;
        }

        // Document models must use the DocumentModel trait.
        return self::$documentModelClasses[$class] = array_key_exists(ElasticsearchModel::class, class_uses_recursive($class));
    }
}
