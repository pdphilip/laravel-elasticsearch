<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Pagination;

use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Collection;

class ElasticsearchPaginator extends CursorPaginator
{
    public function getParametersForItem($item)
    {
        return [
            'search_after' => $item->getMeta()->sort,
        ];
    }

    protected function setItems($items)
    {
        $this->items = $items instanceof Collection ? $items : Collection::make($items);

        $this->hasMore = $this->items->count() >= $this->perPage;

        $this->items = $this->items->slice(0, $this->perPage);

        if (! is_null($this->cursor) && $this->cursor->pointsToPreviousItems()) {
            $this->items = $this->items->reverse()->values();
        }
    }
}
