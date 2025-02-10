<?php

namespace PDPhilip\Elasticsearch\Query\DSL;

//@internal
class DslCompiler
{
    protected array $query = [];

    protected array $bool = [];

    protected int $clauses = 0;

    protected array $lastClause = [];

    public function setAnd($clause): void
    {
        $this->_attachMust($clause);
    }

    public function setAndNot($clause): void
    {
        $this->_attachMustNot($clause);
    }

    public function setOr($clause): void
    {
        $this->_resetBool();
        $this->_attachMust($clause);
    }

    public function setOrNot($clause): void
    {
        $this->_resetBool();
        $this->_attachMustNot($clause);
    }

    public function setResult($clause, $isOr, $isNot)
    {
        $type = $isOr ? 'Or' : 'And';
        $suffix = $isNot ? 'Not' : '';
        $method = 'set'.$type.$suffix;
        $this->$method($clause);
    }

    public function compileQuery()
    {
        if (! empty($this->query['should'])) {
            $this->query['should'][] = ['bool' => $this->bool];

            return ['bool' => $this->query];
        }
        if (! $this->bool) {
            return ['match_all' => (object) []];
        }
        if ($this->clauses === 1) {
            return $this->lastClause;
        }

        return ['bool' => $this->bool];

    }

    //----------------------------------------------------------------------
    // Protected Methods
    //----------------------------------------------------------------------

    protected function trackClause($clause)
    {
        $this->clauses++;
        $this->lastClause = $clause;
    }

    protected function _attachMust($clause): void
    {
        if (! isset($this->bool['must'])) {
            $this->bool['must'] = [];
        }
        $this->bool['must'][] = $clause;
        $this->trackClause($clause);
    }

    protected function _attachMustNot($clause): void
    {
        if (! isset($this->bool['must_not'])) {
            $this->bool['must_not'] = [];
        }
        $this->bool['must_not'][] = $clause;
        //Double inc to ensure it's not seen as a single clause
        $this->clauses += 2;
    }

    protected function _resetBool(): void
    {
        if ($this->bool) {
            if (! isset($this->query['should'])) {
                $this->query['should'] = [];
            }
            $this->query['should'][] = ['bool' => $this->bool];
            $this->bool = [];
        }

    }
}
