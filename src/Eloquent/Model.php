<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Eloquent;

use Illuminate\Database\Eloquent\Model as BaseModel;

/**
 * @property object $searchHighlights
 * @property array $searchHighlightsAsArray
 * @property object $withHighlights
 */
abstract class Model extends BaseModel
{
    use ElasticsearchModel;

    /**
     * The primary key type.
     *
     * @var string
     */
    protected $keyType = 'string';

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        if (empty($this->attributes['id'])) {
            $this->attributes['id'] = $this->newUniqueId();
        }
    }

    private static $documentModelClasses = [];

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
