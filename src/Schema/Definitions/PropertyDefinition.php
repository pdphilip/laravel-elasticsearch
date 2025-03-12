<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Schema\Definitions;

use Closure;
use Illuminate\Support\Fluent;

/**
 * Class ColumnDefinition
 *
 * @method PropertyDefinition boost(int $boost)
 * @method PropertyDefinition dynamic(bool $value)
 * @method PropertyDefinition fields(Closure $field)
 * @method PropertyDefinition format(string $format)
 * @method PropertyDefinition indexField(bool $value) //was index but was not being picked up
 * @method PropertyDefinition properties(Closure $field)
 * @method PropertyDefinition nullValue(mixed $value)
 * @method PropertyDefinition copyTo(string $field)
 * @method PropertyDefinition analyzer(string $name)
 * @method PropertyDefinition searchAnalyzer(string $name)
 * @method PropertyDefinition searchQuoteAnalyzer(string $name)
 * @method PropertyDefinition coerce(bool $value)
 * @method PropertyDefinition docValues(bool $value)
 * @method PropertyDefinition norms(bool $value)
 */
class PropertyDefinition extends Fluent
{
    public function nullable()
    {
        //
    }

    public function unsigned()
    {
        //
    }

    public function charset()
    {
        //
    }

    public function default(mixed $default): PropertyDefinition
    {
        return $this->nullValue($default);
    }
}
