<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Schema\Definitions;

use Illuminate\Support\Fluent;

/**
 * @method $this type(string|array $value)
 * @method $this tokenizer(string|array $value)
 * @method $this filter(array $value)
 * @method $this char_filter(array $value)
 * @method $this pattern(string|array $value)
 * @method $this mappings(string|array $value)
 * @method $this stopwords(string|array $value)
 * @method $this replacement(string|array $value)
 */
class AnalyzerPropertyDefinition extends Fluent
{
    //
}
