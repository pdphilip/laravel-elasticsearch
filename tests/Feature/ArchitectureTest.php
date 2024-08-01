<?php

  declare(strict_types=1);

  #saftey tests to make sure we never push up a package that can dump and die.
  arch("doesn't use dd, dump, or var_dump")
    ->expect(['dd', 'dump', 'var_dump'])
    ->not->toBeUsed();
