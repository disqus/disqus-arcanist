<?php

/**
 * SentryLogger::setClient($client);
 * PhutilErrorHandler::setErrorListener(
 *   array('SentryLogger', 'handleErrors'));
 */

final class SentryLogger {

  private static $client = null;
  private static $errorHandler = null;

  public static function setClient($client, $error_handler=null) {
    self::$client = $client;
    if (!$error_handler) {
      $error_handler = new Raven_ErrorHandler($client);
    }
    self::$errorHandler = $error_handler;
  }

  public static function handleErrors($event, $value, $metadata) {
    if (!self::$client) {
      return;
    }
    switch ($event) {
      case PhutilErrorHandler::EXCEPTION:
        // $value is of type Exception
        self::$client->captureException($value);
        break;
      case PhutilErrorHandler::ERROR:
        // $value is a simple string
        self::$errorHandler->handleError($metadata['error_code'],
          $value,
          $metadata['file'],
          $metadata['line'],
          $metadata['context']);
        break;
    }
  }

}
