<?php

namespace PDPhilip\Elasticsearch\Query\Options;

/**
 * @method $this analyzer(string $analyzer) MatchQuery
 * @method $this autoGenerateSynonymsPhraseQuery(bool $value) MatchQuery
 * @method $this boost(float|int $value) MatchQuery
 * @method $this fuzziness(mixed $value) MatchQuery
 * @method $this maxExpansions(int $value) MatchQuery
 * @method $this prefixLength(int $value) MatchQuery
 * @method $this fuzzyTranspositions(int $value) MatchQuery
 * @method $this fuzzyRewrite(int $value) MatchQuery
 * @method $this lenient(int $value) MatchQuery
 * @method $this operator(int $value) MatchQuery
 * @method $this zeroTermsQuery(string $value) MatchQuery
 * @method $this relation(string $value) RangeQuery | $value must be one of the following: INTERSECTS (default), CONTAINS, WITHIN
 * @method $this slop(int $value)
 * @method $this type(string $value) options: "best_fields", "most_fields", "cross_fields", "phrase", "phrase_prefix", "bool_prefix"
 * @method $this tieBreaker(float|int $value) Only for best_fields, most_fields, phrase, and phrase_prefix.
 * @method $this minimumShouldMatch(string|int $value) Only for best_fields, most_fields, phrase.
 * @method $this constantScore(bool $value) Only for best_fields, most_fields, phrase.
 * @method $this transpositions(bool $value)
 * @method $this rewrite(string $value)
 * @method $this caseInsensitive(bool $value)
 * @method $this format(string $value)
 * @method $this timeZone(string $value)
 * @method $this scoreMode(string $value)
 * @method $this ignoreUnmapped(bool $value)
 * @method $this innerHits(bool $value)
 * @method $this flags(string $value)
 * @method $this maxDeterminizedStates(int $value)
 * @method $this minimumShouldMatchField(bool $value)
 * @method $this minimumShouldMatchScript(bool $value)
 */
class ClauseOptions extends QueryOptions
{
    public function allowedOptions(): array
    {
        return [
            'analyzer',
            'auto_generate_synonyms_phrase_query',
            'boost',
            'fuzziness',
            'max_expansions',
            'prefix_length',
            'fuzzy_transpositions',
            'fuzzy_rewrite',
            'lenient',
            'operator',
            'zero_terms_query',
            'relation',
            'slop',
            'type',
            'tie_breaker',
            'minimum_should_match',
            'constant_score',
            'transpositions',
            'rewrite',
            'case_insensitive',
            'format',
            'time_zone',
            'score_mode',
            'ignore_unmapped',
            'inner_hits',
            'flags',
            'max_determinized_states',
            'minimum_should_match_field',
            'minimum_should_match_script',
        ];
    }
}
