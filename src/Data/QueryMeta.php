<?php

namespace PDPhilip\Elasticsearch\Data;

final class QueryMeta
{
    protected string $index = '';

    protected string $query = '';

    protected bool $success = false;

    protected bool $timed_out = false;

    protected int $took = -1;

    protected int $total = -1;

    protected int $hits = -1;

    protected mixed $maxScore = '';

    protected mixed $_id = '';

    protected mixed $shards = [];

    protected array $dsl = [];

    protected array $results = [];

    protected array $_meta = [];

    protected array $error = [];

    protected string $errorMessage = '';

    protected array $sort = [];

    protected array $cursor = [];

    protected array $afterKey = [];

    public function __construct(?MetaDTO $meta = null)
    {
        if (! $meta) {
            return;
        }
        $this->index = $meta->getIndex();
        $this->timed_out = $meta->timed_out ?? false;
        $this->took = $meta->getTook();
        $this->hits = $meta->getHits();
        $this->total = $meta->getTotal();
        $this->maxScore = $meta->getMaxScore();
        $this->shards = $meta->getShards();
        $this->query = $meta->getQuery();
        $this->dsl = $meta->getDsl();
        $this->afterKey = $meta->getAfterKey();
        $this->sort = $meta->getSort();
        $this->cursor = $meta->getCursor();
        $this->_meta = $meta->toArray();
    }

    // ----------------------------------------------------------------------
    // Getters
    // ----------------------------------------------------------------------

    public function getIndex(): mixed
    {
        return $this->index ?? null;
    }

    public function getId(): mixed
    {
        return $this->_id ?? null;
    }

    public function getModified(): int
    {
        return $this->getResults('modified') ?? 0;
    }

    public function getDeleted(): int
    {
        return $this->getResults('deleted') ?? 0;
    }

    public function getCreated(): int
    {
        return $this->getResults('created') ?? 0;
    }

    public function isSuccessful(): bool
    {
        return $this->success;
    }

    public function getSort(): ?array
    {
        return $this->sort;
    }

    public function getCursor(): ?array
    {
        return $this->cursor;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getDsl(): array
    {
        return $this->dsl;
    }

    public function getTook(): int
    {
        return $this->took;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getMaxScore(): string
    {
        return $this->maxScore;
    }

    public function getShards(): mixed
    {
        return $this->shards;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    public function getError(): array
    {
        return $this->error;
    }

    public function toArray(): array
    {
        $return = [
            'index' => $this->index,
            'query' => $this->query,
            'success' => $this->success,
            'timed_out' => $this->timed_out,
            'took' => $this->took,
            'total' => $this->total,
        ];
        if ($this->maxScore) {
            $return['max_score'] = $this->maxScore;
        }
        if ($this->shards) {
            $return['shards'] = $this->shards;
        }
        if ($this->dsl) {
            $return['dsl'] = $this->dsl;
        }
        if ($this->_id) {
            $return['_id'] = $this->_id;
        }
        if ($this->results) {
            foreach ($this->results as $key => $value) {
                $return[$key] = $value;
            }
        }
        if ($this->error) {
            $return['error'] = $this->error;
            $return['errorMessage'] = $this->errorMessage;
        }
        if ($this->sort) {
            $return['sort'] = $this->sort;
        }
        if ($this->cursor) {
            $return['cursor'] = $this->cursor;
        }
        if ($this->_meta) {
            $return['_meta'] = $this->_meta;
        }

        return $return;
    }

    public function getResults($key = null)
    {
        if ($key) {
            return $this->results[$key] ?? null;
        }

        return $this->results;
    }

    // ----------------------------------------------------------------------
    // Setters
    // ----------------------------------------------------------------------
    public function setIndex($index): void
    {
        $this->index = $index;
    }

    public function setId($id): void
    {
        $this->_id = $id;
    }

    public function setTook(int $took): void
    {
        $this->took = $took;
    }

    public function setTotal(int $total): void
    {
        $this->total = $total;
    }

    public function setQuery($query): void
    {
        $this->query = $query;
    }

    public function setSuccess(): void
    {
        $this->success = true;
    }

    public function setResult($key, $value): void
    {
        $this->results[$key] = $value;
    }

    public function setModified(int $count): void
    {
        $this->setResult('modified', $count);
    }

    public function setCreated(int $count): void
    {
        $this->setResult('created', $count);
    }

    public function setDeleted(int $count): void
    {
        $this->setResult('deleted', $count);
    }

    public function setFailed(int $count): void
    {
        $this->setResult('failed', $count);
    }

    public function setSort(array $sort): void
    {
        $this->sort = $sort;
    }

    public function setCursor(array $cursor): void
    {
        $this->cursor = $cursor;
    }

    public function setDsl($params)
    {
        $this->dsl = $params;
    }

    public function setError(array $error, string $errorMessage = ''): void
    {
        $this->success = false;
        $this->error = $error;
        $this->errorMessage = $errorMessage;
    }

    public function parseAndSetError($error, $errorCode)
    {
        $errorMessage = $error;
        $this->success = false;
        $details = $this->_decodeError($errorMessage);
        $error = [
            'msg' => $details['msg'],
            'data' => $details['data'],
            'code' => $errorCode,
        ];
        $this->error = $error;
        $this->errorMessage = $errorMessage;
    }

    private function _decodeError($error): array
    {
        $return['msg'] = $error;
        $return['data'] = [];
        $jsonStartPos = strpos($error, ': ') + 2;
        $response = ($error);
        $title = substr($response, 0, $jsonStartPos);
        $jsonString = substr($response, $jsonStartPos);
        if ($this->_isJson($jsonString)) {
            $errorArray = json_decode($jsonString, true);
        } else {
            $errorArray = [$jsonString];
        }

        if (json_last_error() === JSON_ERROR_NONE) {
            $errorReason = $errorArray['error']['reason'] ?? null;
            if (! $errorReason) {
                return $return;
            }
            $return['msg'] = $title.$errorReason;
            $cause = $errorArray['error']['root_cause'][0]['reason'] ?? null;
            if ($cause) {
                $return['msg'] .= ' - '.$cause;
            }

            $return['data'] = $errorArray;
        }

        return $return;
    }

    private function _isJson($string): bool
    {
        return json_validate($string);
    }
}
