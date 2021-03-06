<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-aurarouter for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-aurarouter/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Router\AuraRouter;

use Zend\Expressive\Router\AuraRouter;
use Zend\Expressive\Router\RouterInterface;

class ConfigProvider
{
    public function __invoke() : array
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }

    public function getDependencies() : array
    {
        return [
            'aliases' => [
                RouterInterface::class => AuraRouter::class,
            ],
            'invokables' => [
                AuraRouter::class => AuraRouter::class,
            ],
        ];
    }
}
