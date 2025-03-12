<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Schema\Definitions;

/**
 * @method $this type(string|array $value)
 * @method $this tokenizer(string|array $value)
 * @method $this charFilter(array $value)
 * @method $this filter(array $value)
 * @method $this positionIncrementGap(int $value)
 */
class AnalyzerPropertyDefinition extends FluentDefinitions {}
