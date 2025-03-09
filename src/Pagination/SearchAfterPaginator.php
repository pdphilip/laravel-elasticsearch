<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Pagination;

use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Collection;
use PDPhilip\Elasticsearch\Eloquent\Model;

class SearchAfterPaginator extends CursorPaginator
{
    /**
     * @param  Model  $item
     */
    public function getParametersForItem($item): array
    {
        return [];
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
            'last_page' => $this->totalPages(),

        ];
    }

    public function nextCursor(): ?Cursor
    {
        if (! $this->hasMore) {
            return null;
        }
        if (! $this->cursor) {
            $current = $this->cursorMeta();
        } else {
            $current = $this->cursor->toArray();
        }
        $current['page']++;
        $current['next_sort'] = $this->searchAfter();

        return new Cursor($current, true);
    }

    public function cursorMeta(): array
    {
        return $this->options['cursorMeta'];
    }

    public function searchAfter(): array
    {
        return $this->options['searchAfter'];
    }

    public function currentPageNumber(): int
    {
        return $this->cursorMeta()['page'];
    }

    public function totalRecords(): int
    {
        return $this->cursorMeta()['records'];
    }

    public function totalPages()
    {
        return $this->cursorMeta()['pages'];
    }

    public function showingFrom(): int
    {
        $perPage = $this->perPage();
        $currentPage = $this->currentPageNumber();

        return ($currentPage - 1) * $perPage + 1;

    }

    public function showingTo(): int
    {
        $records = count($this->items);
        $currentPage = $this->currentPageNumber();
        $perPage = $this->perPage();

        return (($currentPage - 1) * $perPage) + $records;
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
        $this->hasMore = $this->currentPageNumber() < $this->totalPages();
    }
}
