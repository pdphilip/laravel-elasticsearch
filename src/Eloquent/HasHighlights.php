<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Eloquent;

trait HasHighlights
{
    public function getHighlights()
    {
        return $this->getMeta()->getHighlights();
    }

    public function getHighlight($column, $deliminator = '')
    {
        return $this->getMeta()->getHighlight($column);
    }

    public function getSearchHighlightsAttribute(): ?object
    {
        return $this->getMeta()->parseHighlights();
    }

    public function getSearchHighlightsAsArrayAttribute(): array
    {
        return $this->getHighlights();
    }

    public function getWithHighlightsAttribute(): object
    {
        $data = $this->attributes;
        $mutators = array_values(array_diff($this->getMutatedAttributes(), [
            'id',
            'search_highlights',
            'search_highlights_as_array',
            'with_highlights',
        ]));
        if ($mutators) {
            foreach ($mutators as $mutator) {
                $data[$mutator] = $this->{$mutator};
            }
        }

        return (object) $this->getMeta()->parseHighlights($data);
    }
}
