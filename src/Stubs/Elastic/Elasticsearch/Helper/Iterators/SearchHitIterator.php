<?php

namespace Elastic\Elasticsearch\Helper\Iterators;

class SearchHitIterator implements \Iterator
{
    public function current(): mixed
    {
        return null;
    }

    public function next(): void
    {
    }

    public function key(): mixed
    {
        return null;
    }

    public function valid(): bool
    {
        return false;
    }

    public function rewind(): void
    {
    }
}
