<?php

namespace PDPhilip\Elasticsearch\Query\Options;

use Illuminate\Support\Fluent;
use Illuminate\Support\Str;

/**
 * @mixin MatchOptions
 * @mixin TermOptions
 * @mixin FuzzyOptions
 * @mixin SearchOptions
 * @mixin DateOptions
 * @mixin PhraseOptions
 * @mixin PhrasePrefixOptions
 * @mixin PrefixOptions
 * @mixin RegexOptions
 */
abstract class QueryOptions extends Fluent
{
    public function __call($method, $parameters)
    {
        $key = Str::snake($method);

        $this->{$key} = $parameters[0];

        return $this;
    }

    abstract public function allowedOptions(): array;
}
