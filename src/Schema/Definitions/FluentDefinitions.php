<?php

namespace PDPhilip\Elasticsearch\Schema\Definitions;

use Illuminate\Support\Fluent;
use Illuminate\Support\Str;

class FluentDefinitions extends Fluent
{
    public function __call($method, $parameters)
    {
        $key = Str::Snake($method);
        unset($method);
        $this->{$key} = $parameters[0];

        return $this;
    }
}
