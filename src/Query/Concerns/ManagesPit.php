<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Query\Concerns;

use PDPhilip\Elasticsearch\Connection;
use PDPhilip\Elasticsearch\Eloquent\ElasticCollection;
use PDPhilip\Elasticsearch\Query\Builder;
use PDPhilip\Elasticsearch\Query\Grammar\Grammar;

/**
 * Manages Point In Time (PIT) API operations for Elasticsearch queries.
 *
 * @property Connection $connection
 * @property Grammar $grammar
 * @property mixed $pitId
 * @property string $keepAlive
 * @property mixed $searchAfter
 * @property array $cursorMeta
 *
 * @mixin Builder
 */
trait ManagesPit
{
    public function viaPit($pid, $afterKey): self
    {
        if (! $pid) {
            $pid = $this->openPit();
        }
        $this->pitId = $pid;
        $this->searchAfter = $afterKey;

        return $this;
    }

    public function searchAfter($afterKey): self
    {
        $this->searchAfter = $afterKey;

        return $this;
    }

    public function withPitId(?string $value): self
    {
        $this->pitId = $value;

        return $this;
    }

    public function keepAlive(string $value): self
    {
        $this->keepAlive = $value;

        return $this;
    }

    public function openPit(): string
    {
        $id = $this->connection->openPit($this->grammar->compileOpenPit($this));
        $this->withPitId($id);

        return $id;
    }

    /**
     * Apply PIT and get()
     */
    public function getPit($columns = ['*']): ElasticCollection
    {
        if (! $this->pitId) {
            $this->openPit();
        }

        return $this->get($columns);
    }

    public function closePit($pidId = null): bool
    {
        if ($pidId) {
            $this->withPitId($pidId);
        }
        $didClose = $this->connection->closePit($this->grammar->compileClosePit($this));
        $this->withPitId(null);

        return $didClose;
    }

    public function chunkByPit($count, callable $callback, $keepAlive = '1m'): bool
    {

        $this->keepAlive = $keepAlive;
        $pitId = $this->openPit();

        $searchAfter = null;
        $page = 1;
        do {
            $clone = clone $this;
            $clone->viaPit($pitId, $searchAfter);
            $results = $clone->getPit();
            $searchAfter = $results->getAfterKey();
            $countResults = $results->count();

            if ($countResults == 0) {
                break;
            }

            if ($callback($results, $page) === false) {
                return true;
            }

            unset($results);

            $page++;
        } while ($countResults == $count);

        $this->closePit($pitId);

        return true;
    }

    public function initCursorMeta($cursor): array
    {
        $this->cursorMeta = [
            'pit_id' => null,
            'page' => 1,
            'pages' => 0,
            'records' => 0,
            'sort_history' => [],
            'next_sort' => null,
            'ts' => 0,
        ];

        if (! empty($cursor)) {
            $this->cursorMeta = [
                'pit_id' => $cursor->parameter('pit_id'),
                'page' => $cursor->parameter('page'),
                'pages' => $cursor->parameter('pages'),
                'records' => $cursor->parameter('records'),
                'sort_history' => $cursor->parameter('sort_history'),
                'next_sort' => $cursor->parameter('next_sort'),
                'ts' => $cursor->parameter('ts'),
            ];
        }

        return $this->cursorMeta;
    }

    public function setCursorMeta($cursorPagination)
    {
        $this->cursorMeta = $cursorPagination;

        return $this;
    }

    public function getCursorMeta()
    {
        return $this->cursorMeta;
    }
}
