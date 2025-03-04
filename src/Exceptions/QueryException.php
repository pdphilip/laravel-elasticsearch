<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Exceptions;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Exception;

final class QueryException extends Exception
{
    private int $errorLimit = 10;

    public function __construct(Exception $previous)
    {
        parent::__construct($this->formatMessage($previous), $previous->code);
    }

    /**
     * Format the error message.
     */
    private function formatMessage(Exception $result): string
    {
        // Clean that ish up.
        return match (get_class($result)) {
            MissingParameterException::class => $this->formatMissingParameterException($result),
            ClientResponseException::class => $this->routeClientResponseException($result),
            default => $result->getMessage(),
        };
    }

    private function formatMissingParameterException($result): string
    {
        return $result->getMessage();
    }

    private function routeClientResponseException($result): string
    {

        $error = json_decode((string) $result->getResponse()->getBody(), true);

        if (! empty($error['failures'])) {
            return $this->formatAsConflictException($error);
        }

        return match ($error['error']['type']) {
            'search_phase_execution_exception' => $this->formatSearchPhaseExecutionException($error),
            'script_exception' => $this->formatScriptException($error),
            default => $this->formatParseException($error),
        };
    }

    private function formatAsConflictException($error): string
    {

        $message = collect();

        // Clean that ish up.
        $items = collect($error['failures'] ?? [])
            ->filter(function (array $item) {
                return $item['index'] && ! empty($item['cause']);
            })
          // reduce to max limit
            ->slice(0, $this->errorLimit)
            ->values();

        $totalErrors = collect($error['failures'] ?? []);

        $message->push('Conflict Errors ('.'Showing '.$items->count().' of '.$totalErrors->count().'):');

        $items = $items->map(function (array $item) {
            return implode("\n", [
                "Index: {$item['index']}",
                "ID: {$item['id']}",
                "Error Type: {$item['cause']['type']}",
                "Error Reason: {$item['cause']['reason']}",
                "\n",
            ]);
        })->values()->toArray();

        $message->push(...$items);

        return $message->implode(PHP_EOL);
    }

    private function formatSearchPhaseExecutionException($error): string
    {
        $message = collect();
        $message->push("Error Type: {$error['error']['type']}");
        $message->push("Reason: {$error['error']['reason']}\n\n");

        foreach ($error['error']['root_cause'] as $phase) {
            if ($phase['type'] == 'script_exception') {
                $message->push("Caused By: {$phase['type']}");
                $message->push(...$phase['script_stack']);
            } else {
                $message->push("Caused By: {$error['error']['type']}");
                if (! empty($phase['index'])) {
                    $message->push("Index: {$phase['index']}");
                }
                $message->push("Reason: {$phase['reason']}");
            }
        }

        return $message->implode(PHP_EOL);
    }

    private function formatParseException($error): string
    {
        $message = collect();
        $message->push("Error Type: {$error['error']['type']}");
        $message->push("Reason: {$error['error']['reason']}\n\n");

        // Loop through root causes for detailed insights
        foreach ($error['error']['root_cause'] as $rootCause) {
            $message->push("Root Cause Type: {$rootCause['type']}");
            $message->push("Root Cause Reason: {$rootCause['reason']}");
        }

        // Add caused_by details if present
        if (isset($error['error']['caused_by'])) {
            $causedBy = $error['error']['caused_by'];
            $message->push("Caused By: {$causedBy['type']}");
            $message->push("Reason: {$causedBy['reason']}");
        }

        return $message->implode(PHP_EOL);
    }

    private function formatScriptException($error): string
    {
        $message = collect();

        $message->push("{$error['error']['type']}: {$error['error']['reason']}");
        $message->push("Caused By: {$error['error']['caused_by']['type']}");
        $message->push(...$error['error']['script_stack']);

        return $message->implode(PHP_EOL);
    }
}
