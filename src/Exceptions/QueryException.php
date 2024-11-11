<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Exceptions;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Exception;
use Illuminate\Support\Collection;

class QueryException extends Exception
{
    public function __construct(Exception $previous)
    {
      parent::__construct($this->formatMessage($previous), $previous->code);
    }


  /**
   * Format the error message.
   *
   * Takes the first {$this->errorLimit} bulk issues and concatenates them to a single string message
   *
   * @param  Exception  $result
   * @return string
   */
  private function formatMessage(Exception $result): string
  {
    // Clean that ish up.
    return match (get_class($result)) {
      MissingParameterException::class => $this->formatMissingParameterException($result),
      ClientResponseException::class => $this->routeClientResponseException($result),
    };
  }

  private function formatMissingParameterException($result): string
  {
    return $result->getMessage();
  }

  private function routeClientResponseException($result): string
  {

    $error = json_decode((string) $result->getResponse()->getBody(), true);

    return match ($error['error']['type']) {
      'search_phase_execution_exception' => $this->formatSearchPhaseExecutionException($error),
      'script_exception' => $this->formatScriptException($error),
      'resource_already_exists_exception', 'mapper_parsing_exception' ,'parse_exception' => $this->formatParseException($error),
    };
  }

  private function formatSearchPhaseExecutionException($error): string
  {
    $message = collect();
    $message->push("{$error['error']['type']}: {$error['error']['reason']}");

    foreach ($error['error']['root_cause'] as $phase) {
      if($phase['type'] == 'script_exception'){
        $message->push("Caused By: {$phase['type']}");
        $message->push(...$phase['script_stack']);
      } else {
        $message->push("Caused By: {$error['type']}");
        $message->push("Index: {$phase['index']}");
        $message->push("Reason: {$phase['reason']}");
      }
    }

    return $message->implode(PHP_EOL);
  }

  private function formatParseException($error): string
  {
    $message = collect();
    $message->push("{$error['error']['type']}: {$error['error']['reason']}");
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
