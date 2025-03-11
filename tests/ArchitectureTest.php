<?php

declare(strict_types=1);

// saftey tests to make sure we never push up a package that can dump and die.
arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();
