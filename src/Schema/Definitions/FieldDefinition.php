<?php

namespace PDPhilip\Elasticsearch\Schema\Definitions;

use Illuminate\Support\Fluent;

/**
 * @method $this analyzer(string|array $value)
 * @method $this copyTo(string $field)
 * @method $this coerce(bool $value)
 * @method $this docValues(bool $value)
 * @method $this norms(bool $value)
 * @method $this index(bool $value)
 * @method $this nullValue(mixed $value)
 * @method $this addFieldMap(string $type)
 * @method $this ignoreAbove(int $value)
 * @method $this indexOptions(int $value)
 * @removed $this addType(string $indexName = null)
 *
 * @removed $this format(string $value)
 * @removed $this path(string $expression = null)
 */
class FieldDefinition extends Fluent
{
    //
}
