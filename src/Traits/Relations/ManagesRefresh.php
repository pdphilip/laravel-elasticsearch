<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Traits\Relations;

trait ManagesRefresh
{
    public function withoutRefresh(): self
    {

        //TODO: Need to check if this is an inverse relationship etc. Need to attach the option in proper places.

        // Add the `refresh` option to the related model
        $this->related->options()->add('refresh', false);

        return $this;
    }
}
