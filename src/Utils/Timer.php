<?php

namespace PDPhilip\Elasticsearch\Utils;

trait Timer
{
    private array $timer = [];

    public function startTimer()
    {
        $this->timer['start'] = microtime(true);

        return $this;
    }

    public function stopTimer()
    {
        $this->timer['end'] = microtime(true);

        return $this;
    }

    public function getTime()
    {
        $this->_timingCalcs();

        return $this->timer['time'];
    }

    private function _timingCalcs(): void
    {
        if (! empty($this->timer['start'])) {

            if (empty($this->timer['end'])) {
                $this->timer['end'] = microtime(true);
            }

            $this->timer['took'] = ($this->timer['end'] - $this->timer['start']) * 1000000; // Convert to microseconds

            $this->timer['time']['μs'] = round($this->timer['took'], 0); // Microseconds
            $this->timer['time']['ms'] = round($this->timer['took'] / 1000, 2); // Milliseconds
            $this->timer['time']['sec'] = round($this->timer['took'] / 1000000, 6); // Seconds
            $this->timer['time']['min'] = round($this->timer['took'] / 60000000, 6); // Minutes
        } else {
            $this->timer['time'] = 'Timer was not initialized';
        }

    }
}
