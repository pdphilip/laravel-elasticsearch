<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Query\Grammar\Concerns;

use Illuminate\Support\Str;
use InvalidArgumentException;
use PDPhilip\Elasticsearch\Exceptions\BuilderException;
use PDPhilip\Elasticsearch\Query\Builder;
use PDPhilip\Elasticsearch\Query\DSL\DslFactory;
use PDPhilip\Elasticsearch\Query\DSL\QueryCompiler;
use PDPhilip\Elasticsearch\Utils\Helpers;

/**
 * Where clause compilation
 */
trait CompilesWheres
{
    /**
     * Compile all where clauses into query/filter/postFilter buckets.
     */
    public function compileWheres($query): array
    {
        $compiled['query'] = $this->compileQuery($query, $query->wheres);
        $compiled['filter'] = $this->compileQuery($query, $query->filters ?? []);
        $compiled['postFilter'] = $this->compileQuery($query, $query->postFilters ?? []);

        return $compiled;
    }

    /**
     * Compile an array of wheres into ES query DSL.
     * This is where the magic happens - routes each where to its compiler
     * and wraps everything in bool query logic.
     */
    protected function compileQuery(Builder $builder, array $wheres = []): array
    {
        if (! $wheres) {
            return [];
        }

        $dslCompiler = new QueryCompiler;

        foreach ($wheres as $where) {
            $isOr = str_starts_with($where['boolean'], 'or');
            $isNot = str_ends_with($where['boolean'], 'not');

            if (! empty($where['not'])) {
                $isNot = true;
            }

            // Normalize operators
            if (isset($where['operator'])) {
                $where['operator'] = strtolower($where['operator']);
                [$operator, $isNegation] = $this->parseNegationOperator($where['operator'], $this->getValueForWhere($builder, $where));
                $where['operator'] = $operator;
                if ($isNegation) {
                    $isNot = ! $isNot;
                }
            }

            // Normalize column names
            if (isset($where['column'])) {
                $where['column'] = (string) $where['column'];

                if ($where['column'] === 'id') {
                    $where['column'] = '_id';
                }

                // Strip table prefix if present
                if (Str::startsWith($where['column'], $builder->from.'.')) {
                    $where['column'] = Str::replaceFirst($builder->from.'.', '', $where['column']);
                }

                $where['column'] = $this->prependParentField($where['column'], $builder);
            }

            // Route to the appropriate compiler
            $method = 'compileWhere'.$where['type'];
            if (! method_exists($this, $method)) {
                throw new BuilderException("Unsupported where type: {$where['type']}");
            }
            $result = $this->{$method}($builder, $where);
            $dslCompiler->setResult($result, $isOr, $isNot);
        }

        return $dslCompiler->compileQuery();
    }

    // ----------------------------------------------------------------------
    // Where Type Compilers
    // Each handles a specific where type (Basic, In, Between, etc.)
    // ----------------------------------------------------------------------

    public function compileWhereMatchAll(): array
    {
        return DslFactory::matchAll();
    }

    /**
     * Basic where
     *
     * @throws BuilderException
     */
    protected function compileWhereBasic(Builder $builder, array $where): array
    {
        $value = $this->getValueForWhere($builder, $where);
        $field = $where['column'];
        $options = $where['options'] ?? [];

        $operatorsMap = [
            '>' => 'gt',
            '>=' => 'gte',
            '<' => 'lt',
            '<=' => 'lte',
        ];

        if (is_null($value) || $where['operator'] == 'exists') {
            $query = DslFactory::exists($field, $options);
        } elseif (in_array($where['operator'], ['like', 'not like'])) {
            $field = $this->getIndexableField($field, $builder);
            $wildcardValue = str_replace('%', '*', $value);
            if (! Str::contains($wildcardValue, '*')) {
                $wildcardValue = '*'.$wildcardValue.'*';
            }
            $query = DslFactory::wildcard($field, $wildcardValue, $options);
        } elseif (in_array($where['operator'], array_keys($operatorsMap))) {
            $operator = $operatorsMap[$where['operator']];
            $field = $this->getIndexableField($field, $builder);
            $query = DslFactory::range($field, [$operator => $value], $options);
        } else {
            $field = $this->getIndexableField($field, $builder);
            $query = DslFactory::term($field, $value, $options);
        }

        return $this->applyOptionsToClause($query, $where);
    }

    /**
     * Match query
     */
    protected function compileWhereMatch(Builder $builder, array $where): array
    {
        $field = $where['column'];
        $values = $this->getValueForWhere($builder, $where);
        $options = $where['options'] ?? [];
        $options['operator'] = $options['operator'] ?? 'and';
        $query = DslFactory::match($field, $values, $options);

        return $this->applyOptionsToClause($query, $where);
    }

    /**
     * whereIn - terms query
     *
     * @throws BuilderException
     */
    protected function compileWhereIn(Builder $builder, array $where, $not = false): array
    {
        $field = $this->getIndexableField($where['column'], $builder);
        $values = $this->getValueForWhere($builder, $where);
        $options = $where['options'] ?? [];
        $query = DslFactory::terms($field, $values, $options);

        return $this->applyOptionsToClause($query, $where);
    }

    /**
     * whereExists - exists query
     *
     * @throws BuilderException
     */
    protected function compileWhereExists(Builder $builder, array $where): array
    {
        $where['operator'] = '=';

        return $this->compileWhereBasic($builder, $where);
    }

    /**
     * whereDate - date comparison
     *
     * @throws BuilderException
     */
    protected function compileWhereDate(Builder $builder, array $where): array
    {
        if ($where['operator'] == '=') {
            $value = $this->getValueForWhere($builder, $where);
            $where['value'] = [$value, $value];

            return $this->compileWhereBetween($builder, $where);
        }

        return $this->compileWhereBasic($builder, $where);
    }

    /**
     * whereBetween - range query with gte and lte
     */
    protected function compileWhereBetween(Builder $builder, array $where): array
    {
        $column = $where['column'];
        $values = $this->getValueForWhere($builder, $where);
        $options = $where['options'] ?? [];

        $conditions = [
            'gte' => $values[0],
            'lte' => $values[1],
        ];

        return DslFactory::range($column, $conditions, $options);
    }

    /**
     * Function score - wrap query with custom scoring
     */
    protected function compileWhereFunctionScore(Builder $builder, array $where): array
    {
        $compiled = $this->compileWheres($where['query']);
        foreach ($compiled as $queryPart => $clauses) {
            $compiled[$queryPart] = array_map(function ($clause) use ($where) {
                if ($clause) {
                    $this->applyOptionsToClause($clause, $where);
                }

                return $clause;
            }, $clauses);
        }

        $compiled = array_filter($compiled);
        $functionType = $where['functionType'];
        $functionOptions = $where['options'];

        return DslFactory::functionScore($compiled['query'], $functionType, $functionOptions);
    }

    /**
     * Geo distance - find docs within radius of a point
     */
    protected function compileWhereGeoDistance($builder, $where): array
    {
        $options = $where['options'] ?? [];

        return DslFactory::geoDistance($where['column'], $where['distance'], $where['location'], $options);
    }

    /**
     * Geo bounding box - find docs within a rectangle
     */
    protected function compileWhereGeoBox(Builder $builder, array $where): array
    {
        $options = $where['options'] ?? [];

        return DslFactory::geoBoundingBox($where['column'], $where['bounds'], $options);
    }

    /**
     * Nested where - subquery grouped with parentheses
     */
    protected function compileWhereNested(Builder $builder, array $where): array
    {
        $compiled = $this->compileWheres($where['query']);

        foreach ($compiled as $queryPart => $clauses) {
            $compiled[$queryPart] = array_map(function ($clause) use ($where) {
                if ($clause) {
                    $this->applyOptionsToClause($clause, $where);
                }

                return $clause;
            }, $clauses);
        }

        $compiled = array_filter($compiled);

        return reset($compiled);
    }

    /**
     * Nested object query - query nested document type
     */
    protected function compileWhereNestedObject(Builder $builder, $where): array
    {
        $wheres = $this->compileWheres($where['query']);
        $path = $where['column'];
        if (! $wheres['query']) {
            $wheres['query'] = DslFactory::exists($path);
        }

        $options = $where['options'] ?? [];
        $wheres = array_filter($wheres);

        $query = DslFactory::nested($path, $wheres, $options);

        return $this->applyOptionsToClause($query, $where);
    }

    /**
     * Inner nested - nested query with inner_hits
     */
    protected function compileWhereInnerNested(Builder $builder, $where): array
    {
        $innerQuery = $where['query'];
        $query = $this->compileWheres($innerQuery);
        $innerHits = $this->_buildInnerHits($innerQuery);

        return DslFactory::innerNested($where['column'], $query['query'], $innerHits);
    }

    /**
     * Parent query - has_parent
     */
    protected function compileWhereParent(Builder $builder, array $where): array
    {
        return $this->applyWhereRelationship($builder, $where, 'parent');
    }

    /**
     * Parent ID query
     */
    protected function compileWhereParentId(Builder $builder, array $where)
    {
        $type = $where['relationType'];
        $id = $where['id'];
        $options = $where['options'] ?? [];

        return DslFactory::parentId($type, $id, $options);
    }

    /**
     * Prefix query
     *
     * @throws BuilderException
     */
    protected function compileWherePrefix(Builder $builder, array $where): array
    {
        $field = $this->getIndexableField($where['column'], $builder);
        $value = $where['value'];
        $options = $where['options'] ?? [];

        return DslFactory::prefix($field, $value, $options);
    }

    /**
     * Raw query - pass through as-is
     */
    protected function compileWhereRaw(Builder $builder, array $where): array
    {
        return $where['sql'];
    }

    /**
     * Regex query
     *
     * @throws BuilderException
     */
    protected function compileWhereRegex(Builder $builder, array $where): array
    {
        $allowedArgs = [
            'flags',
            'case_insensitive',
            'max_determinized_states',
            'rewrite',
        ];
        $options = $this->getAllowedOptions($where['options'] ?? [], $allowedArgs);
        $field = $this->getIndexableField($where['column'], $builder);
        $value = Helpers::escape($where['value']);

        return DslFactory::regexp($field, $value, $options);
    }

    /**
     * Phrase match - match exact phrase
     */
    protected function compileWherePhrase(Builder $builder, array $where): array
    {
        $field = $where['column'];
        $value = $where['value'];
        $options = $where['options'] ?? [];

        return DslFactory::matchPhrase($field, $value, $options);
    }

    /**
     * Phrase prefix - autocomplete style matching
     */
    protected function compileWherePhrasePrefix(Builder $builder, array $where): array
    {
        $field = $where['column'];
        $value = $where['value'];
        $options = $where['options'] ?? [];

        return DslFactory::matchPhrasePrefix($field, $value, $options);
    }

    /**
     * Fuzzy query - typo tolerant matching
     */
    protected function compileWhereFuzzy(Builder $builder, array $where): array
    {
        $field = $where['column'];
        $value = $where['value'];
        $options = $where['options'] ?? [];

        return DslFactory::fuzzy($field, $value, $options);
    }

    /**
     * Term query - exact match
     *
     * @throws BuilderException
     */
    protected function compileWhereTerm(Builder $builder, array $where): array
    {
        $field = $this->getIndexableField($where['column'], $builder);
        $value = $where['value'];
        $options = $where['options'] ?? [];

        return DslFactory::term($field, $value, $options);
    }

    /**
     * Script query - custom Painless script
     */
    protected function compileWhereScript(Builder $builder, array $where): array
    {
        $source = $where['script'];
        $options = $where['options'] ?? [];

        return DslFactory::script($source, $options);
    }

    /**
     * Multi-match search across fields
     */
    protected function compileWhereSearch(Builder $builder, array $where): array
    {
        $value = $where['value'];
        $options = $where['options'] ?? [];

        $constantScore = false;
        if (isset($options['constant_score'])) {
            $constantScore = $options['constant_score'];
            unset($options['constant_score']);
        }
        $query = DslFactory::multiMatch($value, $options);

        if ($constantScore) {
            return DslFactory::constantScore($query);
        }

        return $query;
    }

    /**
     * Query string - Lucene syntax search
     */
    private function compileWhereQueryString(Builder $builder, array $where)
    {
        $fields = $where['columns'];
        $query = $where['value'];
        $options = $where['options'] ?? [];

        return DslFactory::queryString($query, $fields, $options);
    }

    /**
     * Child query - has_child
     */
    protected function compileWhereChild(Builder $builder, array $where): array
    {
        return $this->applyWhereRelationship($builder, $where, 'child');
    }

    /**
     * Compile parent/child relationship query
     */
    protected function applyWhereRelationship(Builder $builder, array $where, string $relationship): array
    {
        $compiled = $this->compileWheres($where['value']);

        $relationshipFilter = "has_{$relationship}";
        $type = $relationship === 'parent' ? 'parent_type' : 'type';

        // Use filter as query if query is empty
        if (empty($compiled['query'])) {
            $compiled['query'] = empty($compiled['filter']) ? ['match_all' => (object) []] : $compiled['filter'];
        } elseif (! empty($compiled['filter'])) {
            throw new InvalidArgumentException('Cannot use both filter and query contexts within a relation context');
        }

        $query = [
            $relationshipFilter => [
                $type => $where['documentType'],
                'query' => $compiled['query'],
            ],
        ];

        return $this->applyOptionsToClause($query, $where);
    }

    // ----------------------------------------------------------------------
    // Option Appliers
    // ----------------------------------------------------------------------

    /**
     * Apply options (boost, inner_hits, etc.) to a compiled clause
     */
    protected function applyOptionsToClause(array $clause, array $where): array
    {
        if (empty($where['options'])) {
            return $clause;
        }
        $optionsToApply = ['inner_hits'];
        $options = array_intersect_key($where['options'], array_flip($optionsToApply));

        foreach ($options as $option => $value) {
            $method = 'apply'.Str::studly($option).'Option';
            if (method_exists($this, $method)) {
                $clause = $this->$method($clause, $value, $where);
            }
        }

        return $clause;
    }

    /**
     * Apply inner_hits to nested queries
     */
    protected function applyInnerHitsOption(array $clause, $options, $where): array
    {
        $innerHits = $this->_buildInnerHits($where['query']);

        return DslFactory::applyInnerHits($clause, $innerHits);
    }

    /**
     * Apply boost to a clause
     */
    protected function applyBoostOption(array $clause, mixed $value, array $where): array
    {
        return DslFactory::applyBoost($clause, $value);
    }
}
