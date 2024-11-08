<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Schema;

use Closure;
use Exception;
use PDPhilip\Elasticsearch\Connection;
use PDPhilip\Elasticsearch\DSL\Results;
use Illuminate\Database\Schema\Builder as BaseBuilder;

class Builder extends BaseBuilder
{
  /**
   * @param         $table
   * @param Closure $callback
   */
  public function index($table, Closure $callback)
  {
    $this->table($table, $callback);
  }

  /**
   * @param string  $table
   * @param Closure $callback
   */
  public function table($table, Closure $callback)
  {
    $this->build(tap($this->createBlueprint($table), function (Blueprint $blueprint) use ($callback) {
      $blueprint->update();

      $callback($blueprint);
    }));
  }

  /**
   * @inheritDoc
   */
  protected function createBlueprint($table, Closure $callback = null)
  {
    return new Blueprint($table, $callback);
  }
}
