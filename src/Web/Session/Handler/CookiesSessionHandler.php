<?php

namespace Swoft\Web\Session\Handler;

use Swoft\Web\Cookie\CookieJarInterface;
use Swoft\Web\Cookie\SetCookie;
use Swoft\Web\Request;


/**
 * @uses      CookiesSessionHandler
 * @version   2017年12月05日
 * @author    huangzhhui <huangzhwork@gmail.com>
 * @copyright Copyright 2010-2017 Swoft software
 * @license   PHP Version 7.x {@link http://www.php.net/license/3_0.txt}
 */
class CookiesSessionHandler implements \SessionHandlerInterface
{

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var CookieJarInterface
     */
    protected $cookie;

    /**
     * CookiesSessionHandler constructor.
     * @param Request $request
     * @param CookieJarInterface $cookie
     */
    public function __construct(Request $request, CookieJarInterface $cookie)
    {
        $this->request = $request;
        $this->cookie = $cookie;
    }

    /**
     * @inheritdoc
     */
    public function close()
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function destroy($session_id)
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function gc($maxlifetime)
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function open($save_path, $name)
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function read($sessionId)
    {
        $value = $this->request->cookies->get($sessionId) ?: '';

        if (!is_null($decoded = json_decode($value, true)) && is_array($decoded)) {
            if (isset($decoded['expires']) && time() <= $decoded['expires']) {
                return $decoded['data'];
            }
        }

        return '';
    }

    /**
     * @inheritdoc
     */
    public function write($sessionId, $data)
    {
        $setCookie = new SetCookie(json_encode([
            'data' => $data,
            'expires' => time() + 3600,
        ]));
        $setCookie->setExpires(3600);

        $this->cookie->setCookie($setCookie);

        return true;
    }

}