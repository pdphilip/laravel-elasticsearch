<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Schema;

use PDPhilip\Elasticsearch\Exceptions\InvalidFieldTypeException;
use PDPhilip\Elasticsearch\Schema\Definitions\PropertyDefinition;

trait ManagesDefaultMigrations
{
    /**
     * {@inheritdoc}
     */
    public function binary($name, $length = null, $fixed = false, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('binary', $name, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function boolean($column, array $parameters = [])
    {
        return $this->addColumn('boolean', $column, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function char($column, $length = null, array $parameters = [])
    {
        return $this->keyword($column, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function dateTimeTz($column, $precision = 0, array $parameters = [])
    {
        return $this->date($column, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function decimal($column, $total = 8, $places = 2, $unsigned = false, array $parameters = [])
    {
        return $this->addColumn('scaled_float', $column, [
            'scaling_factor' => pow(10, $places),
            ...$parameters,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function double($column, $total = null, $places = null, $unsigned = false, array $parameters = [])
    {
        return $this->addColumn('double', $column, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function enum($column, array $allowed, array $parameters = [])
    {

        $allowed = implode("', '", $allowed);

        $script = "def allowed = ['{$allowed}'];
      if (!allowed.contains(params._source.{$column})) {
        throw new IllegalArgumentException(\"Value for '{$column}' must be one of \" + allowed);
      }
      emit(params._source.{$column});";

        return $this->addColumn('keyword', $column, [
            'script' => $script,
            'on_script_error' => 'fail',
            ...$parameters,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function float($column, $total = null, $precision = null, $unsigned = false, array $parameters = []): PropertyDefinition
    {

        if ($precision <= 24) {
            // Use single precision equivalent in Elasticsearch
            return $this->addColumn('float', $column, $parameters);
        } elseif ($precision <= 53) {
            // Use double precision equivalent in Elasticsearch
            return $this->addColumn('double', $column, $parameters);
        }

        // For higher precision, use scaled_float with a scaling factor
        return $this->addColumn('scaled_float', $column, [
            'scaling_factor' => pow(10, $precision - 1),
            ...$parameters,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function foreignId($column, array $parameters = [])
    {
        return $this->keyword($column, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function foreignIdFor($model, $column = null, array $parameters = [])
    {
        if (is_string($model)) {
            $model = new $model;
        }

        $column = $column ?: $model->getForeignKey();

        return $this->keyword($column, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function foreignUlid($column, $length = 26, array $parameters = [])
    {
        return $this->foreignId($column, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function foreignUuid($column, $length = 26, array $parameters = [])
    {
        return $this->foreignId($column, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function geography($column, $subtype = null, $srid = 4326, array $parameters = [])
    {
        if ($subtype == 'shape') {
            return $this->geoShape($column, $parameters);
        }

        return $this->geoPoint($column, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function id($column = 'id', array $parameters = [])
    {
        return $this->keyword($column, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function ipAddress($column = 'ip_address', array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('ip', $column, $parameters);
    }

    /**
     * {@inheritdoc}
     *
     * @param  bool  $hasKeyword  adds a keyword subfield.
     */
    public function longText($column, bool $hasKeyword = false, array $parameters = [])
    {
        return $this->text($column, $hasKeyword, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function macAddress($column = 'mac_address', array $parameters = [])
    {
        return $this->keyword($column, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function integer($column, $autoIncrement = false, $unsigned = false, array $parameters = [], string $type = 'integer'): PropertyDefinition
    {
        return $this->addColumn($unsigned ? 'unsigned_long' : $type, $column, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function tinyInteger($column, $autoIncrement = false, $unsigned = false, array $parameters = []): PropertyDefinition
    {
        return $this->integer($column, $autoIncrement, $unsigned, $parameters, 'byte');
    }

    /**
     * {@inheritdoc}
     */
    public function smallInteger($column, $autoIncrement = false, $unsigned = false, array $parameters = []): PropertyDefinition
    {
        return $this->integer($column, $autoIncrement, $unsigned, $parameters, 'short');
    }

    /**
     * {@inheritdoc}
     */
    public function mediumInteger($column, $autoIncrement = false, $unsigned = false, array $parameters = []): PropertyDefinition
    {
        return $this->integer($column, $autoIncrement, $unsigned, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function bigInteger($column, $autoIncrement = false, $unsigned = false, array $parameters = []): PropertyDefinition
    {
        return $this->integer($column, $autoIncrement, $unsigned, $parameters, 'long');
    }

    /**
     * {@inheritdoc}
     *
     * @param  bool  $hasKeyword  adds a keyword subfield.
     */
    public function tinyText($column, bool $hasKeyword = false, array $parameters = [])
    {
        return $this->text($column, $hasKeyword, $parameters);
    }

    /**
     * {@inheritdoc}
     *
     * @param  bool  $hasKeyword  adds a keyword subfield.
     */
    public function mediumText($column, bool $hasKeyword = false, array $parameters = [])
    {
        return $this->text($column, $hasKeyword, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function morphs(...$args): void
    {

        [$name] = $args;

        $this->keyword("{$name}_type");
        $this->keyword("{$name}_id");
    }

    /**
     * {@inheritdoc}
     */
    public function nullableTimestamps($precision = 0): void
    {
        $this->timestamps($precision);
    }

    /**
     * {@inheritdoc}
     */
    public function timestamps($precision = 0): void
    {
        $this->timestamp('created_at');
        $this->timestamp('updated_at');
    }

    /**
     * {@inheritdoc}
     */
    public function timestamp($column, $precision = 0, array $parameters = []): PropertyDefinition
    {
        return $this->date($column, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function timestampTz($column, $precision = 0, array $parameters = [])
    {
        return $this->timestamp($column, $precision, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function rememberToken()
    {
        return $this->keyword('remember_token');
    }

    /**
     * {@inheritdoc}
     */
    public function softDeletesTz($column = 'deleted_at', $precision = 0, array $parameters = []): PropertyDefinition
    {
        return $this->timestamp($column, $precision, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function softDeletes($column = 'deleted_at', $precision = 0, array $parameters = []): PropertyDefinition
    {
        return $this->timestamp($column, $precision, $parameters);
    }

    /**
     * {@inheritdoc}
     *
     * @param  bool  $hasKeyword  adds a keyword subfield.
     */
    public function string($column, $length = null, bool $hasKeyword = false, array $parameters = []): PropertyDefinition
    {
        return $this->text($column, $hasKeyword, $parameters);
    }

    /**
     * {@inheritdoc}
     *
     *
     * @return PropertyDefinition
     */
    public function timeTz($column, $precision = 0, array $parameters = [])
    {
        return $this->time($column, $precision, $parameters);
    }

    /**
     * {@inheritdoc}
     *
     *
     * @return PropertyDefinition
     */
    public function time($column, $precision = 0, array $parameters = [])
    {
        return $this->addColumn('date', $column, [
            'format' => 'hour_minute_second||strict_hour_minute_second||HH:mm:ssZ',
            ...$parameters,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function unsignedBigInteger($column, $autoIncrement = false, array $parameters = []): PropertyDefinition
    {
        return $this->bigInteger($column, $autoIncrement, true, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function unsignedInteger($column, $autoIncrement = false, array $parameters = []): PropertyDefinition
    {
        return $this->integer($column, $autoIncrement, true, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function unsignedMediumInteger($column, $autoIncrement = false, array $parameters = []): PropertyDefinition
    {
        return $this->mediumInteger($column, $autoIncrement, true, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function unsignedSmallInteger($column, $autoIncrement = false, array $parameters = []): PropertyDefinition
    {
        return $this->smallInteger($column, $autoIncrement, true, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function unsignedTinyInteger($column, $autoIncrement = false, array $parameters = []): PropertyDefinition
    {
        return $this->tinyInteger($column, $autoIncrement, true, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function ulidMorphs(...$args): void
    {
        $this->morphs(...$args);
    }

    /**
     * {@inheritdoc}
     */
    public function uuidMorphs(...$args): void
    {
        $this->morphs(...$args);
    }

    /**
     * {@inheritdoc}
     */
    public function ulid($column = 'ulid', $length = 26, array $parameters = []): PropertyDefinition
    {
        return $this->char($column, $length, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function uuid($column = 'uuid', array $parameters = []): PropertyDefinition
    {
        return $this->char($column, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function year($column, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('date', $column, [
            'format' => 'yyyy',
            ...$parameters,
        ]);
    }

    /**
     * {@inheritdoc}
     *
     * ---------------------------------------------------------------------
     *   Internal Laravel init migration catchers
     *   *Case for when ES is the only datasource and migration tables are trying to be created
     * ---------------------------------------------------------------------
     */
    public function increments($column)
    {
        return $this->keyword($column);
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidFieldTypeException
     */
    public function bigIncrements($column)
    {
        throw new InvalidFieldTypeException('Increments are not supported by ElasticSearch.');
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidFieldTypeException
     */
    public function mediumIncrements($column)
    {
        throw new InvalidFieldTypeException('Increments are not supported by ElasticSearch.');
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidFieldTypeException
     */
    public function tinyIncrements($column)
    {
        throw new InvalidFieldTypeException('Increments are not supported by ElasticSearch.');
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidFieldTypeException
     */
    public function smallIncrements($column)
    {
        throw new InvalidFieldTypeException('Increments are not supported by ElasticSearch.');
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidFieldTypeException
     */
    public function set($column, array $allowed)
    {
        throw new InvalidFieldTypeException('set(s) are not supported by ElasticSearch use keyword instead.');
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidFieldTypeException
     */
    public function json($column)
    {
        throw new InvalidFieldTypeException('ElasticSearch is all json. No need to specify a type.');
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidFieldTypeException
     */
    public function jsonb($column)
    {
        throw new InvalidFieldTypeException('ElasticSearch is all json. No need to specify a type.');
    }
}
