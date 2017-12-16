<?php

namespace Swoft\Middleware\Http;

use Interop\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Swoft\Middleware\MiddlewareInterface;
use Swoft\Web\Cookie\CookieJar;
use Swoft\Web\Session\Handler\CookiesSessionHandler;


/**
 * @uses      StartSession
 * @version   2017年12月05日
 * @author    huangzhhui <huangzhwork@gmail.com>
 * @copyright Copyright 2010-2017 Swoft software
 * @license   PHP Version 7.x {@link http://www.php.net/license/3_0.txt}
 */
class StartSession implements MiddlewareInterface
{

    /**
     * Process an incoming server request and return a response, optionally delegating
     * response creation to a handler.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Interop\Http\Server\RequestHandlerInterface $handler
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $session = $this->startSession($request);
        $response = $handler->handle($request);
        return $response->withHeader($name, $value);
    }

    /**
     * @param ServerRequestInterface $request
     * @return CookiesSessionHandler
     */
    private function startSession(ServerRequestInterface $request)
    {
        $cookie = new CookieJar();
        $session = new CookiesSessionHandler($request, $cookie);
        return $session;
    }

}