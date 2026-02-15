<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Query\Concerns;

/**
 * ES-specific field queries: term, match, phrase, fuzzy, regex, prefix.
 *
 * Each query type provides four variants:
 *   where{Type}()         — AND match
 *   orWhere{Type}()       — OR match
 *   whereNot{Type}()      — AND NOT match
 *   orWhereNot{Type}()    — OR NOT match
 *
 * Aliases:
 *   whereExact*  → whereTerm*
 *   wherePrefix* → whereStartsWith*
 */
trait BuildsFieldQueries
{
    /**
     * Core dispatcher for all field query types.
     * Handles option extraction, negation, and where clause registration.
     */
    private function addFieldQuery(string $type, $column, $value, $boolean = 'and', $not = false, $options = []): self
    {
        [$column, $value, $not, $boolean, $options] = $this->extractOptionsWithNot($type, $column, $value, $boolean, $not, $options);
        $this->wheres[] = compact('column', 'value', 'type', 'boolean', 'not', 'options');

        return $this;
    }

    // ----------------------------------------------------------------------
    // Term Query - exact value matching
    // https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-term-query.html
    // ----------------------------------------------------------------------

    public function whereTerm($column, $value, $boolean = 'and', $not = false, $options = []): self
    {
        return $this->addFieldQuery('Term', $column, $value, $boolean, $not, $options);
    }

    public function orWhereTerm(string $column, $value, $options = []): self
    {
        return $this->whereTerm($column, $value, 'or', false, $options);
    }

    public function whereNotTerm(string $column, $value, $options = []): self
    {
        return $this->whereTerm($column, $value, 'and', true, $options);
    }

    public function orWhereNotTerm(string $column, $value, $options = []): self
    {
        return $this->whereTerm($column, $value, 'or', true, $options);
    }

    /** @see whereTerm() */
    public function whereExact($column, $value, $boolean = 'and', $not = false, $options = []): self
    {
        return $this->whereTerm($column, $value, $boolean, $not, $options);
    }

    /** @see orWhereTerm() */
    public function orWhereExact($column, $value, $options = []): self
    {
        return $this->orWhereTerm($column, $value, $options);
    }

    /** @see whereNotTerm() */
    public function whereNotExact($column, $value, $options = []): self
    {
        return $this->whereNotTerm($column, $value, $options);
    }

    /** @see orWhereNotTerm() */
    public function orWhereNotExact($column, $value, $options = []): self
    {
        return $this->orWhereNotTerm($column, $value, $options);
    }

    // ----------------------------------------------------------------------
    // Term Exists Query - check if a field has an indexed value
    // https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-exists-query.html
    // ----------------------------------------------------------------------

    public function whereTermExists($column, $boolean = 'and', $not = false): self
    {
        $this->wheres[] = [
            'type' => 'Basic',
            'operator' => 'exists',
            'column' => $column,
            'value' => $not ? null : ' ',
            'boolean' => $boolean,
            'options' => [],
        ];

        return $this;
    }

    public function whereNotTermExists($column)
    {
        return $this->whereTermExists($column, 'and', true);
    }

    public function orWhereTermExists($column)
    {
        return $this->whereTermExists($column, 'or', false);
    }

    public function orWhereNotTermsExists($column)
    {
        return $this->whereTermExists($column, 'or', true);
    }

    // ----------------------------------------------------------------------
    // Match Query - full-text search with analysis
    // https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-match-query.html
    // ----------------------------------------------------------------------

    public function whereMatch($column, $value, $boolean = 'and', $not = false, $options = []): self
    {
        return $this->addFieldQuery('Match', $column, $value, $boolean, $not, $options);
    }

    public function orWhereMatch(string $column, $value, $options = []): self
    {
        return $this->whereMatch($column, $value, 'or', false, $options);
    }

    public function whereNotMatch(string $column, $value, $options = []): self
    {
        return $this->whereMatch($column, $value, 'and', true, $options);
    }

    public function orWhereNotMatch(string $column, $value, $options = []): self
    {
        return $this->whereMatch($column, $value, 'or', true, $options);
    }

    // ----------------------------------------------------------------------
    // Phrase Query - exact phrase matching
    // https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-match-query-phrase.html
    // ----------------------------------------------------------------------

    public function wherePhrase($column, $value, $boolean = 'and', $not = false, $options = [])
    {
        return $this->addFieldQuery('Phrase', $column, $value, $boolean, $not, $options);
    }

    public function orWherePhrase($column, $value, $options = [])
    {
        return $this->wherePhrase($column, $value, 'or', false, $options);
    }

    public function whereNotPhrase($column, $value, $options = [])
    {
        return $this->wherePhrase($column, $value, 'and', true, $options);
    }

    public function orWhereNotPhrase($column, $value, $options = [])
    {
        return $this->wherePhrase($column, $value, 'or', true, $options);
    }

    // ----------------------------------------------------------------------
    // Phrase Prefix Query - autocomplete style matching
    // https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-match-query-phrase-prefix.html
    // ----------------------------------------------------------------------

    public function wherePhrasePrefix($column, $value, $boolean = 'and', $not = false, $options = [])
    {
        return $this->addFieldQuery('PhrasePrefix', $column, $value, $boolean, $not, $options);
    }

    public function orWherePhrasePrefix($column, $value, $options = [])
    {
        return $this->wherePhrasePrefix($column, $value, 'or', false, $options);
    }

    public function whereNotPhrasePrefix($column, $value, $options = [])
    {
        return $this->wherePhrasePrefix($column, $value, 'and', true, $options);
    }

    public function orWhereNotPhrasePrefix($column, $value, $options = [])
    {
        return $this->wherePhrasePrefix($column, $value, 'or', true, $options);
    }

    // ----------------------------------------------------------------------
    // Fuzzy Query - typo tolerant matching
    // https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-fuzzy-query.html
    // ----------------------------------------------------------------------

    public function whereFuzzy($column, $value, $boolean = 'and', $not = false, $options = []): self
    {
        return $this->addFieldQuery('Fuzzy', $column, $value, $boolean, $not, $options);
    }

    public function orWhereFuzzy(string $column, $value, array $options = []): self
    {
        return $this->whereFuzzy($column, $value, 'or', false, $options);
    }

    public function whereNotFuzzy(string $column, $value, array $options = []): self
    {
        return $this->whereFuzzy($column, $value, 'and', true, $options);
    }

    public function orWhereNotFuzzy(string $column, $value, array $options = []): self
    {
        return $this->whereFuzzy($column, $value, 'or', true, $options);
    }

    // ----------------------------------------------------------------------
    // Prefix Query - starts with matching
    // https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-prefix-query.html
    // ----------------------------------------------------------------------

    public function whereStartsWith($column, string $value, $boolean = 'and', $not = false, $options = []): self
    {
        return $this->addFieldQuery('Prefix', $column, $value, $boolean, $not, $options);
    }

    public function orWhereStartsWith($column, string $value, $options = []): self
    {
        return $this->whereStartsWith($column, $value, 'or', false, $options);
    }

    public function whereNotStartsWith($column, string $value, $options = [])
    {
        return $this->whereStartsWith($column, $value, 'and', true, $options);
    }

    public function orWhereNotStartsWith($column, string $value, $options = [])
    {
        return $this->whereStartsWith($column, $value, 'or', true, $options);
    }

    /** @see whereStartsWith() */
    public function wherePrefix($column, string $value, $boolean = 'and', $not = false, $options = []): self
    {
        return $this->whereStartsWith($column, $value, $boolean, $not, $options);
    }

    /** @see orWhereStartsWith() */
    public function orWherePrefix($column, string $value, $options = []): self
    {
        return $this->orWhereStartsWith($column, $value, $options);
    }

    /** @see whereNotStartsWith() */
    public function whereNotPrefix($column, string $value, $options = []): self
    {
        return $this->whereNotStartsWith($column, $value, $options);
    }

    /** @see orWhereNotStartsWith() */
    public function orWhereNotPrefix($column, string $value, $options = []): self
    {
        return $this->orWhereNotStartsWith($column, $value, $options);
    }

    // ----------------------------------------------------------------------
    // Regex Query - pattern matching
    // https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-regexp-query.html
    // ----------------------------------------------------------------------

    public function whereRegex($column, string $value, $boolean = 'and', bool $not = false, array $options = []): self
    {
        return $this->addFieldQuery('Regex', $column, $value, $boolean, $not, $options);
    }

    public function orWhereRegex($column, string $value, array $options = []): self
    {
        return $this->whereRegex($column, $value, 'or', false, $options);
    }

    public function whereNotRegex($column, string $value, array $options = []): self
    {
        return $this->whereRegex($column, $value, 'and', true, $options);
    }

    public function orWhereNotRegex($column, string $value, array $options = []): self
    {
        return $this->whereRegex($column, $value, 'or', true, $options);
    }
}
