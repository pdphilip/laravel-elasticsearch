<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Query\Concerns;

use Exception;
use Illuminate\Support\Arr;
use PDPhilip\Elasticsearch\Query\Builder;

/**
 * Multi-field search queries for Elasticsearch (multi_match + query_string).
 *
 * All convenience methods delegate to search() with different multi_match types:
 *   searchTerm*          — best_fields   (highest score from best-matching field)
 *   searchTermMost*      — most_fields   (combined score from all matching fields)
 *   searchTermCross*     — cross_fields  (treats fields as one combined field)
 *   searchPhrase*        — phrase        (exact phrase match)
 *   searchPhrasePrefix*  — phrase_prefix (phrase + prefix on last term)
 *   searchBoolPrefix*    — bool_prefix   (search-as-you-type)
 *   searchFuzzy*         — best_fields + fuzziness:AUTO
 *   searchFuzzyPrefix*   — bool_prefix  + fuzziness:AUTO
 *   searchQueryString*   — query_string  (Lucene query syntax)
 *
 * Each type has four variants: search{Type}, orSearch{Type}, searchNot{Type}, orSearchNot{Type}
 *
 * @property array $wheres
 *
 * @mixin Builder
 */
trait BuildsSearchQueries
{
    // ----------------------------------------------------------------------
    // Core Search Method
    // ----------------------------------------------------------------------

    /**
     * Add a text search clause to the query.
     *
     * @param  array|\Closure  $options
     */
    public function search(string $query, string $type = 'best_fields', mixed $columns = null, mixed $options = [], bool $not = false, string $boolean = 'and', array $appendedOptions = []): self
    {
        [$columns, $options] = $this->extractSearch($columns, $options);
        $options = $this->setOptions($options, 'search');
        $options->asType($type);
        if ($columns) {
            $options->fields(Arr::wrap($columns));
            $options->formatFields();
        }
        $options = $options->toArray();
        if ($appendedOptions) {
            // prioritize given options
            $options = array_merge($appendedOptions, $options);
        }

        $this->wheres[] = [
            'type' => 'Search',
            'value' => $query,
            'boolean' => $boolean,
            'not' => $not,
            'options' => $options,
        ];

        return $this;
    }

    // ----------------------------------------------------------------------
    // Multi Match: best_fields
    // https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-multi-match-query.html#type-best-fields
    // ----------------------------------------------------------------------

    public function searchTerm($query, mixed $columns = null, $options = [])
    {
        return $this->search($query, 'best_fields', $columns, $options);
    }

    public function orSearchTerm($query, mixed $columns = null, $options = [])
    {
        return $this->search($query, 'best_fields', $columns, $options, false, 'or');
    }

    public function searchNotTerm($query, mixed $columns = null, $options = [])
    {
        return $this->search($query, 'best_fields', $columns, $options, true);
    }

    public function orSearchNotTerm($query, mixed $columns = null, $options = [])
    {
        return $this->search($query, 'best_fields', $columns, $options, true, 'or');
    }

    // ----------------------------------------------------------------------
    // Multi Match: most_fields
    // https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-multi-match-query.html#type-most-fields
    // ----------------------------------------------------------------------

    public function searchTermMost($query, mixed $columns = null, $options = [])
    {
        return $this->search($query, 'most_fields', $columns, $options);
    }

    public function orSearchTermMost($query, mixed $columns = null, $options = [])
    {
        return $this->search($query, 'most_fields', $columns, $options, false, 'or');
    }

    public function searchNotTermMost($query, mixed $columns = null, $options = [])
    {
        return $this->search($query, 'most_fields', $columns, $options, true);
    }

    public function orSearchNotTermMost($query, mixed $columns = null, $options = [])
    {
        return $this->search($query, 'most_fields', $columns, $options, true, 'or');
    }

    // ----------------------------------------------------------------------
    // Multi Match: cross_fields
    // https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-multi-match-query.html#type-cross-fields
    // ----------------------------------------------------------------------

    public function searchTermCross($query, mixed $columns = null, $options = [])
    {
        return $this->search($query, 'cross_fields', $columns, $options);
    }

    public function orSearchTermCross($query, mixed $columns = null, $options = [])
    {
        return $this->search($query, 'cross_fields', $columns, $options, false, 'or');
    }

    public function searchNotTermCross($query, mixed $columns = null, $options = [])
    {
        return $this->search($query, 'cross_fields', $columns, $options, true);
    }

    public function orSearchNotTermCross($query, mixed $columns = null, $options = [])
    {
        return $this->search($query, 'cross_fields', $columns, $options, true, 'or');
    }

    // ----------------------------------------------------------------------
    // Multi Match: phrase
    // https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-multi-match-query.html#type-phrase
    // ----------------------------------------------------------------------

    public function searchPhrase($phrase, mixed $columns = null, $options = [])
    {
        return $this->search($phrase, 'phrase', $columns, $options);
    }

    public function orSearchPhrase($phrase, mixed $columns = null, $options = [])
    {
        return $this->search($phrase, 'phrase', $columns, $options, false, 'or');
    }

    public function searchNotPhrase($phrase, mixed $columns = null, $options = [])
    {
        return $this->search($phrase, 'phrase', $columns, $options, true);
    }

    public function orSearchNotPhrase($phrase, mixed $columns = null, $options = [])
    {
        return $this->search($phrase, 'phrase', $columns, $options, true, 'or');
    }

    // ----------------------------------------------------------------------
    // Multi Match: phrase_prefix
    // https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-multi-match-query.html#type-phrase
    // ----------------------------------------------------------------------

    public function searchPhrasePrefix($phrase, mixed $columns = null, $options = [])
    {
        return $this->search($phrase, 'phrase_prefix', $columns, $options);
    }

    public function orSearchPhrasePrefix($terms, mixed $columns = null, $options = [])
    {
        return $this->search($terms, 'phrase_prefix', $columns, $options, false, 'or');
    }

    public function searchNotPhrasePrefix($phrase, mixed $columns = null, $options = [])
    {
        return $this->search($phrase, 'phrase_prefix', $columns, $options, true);
    }

    public function orSearchNotPhrasePrefix($phrase, mixed $columns = null, $options = [])
    {
        return $this->search($phrase, 'phrase_prefix', $columns, $options, true, 'or');
    }

    // ----------------------------------------------------------------------
    // Multi Match: bool_prefix
    // https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-multi-match-query.html#type-bool-prefix
    // ----------------------------------------------------------------------

    public function searchBoolPrefix($query, mixed $columns = null, $options = [])
    {
        return $this->search($query, 'bool_prefix', $columns, $options);
    }

    public function orSearchBoolPrefix($query, mixed $columns = null, $options = [])
    {
        return $this->search($query, 'bool_prefix', $columns, $options, false, 'or');
    }

    public function searchNotBoolPrefix($query, mixed $columns = null, $options = [])
    {
        return $this->search($query, 'bool_prefix', $columns, $options, true);
    }

    public function orSearchNotBoolPrefix($query, mixed $columns = null, $options = [])
    {
        return $this->search($query, 'bool_prefix', $columns, $options, true, 'or');
    }

    // ----------------------------------------------------------------------
    // Fuzzy Search Convenience Methods
    // ----------------------------------------------------------------------

    public function searchFuzzy($query, mixed $columns = null, $options = [])
    {
        return $this->search($query, 'best_fields', $columns, $options, false, 'and', ['fuzziness' => 'AUTO']);
    }

    public function orSearchFuzzy($query, mixed $columns = null, $options = [])
    {
        return $this->search($query, 'best_fields', $columns, $options, false, 'or', ['fuzziness' => 'AUTO']);
    }

    public function searchNotFuzzy($query, mixed $columns = null, $options = [])
    {
        return $this->search($query, 'best_fields', $columns, $options, true, 'and', ['fuzziness' => 'AUTO']);
    }

    public function orSearchNotFuzzy($query, mixed $columns = null, $options = [])
    {

        return $this->search($query, 'best_fields', $columns, $options, true, 'or', ['fuzziness' => 'AUTO']);
    }

    public function searchFuzzyPrefix($query, mixed $columns = null, $options = [])
    {
        return $this->search($query, 'bool_prefix', $columns, $options, false, 'and', ['fuzziness' => 'AUTO']);
    }

    public function orSearchFuzzyPrefix($query, mixed $columns = null, $options = [])
    {
        return $this->search($query, 'bool_prefix', $columns, $options, false, 'or', ['fuzziness' => 'AUTO']);
    }

    public function searchNotFuzzyPrefix($query, mixed $columns = null, $options = [])
    {
        return $this->search($query, 'bool_prefix', $columns, $options, true, 'and', ['fuzziness' => 'AUTO']);
    }

    public function orSearchNotFuzzyPrefix($query, mixed $columns = null, $options = [])
    {
        return $this->search($query, 'bool_prefix', $columns, $options, true, 'or', ['fuzziness' => 'AUTO']);
    }

    // ----------------------------------------------------------------------
    // Query String Search
    // ----------------------------------------------------------------------

    /**
     *   Add a 'query_string' statement to query
     *
     * @throws Exception
     */
    public function searchQueryString(mixed $query, mixed $columns = null, $options = []): self
    {
        return $this->buildQueryStringWheres($columns, $query, 'and', false, $options);
    }

    /**
     * @throws Exception
     */
    public function orSearchQueryString(mixed $query, mixed $columns = null, $options = []): self
    {
        return $this->buildQueryStringWheres($columns, $query, 'or', false, $options);
    }

    /**
     * @throws Exception
     */
    public function searchNotQueryString(mixed $query, mixed $columns = null, $options = []): self
    {
        return $this->buildQueryStringWheres($columns, $query, 'and', true, $options);
    }

    /**
     * @throws Exception
     */
    public function orSearchNotQueryString(mixed $query, mixed $columns = null, $options = []): self
    {
        return $this->buildQueryStringWheres($columns, $query, 'or', true, $options);
    }

    /**
     * @throws Exception
     */
    protected function buildQueryStringWheres($columns, $value, $boolean, $not, $options): self
    {
        $type = 'QueryString';
        [$columns, $options] = $this->extractSearch($columns, $options, 'querystring');
        $options = $this->setOptions($options, 'querystring')->toArray();
        $this->wheres[] = compact('columns', 'value', 'type', 'boolean', 'not', 'options');

        return $this;
    }
}
