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
 */
class WhereOptions extends QueryOptions
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
        ];
    }

    public function asFuzzy($option = 'AUTO'): self
    {
        return $this->fuzziness($option);
    }
}
