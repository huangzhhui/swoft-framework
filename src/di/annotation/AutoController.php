<?php

namespace swoft\di\annotation;

/**
 *
 * 控制器自动解析注解路由
 *
 * @Annotation
 * @Target("CLASS")
 *
 * @uses      Controller
 * @version   2017年08月22日
 * @author    stelin <phpcrazy@126.com>
 * @copyright Copyright 2010-2016 swoft software
 * @license   PHP Version 7.x {@link http://www.php.net/license/3_0.txt}
 */
class AutoController
{
    /**
     * @var string
     */
    private $prefix = "";

    public function __construct(array $values)
    {
        if (isset($values['value'])) {
            $this->prefix = $values['value'];
        }
        if (isset($values['prefix'])) {
            $this->prefix = $values['prefix'];
        }
    }

    /**
     * 获取controller前缀
     *
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }
}