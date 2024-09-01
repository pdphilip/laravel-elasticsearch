<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Pagination;

use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Collection;

class SearchAfterPaginator extends CursorPaginator
{
    /**
     * @param  \PDPhilip\Elasticsearch\Eloquent\Model  $item
     */
    public function getParametersForItem($item): array
    {
        $cursor = $item->getMeta()->getCursor();
        $search_after = $item->getMeta()->getSort();
        $cursor['page']++;
        $cursor['next_sort'] = $search_after;

        return $cursor;
    }

    public function toArray(): array
    {
        return [
            'data' => $this->items->toArray(),
            'path' => $this->path(),
            'per_page' => $this->perPage(),
            'next_cursor' => $this->nextCursor()?->encode(),
            'next_page_url' => $this->nextPageUrl(),
            'prev_cursor' => $this->previousCursor()?->encode(),
            'prev_page_url' => $this->previousPageUrl(),
            'current_page' => $this->currentPageNumber(),
            'total' => $this->totalRecords(),
            'from' => $this->showingFrom(),
            'to' => $this->showingTo(),
            'last_page' => $this->lastPage(),

        ];
    }

    public function currentPageNumber(): int
    {
        return $this->options['currentPage'];
    }

    public function totalRecords(): int
    {
        return $this->options['records'];
    }

    public function showingFrom(): int
    {
        $perPage = $this->perPage();
        $currentPage = $this->currentPageNumber();
        $start = ($currentPage - 1) * $perPage + 1;

        return $start;
    }

    public function showingTo(): int
    {
        $records = count($this->items);
        $currentPage = $this->currentPageNumber();
        $perPage = $this->perPage();
        $end = (($currentPage - 1) * $perPage) + $records;

        return $end;
    }

    public function lastPage(): int
    {
        return $this->options['totalPages'];
    }

    //  Builds the cursor for the previous page
    public function previousCursor(): ?Cursor
    {
        if (! $this->cursor) {
            return null;
        }
        $current = $this->cursor->toArray();
        if ($current['page'] < 2) {
            return null;
        }
        $previousCursor = $current;
        unset($previousCursor['_pointsToNextItems']);
        $previousCursor['page']--;
        $previousCursor['next_sort'] = array_pop($previousCursor['sort_history']);

        return new Cursor($previousCursor, false);
    }

    protected function setItems($items): void
    {
        $this->items = $items instanceof Collection ? $items : Collection::make($items);
        $this->hasMore = $this->options['currentPage'] < $this->options['totalPages'];
    }
}
