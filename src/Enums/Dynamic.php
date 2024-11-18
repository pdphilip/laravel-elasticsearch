<?php

  declare(strict_types=1);

  namespace PDPhilip\Elasticsearch\Enums;

  enum Dynamic
  {
    case TRUE;
    case RUNTIME;

    public function value(): string|bool {
      return match($this)
      {
        Dynamic::TRUE => true,
        Dynamic::RUNTIME => 'runtime'
      };
    }
  }
