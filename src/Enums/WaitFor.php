<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Enums;

enum WaitFor
{
    case TRUE;
    case FALSE;
    case WAITFOR;

    public function get(): string|bool
    {
        return match ($this) {
            WaitFor::TRUE => true,
            WaitFor::FALSE => false,
            WaitFor::WAITFOR => 'wait_for',
        };
    }

    public function getDelete(): bool
    {
        return match ($this) {
            WaitFor::TRUE, WaitFor::WAITFOR => true,
            WaitFor::FALSE => false,
        };
    }
}
