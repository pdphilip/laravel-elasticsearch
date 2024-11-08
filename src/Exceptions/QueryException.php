<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Exceptions;

use Exception;
use Illuminate\Support\Collection;

class QueryException extends Exception
{
    private array $_details;

    public function __construct($message, $code = 0, ?Exception $previous = null)
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
    $error = json_decode((string) $result->getResponse()->getBody(), true);

    // Clean that ish up.
    return match ($error['error']['type']) {
      'script_exception' => $this->formatScriptException($error),
      'index_not_found_exception' => $error['error']['reason'] = 'Index does not exist',
    };
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
