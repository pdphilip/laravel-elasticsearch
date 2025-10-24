<?php

namespace PDPhilip\Elasticsearch\Query\Options;

/**
 * SimpleQueryStringOptions for Simple Query String queries.
 *
 * Mirrors Elasticsearch simple_query_string parameters:
 * - Safer parsing (ignores invalid syntax)
 * - Supports flags, boosting, default operator, wildcard analysis, etc.
 *
 * @method $this flags(string $value) // e.g. "ALL", "AND|OR|NOT|PHRASE|PREFIX|PRECEDENCE|ESCAPE|WHITESPACE|FUZZY|NEAR|SLOP"
 * @method $this defaultOperator(string $value) // "OR" | "AND"
 * @method $this analyzeWildcard(bool $value)
 * @method $this analyzer(string $analyzer)
 * @method $this autoGenerateSynonymsPhraseQuery(bool $value)
 * @method $this boost(float $value)
 * @method $this fuzzyMaxExpansions(int $value)
 * @method $this fuzzyPrefixLength(int $value)
 * @method $this fuzzyTranspositions(bool $value)
 * @method $this lenient(bool $value)
 * @method $this minimumShouldMatch(string $value)
 * @method $this quoteFieldSuffix(string $value)
 */
class SimpleQueryStringOptions extends QueryOptions
{
    public function allowedOptions(): array
    {
        return [
            'flags',
            'default_operator',
            'analyze_wildcard',
            'analyzer',
            'auto_generate_synonyms_phrase_query',
            'boost',
            'fuzzy_max_expansions',
            'fuzzy_prefix_length',
            'fuzzy_transpositions',
            'lenient',
            'minimum_should_match',
            'quote_field_suffix',
        ];
    }
}
