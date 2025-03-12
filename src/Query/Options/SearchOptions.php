<?php

namespace PDPhilip\Elasticsearch\Query\Options;

use InvalidArgumentException;

/**
 * SearchOptions for Multi-Match Queries.
 *
 * Supports: best_fields, most_fields, cross_fields, phrase, phrase_prefix, bool_prefix
 *
 * @method $this type(string $value) options: "best_fields", "most_fields", "cross_fields", "phrase", "phrase_prefix", "bool_prefix"
 * @method $this tieBreaker(float|int $value) Only for best_fields, most_fields, phrase, and phrase_prefix.
 * @method $this analyzer(string $analyzer) Not allowed in cross_fields.
 * @method $this boost(float|int $value)
 * @method $this operator(string $value) ("AND"|"OR") Only for best_fields, most_fields, phrase.
 * @method $this minimumShouldMatch(string|int $value) Only for best_fields, most_fields, phrase.
 * @method $this autoGenerateSynonymsPhraseQuery(bool $value)
 * @method $this fuzziness(string|int $value) Not allowed in phrase or phrase_prefix.
 * @method $this maxExpansions(int $value) Only for phrase_prefix.
 * @method $this prefixLength(int $value) Only for phrase_prefix.
 * @method $this fuzzyTranspositions(bool $value) Not allowed in phrase or phrase_prefix.
 * @method $this fuzzyRewrite(string $value) Not allowed in phrase or phrase_prefix.
 * @method $this lenient(bool $value) Not allowed in phrase_prefix.
 * @method $this zeroTermsQuery(string $value) Only for best_fields, most_fields, phrase.
 * @method $this constantScore(bool $value) Only for best_fields, most_fields, phrase.
 */
class SearchOptions extends QueryOptions
{
    public function allowedOptions(): array
    {
        return [
            'type',
            'tie_breaker',
            'analyzer',
            'boost',
            'operator',
            'minimum_should_match',
            'fuzziness',
            'lenient',
            'prefix_length',
            'max_expansions',
            'fuzzy_rewrite',
            'fuzzy_transpositions',
            'zero_terms_query',
            'auto_generate_synonyms_phrase_query',
            'fields',
            'constant_score',
        ];
    }

    /**
     * Set the fuzziness level for fuzzy search.
     *
     * @param  int|string  $option  AUTO, 1, 2, etc.
     * @return $this
     *
     * @throws InvalidArgumentException if used in phrase or phrase_prefix queries.
     */
    public function searchFuzzy(int|string $option = 'AUTO'): self
    {
        return $this->fuzziness($option);
    }

    /**
     * Set the type of the multi-match query.
     *
     * @param  "best_fields"|"most_fields"|"cross_fields"|"bool_prefix"|"phrase"|"phrase_prefix"  $type
     * @return $this
     */
    public function asType(string $type): self
    {
        return $this->type($type);
    }

    public function formatFields()
    {
        if ($this->fields) {
            $fields = $this->fields;
            if (is_array($fields) && array_keys($fields) !== range(0, count($fields) - 1)) {
                $fields = array_map(fn ($field, $boost) => "{$field}^{$boost}", array_keys($fields), $fields);
            }
            $this->fields = $fields;
        }
    }
}
