<?php

namespace PDPhilip\Elasticsearch\Query\Options;

/**
 * QueryStringOptions for Query String Queries.
 *
 *
 * @method $this type(string $value) //Options: best_fields, most_fields, cross_fields, phrase, phrase_prefix, bool_prefix
 * @method $this allowLeadingWildcard(bool $value)
 * @method $this analyzeWildcard(bool $value)
 * @method $this analyzer(string $analyzer)
 * @method $this autoGenerateSynonymsPhraseQuery(bool $value)
 * @method $this boost(float $value)
 * @method $this defaultOperator(string $value) OR|AND
 * @method $this fuzziness(string|int $value)
 * @method $this fuzzyMaxExpansions(int $value)
 * @method $this fuzzyPrefixLength(int $value)
 * @method $this fuzzyTranspositions(bool $value)
 * @method $this lenient(bool $value)
 * @method $this maxDeterminizedStates(int $value)
 * @method $this minimumShouldMatch(string $value)
 * @method $this quoteAnalyzer(string $value)
 * @method $this phraseSlop(int $value)
 * @method $this quoteFieldSuffix(string $value)
 * @method $this rewrite(string $value)
 * @method $this timeZone(string $value)
 */
class QueryStringOptions extends QueryOptions
{
    public function allowedOptions(): array
    {
        return [
            'type',
            'allow_leading_wildcard',
            'analyze_wildcard',
            'analyzer',
            'auto_generate_synonyms_phrase_query',
            'boost',
            'default_operator',
            'fuzziness',
            'fuzzy_max_expansions',
            'fuzzy_prefix_length',
            'fuzzy_transpositions',
            'fuzzy_rewrite',
            'fuzzy_transpositions',
            'lenient',
            'max_determinized_states',
            'minimum_should_match',
            'quote_analyzer',
            'phrase_slop',
            'quote_field_suffix',
            'rewrite',
            'time_zone',
        ];
    }
}
