<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Schema\Definitions;

/**
 * @method $this type(string|array $value)
 * @method $this tokenizeOnChars(string|array $value)
 * @method $this maxTokenLength(int $value)
 * @method $this minGram(int $value)
 * @method $this maxGram(int $value)
 * @method $this tokenChars(array $value)
 * @method $this customTokenChars(array $value)
 * @method $this bufferSize(int $value)
 * @method $this delimiter(string $value)
 * @method $this replacement(string $value)
 * @method $this reverse(bool $value)
 * @method $this skip(int $value)
 * @method $this pattern(string $value)
 * @method $this flags(string $value)
 * @method $this group(int $value)
 */
class TokenizerPropertyDefinition extends FluentDefinitions {}
