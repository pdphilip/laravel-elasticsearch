<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Schema\Definitions;

use Illuminate\Support\Fluent;

/**
 * Class ColumnDefinition
 * @method PropertyDefinition boost(int $boost)
 * @method PropertyDefinition dynamic(bool $dynamic = true)
 * @method PropertyDefinition fields(\Closure $field)
 * @method PropertyDefinition format(string $format)
 * @method PropertyDefinition index(bool $index = true)
 * @method PropertyDefinition properties(\Closure $field)
 * @method PropertyDefinition nullValue(mixed $null_value)
 * @package PDPhilip\Elasticsearch\Schema\Definitions
 */
class PropertyDefinition extends Fluent
{
    public function nullable(){
      //
    }

    public function unsigned(){
      //
    }

    public function charset(){
      //
    }

    public function default(mixed $default): PropertyDefinition
    {
      return $this->nullValue($default);
    }
}
