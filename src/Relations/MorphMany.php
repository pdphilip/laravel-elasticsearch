<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Relations;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\MorphMany as BaseMorphMany;
use PDPhilip\Elasticsearch\Traits\Relations\ManagesRefresh;

class MorphMany extends BaseMorphMany
{

  use ManagesRefresh;

  /**
   * Get the name of the "where in" method for eager loading.
   *
   * @param string $key
   *
   * @return string
   */
  protected function whereInMethod(EloquentModel $model, $key)
  {
    return 'whereIn';
  }
}
