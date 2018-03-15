<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-aurarouter for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-aurarouter/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Router;

use Generator;
use Zend\Expressive\Router\AuraRouter;
use Zend\Expressive\Router\RouterInterface;
use Zend\Expressive\Router\Test\ImplicitMethodsIntegrationTest as RouterIntegrationTest;

class ImplicitMethodsIntegrationTest extends RouterIntegrationTest
{
    public function getRouter() : RouterInterface
    {
        return new AuraRouter();
    }

    public function implicitRoutesAndRequests() : Generator
    {
        $options = [
            'tokens' => [
                'version' => '\d+',
            ],
        ];

        // @codingStandardsIgnoreStart
        //                  route                 route options, request       params
        yield 'static'  => ['/api/v1/me',         $options,      '/api/v1/me', []];
        yield 'dynamic' => ['/api/v{version}/me', $options,      '/api/v3/me', ['version' => '3']];
        // @codingStandardsIgnoreEnd
    }
}
