<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Traits\Eloquent;

use PDPhilip\Elasticsearch\Eloquent\Model;

/**
 * @see self::withoutRefreshManagesQueryParameters()
 *
 * @method Model withoutRefresh()
 *
 * @see self::proceedOnConflictsManagesQueryParameters()
 *
 * @method Model proceedOnConflicts()
 */
trait ManagesQueryParameters
{
    public function withoutRefreshManagesQueryParameters(): self
    {
        // Add the `refresh` option to the model or query
        $this->options()->add('refresh', false);

        return $this;
    }

    public function proceedOnConflictsManagesQueryParameters(): self
    {
        // Add the `conflicts` option to the model or query
        $this->options()->add('conflicts', 'proceed');

        return $this;
    }
}
