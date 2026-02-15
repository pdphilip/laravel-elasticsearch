<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Eloquent;

trait SoftDeletes
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    /**
     * {@inheritdoc}
     */
    public function getQualifiedDeletedAtColumn(): string
    {
        return $this->getDeletedAtColumn();
    }
}
