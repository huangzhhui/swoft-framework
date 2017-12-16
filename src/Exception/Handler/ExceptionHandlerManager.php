<?php

namespace Swoft\Exception\Handler;

use Swoft\App;

/**
 * exception handler manager
 *
 * @uses      ExceptionHandlerManager
 * @version   2017-11-11
 * @author    huangzhhui <huangzhwork@gmail.com>
 * @copyright Copyright 2010-2017 Swoft software
 * @license   PHP Version 7.x {@link http://www.php.net/license/3_0.txt}
 */
class ExceptionHandlerManager
{

    /**
     * Default exception handlers
     *
     * @var array
     */
    protected static $defaultExceptionHandlers = [
            SystemErrorHandler::class        => 1,
            RuntimeExceptionHandler::class   => 2,
            HttpExceptionHandler::class      => 3,
            ValidatorExceptionHandler::class => 4,
            ServiceExceptionHandler::class   => 5,
        ];

    /**
     * Use to store exception handlers
     * The user defined handler priority greater than 10 is better
     *
     * @var \SplPriorityQueue
     */
    protected static $queue;

    /**
     * Handle the exception and return a response
     *
     * @param \Throwable $throwable
     * @return null|\Swoft\Web\Response
     */
    public static function handle(\Throwable $throwable)
    {
        self::printException($throwable);
        $response = null;
        $queue = clone self::getQueue();
        while ($queue->valid()) {
            $current = $queue->current();
            $instance = new $current();
            if ($instance instanceof AbstractHandler) {
                $instance->setException($throwable);
                if ($instance->isHandle()) {
                    $response = $instance->handle();
                    $response instanceof AbstractHandler && $response = $response->toResponse();
                    break;
                }
            }
            $queue->next();
        }
        return $response;
    }

    /**
     * Get exception handler queue
     *
     * @return \SplPriorityQueue
     */
    public static function getQueue(): \SplPriorityQueue
    {
        self::initQueue();
        return self::$queue;
    }

    /**
     * Init $queue property, and add the default handlers to queue
     */
    protected static function initQueue()
    {
        if (!self::$queue instanceof \SplPriorityQueue) {
            self::$queue = new \SplPriorityQueue();
            foreach (self::$defaultExceptionHandlers as $handler => $priority) {
                self::$queue->insert($handler, $priority);
            }
        }
    }

    /**
     * Print the exception when DISPLAY_ERRORS is true
     * @param \Throwable $throwable
     */
    private static function printException(\Throwable $throwable)
    {
        if (App::isWorkerStatus() && env('DISPLAY_ERRORS', false)) {
            $className = get_class($throwable);
            printf(str_repeat('-',
                    strlen($className) + 9) . PHP_EOL . 'Catch an %s' . PHP_EOL . 'Message: %s' . PHP_EOL . 'Code: %s' . PHP_EOL . 'File: %s (#%s)' . PHP_EOL,
                $className, $throwable->getMessage(), $throwable->getCode(), $throwable->getFile(),
                $throwable->getLine());
        }
    }

}
