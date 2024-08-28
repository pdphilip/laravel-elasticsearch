<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Pagination;

use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Collection;

class SearchAfterPaginator extends CursorPaginator
{
    public function getParametersForItem($item)
    {
        //@phpstan-ignore-next-line
        $sort = $item->getMeta()->sort;

        return [
            'search_after' => $sort,
        ];
    }

    protected function setItems($items)
    {
        $this->items = $items instanceof Collection ? $items : Collection::make($items);

        // FIXME: We need to account fot the scenario where $this->perPage == $this->items->count()
        // but there are no more records and this ends up doing an extra pull.
        $this->hasMore = $this->items->count() >= $this->perPage;

        $this->items = $this->items->slice(0, $this->perPage);

        if (! is_null($this->cursor) && $this->cursor->pointsToPreviousItems()) {
            $this->items = $this->items->reverse()->values();
        }
    }
}
