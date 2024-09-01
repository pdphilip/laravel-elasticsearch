<?php

namespace PDPhilip\Elasticsearch\Meta;

final class QueryMetaData
{
    protected string $query = '';

    protected bool $success = false;

    protected bool $timed_out = false;

    protected int $took = -1;

    protected int $total = -1;

    protected string $max_score = '';

    protected mixed $_id = '';

    protected array $shards = [];

    protected array $dsl = [];

    protected array $error = [];

    protected array $results = [];

    protected array $_meta = [];

    protected string $errorMessage = '';

    protected array $sort = [];

    protected array $cursor = [];

    public function __construct($meta)
    {
        $this->timed_out = $meta['timed_out'] ?? false;
        unset($meta['timed_out']);
        if (isset($meta['took'])) {
            $this->took = $meta['took'];
            unset($meta['took']);
        }
        if (isset($meta['total'])) {
            $this->total = $meta['total'];
            unset($meta['total']);
        }
        if (isset($meta['max_score'])) {
            $this->max_score = $meta['max_score'];
            unset($meta['max_score']);
        }
        if (isset($meta['total'])) {
            $this->total = $meta['total'];
            unset($meta['total']);
        }
        if (isset($meta['shards'])) {
            $this->shards = $meta['shards'];
            unset($meta['shards']);
        }
        if (isset($meta['sort'])) {
            $this->sort = $meta['sort'];
            unset($meta['sort']);
        }
        if (isset($meta['cursor'])) {
            $this->cursor = $meta['cursor'];
            unset($meta['cursor']);
        }
        if (isset($meta['_id'])) {
            $this->_id = $meta['_id'];
            unset($meta['_id']);
        }
        if ($meta) {
            $this->_meta = $meta;
        }
    }

    //----------------------------------------------------------------------
    // Getters
    //----------------------------------------------------------------------

    public function getId(): mixed
    {
        return $this->_id ?? null;
    }

    public function getModified(): int
    {
        return $this->modified ?? 0;
    }

    public function getDeleted(): int
    {
        return $this->deleted ?? 0;
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

    public function getQuery()
    {
        return $this->query;
    }

    public function getDsl()
    {
        return $this->dsl;
    }

    public function getTook()
    {
        return $this->took;
    }

    public function getTotal()
    {
        return $this->total;
    }

    public function getMaxScore()
    {
        return $this->max_score;
    }

    public function getShards()
    {
        return $this->shards;
    }

    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    public function getError()
    {
        return $this->error;
    }

    public function asArray(): array
    {
        $return = [
            'query' => $this->query,
            'success' => $this->success,
            'timed_out' => $this->timed_out,
            'took' => $this->took,
            'total' => $this->total,
            'max_score' => $this->max_score,
            'shards' => $this->shards,
            'dsl' => $this->dsl,
        ];
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

    //----------------------------------------------------------------------
    // Setters
    //----------------------------------------------------------------------
    public function setId($id): void
    {
        $this->_id = $id;
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

    public function setError($error, $errorCode)
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
