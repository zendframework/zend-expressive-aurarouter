<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-aurarouter for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-aurarouter/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Router;

use Aura\Router\Generator as AuraGenerator;
use Aura\Router\Map as AuraMap;
use Aura\Router\Matcher as AuraMatcher;
use Aura\Router\Route as AuraRoute;
use Aura\Router\RouterContainer as AuraRouterContainer;
use Fig\Http\Message\RequestMethodInterface as RequestMethod;
use PHPUnit_Framework_TestCase as TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Zend\Expressive\Router\AuraRouter;
use Zend\Expressive\Router\Route;
use Zend\Expressive\Router\RouteResult;

class AuraRouterTest extends TestCase
{
    /** @var AuraRouterContainer */
    private $auraRouterContainer;

    /** @var AuraRoute */
    private $auraRoute;

    /** @var AuraMap */
    private $auraMap;

    /** @var AuraMatcher */
    private $auraMatcher;

    /** @var AuraGenerator */
    private $auraGenerator;

    public function setUp()
    {
        $this->auraRouterContainer = $this->prophesize(AuraRouterContainer::class);
        $this->auraRoute           = $this->prophesize(AuraRoute::class);
        $this->auraMap             = $this->prophesize(AuraMap::class);
        $this->auraMatcher         = $this->prophesize(AuraMatcher::class);
        $this->auraGenerator       = $this->prophesize(AuraGenerator::class);

        $this->auraRouterContainer->getMap()->willReturn($this->auraMap->reveal());
        $this->auraRouterContainer->getMatcher()->willReturn($this->auraMatcher->reveal());
        $this->auraRouterContainer->getGenerator()->willReturn($this->auraGenerator->reveal());
    }

    public function getRouter()
    {
        return new AuraRouter($this->auraRouterContainer->reveal());
    }

    public function testAddingRouteAggregatesRoute()
    {
        $route  = new Route('/foo', 'foo', [RequestMethod::METHOD_GET]);
        $router = $this->getRouter();
        $router->addRoute($route);
        $this->assertAttributeContains($route, 'routesToInject', $router);

        return $router;
    }

    /**
     * @depends testAddingRouteAggregatesRoute
     */
    public function testMatchingInjectsRouteIntoAuraRouter()
    {
        $route  = new Route('/foo', 'foo', [RequestMethod::METHOD_GET]);
        $router = $this->getRouter();
        $router->addRoute($route);

        $auraRoute = new AuraRoute();
        $auraRoute->name($route->getName());
        $auraRoute->path($route->getPath());
        $auraRoute->handler($route->getMiddleware());
        $auraRoute->allows($route->getAllowedMethods());

        $uri = $this->prophesize(UriInterface::class);
        $uri->getPath()->willReturn('/foo');

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->will(function () use ($uri) {
            return $uri->reveal();
        });
        $request->getMethod()->willReturn(RequestMethod::METHOD_GET);
        $request->getServerParams()->willReturn([]);

        $this->auraMap->addRoute($auraRoute)->shouldBeCalled();
        $this->auraMatcher->match($request)->willReturn(false);
        $this->auraMatcher->getFailedRoute()->willReturn(null);

        $router->match($request->reveal());
    }

    /**
     * @depends testAddingRouteAggregatesRoute
     */
    public function testUriGenerationInjectsRouteIntoAuraRouter()
    {
        $route  = new Route('/foo', 'foo', [RequestMethod::METHOD_GET]);
        $router = $this->getRouter();
        $router->addRoute($route);

        $auraRoute = new AuraRoute();
        $auraRoute->name($route->getName());
        $auraRoute->path($route->getPath());
        $auraRoute->handler($route->getMiddleware());
        $auraRoute->allows($route->getAllowedMethods());

        $this->auraMap->addRoute($auraRoute)->shouldBeCalled();
        $this->auraGenerator->generateRaw('foo', [])->shouldBeCalled()->willReturn('/foo');

        $this->assertEquals('/foo', $router->generateUri('foo'));
    }

    public function testCanSpecifyAuraRouteTokensViaRouteOptions()
    {
        $route = new Route('/foo', 'foo', [RequestMethod::METHOD_GET]);
        $route->setOptions(['tokens' => ['foo' => 'bar']]);

        $auraRoute = new AuraRoute();
        $auraRoute->name('/foo^GET');
        $auraRoute->path('/foo');
        $auraRoute->handler($route->getMiddleware());
        $auraRoute->allows($route->getAllowedMethods());
        $auraRoute->tokens($route->getOptions()['tokens']);

        $this->auraMap->addRoute($auraRoute)->shouldBeCalled();
        // Injection happens when match() or generateUri() are called
        $this->auraGenerator->generateRaw('foo', [])->shouldBeCalled()->willReturn('/foo');

        $router = $this->getRouter();
        $router->addRoute($route);
        $router->generateUri('foo');
    }

    public function testCanSpecifyAuraRouteValuesViaRouteOptions()
    {
        $route = new Route('/foo', 'foo', [RequestMethod::METHOD_GET]);
        $route->setOptions(['values' => ['foo' => 'bar']]);

        $auraRoute = new AuraRoute();
        $auraRoute->name($route->getName());
        $auraRoute->path($route->getPath());
        $auraRoute->handler($route->getMiddleware());
        $auraRoute->allows($route->getAllowedMethods());
        $auraRoute->defaults($route->getOptions()['values']);

        $this->auraMap->addRoute($auraRoute)->shouldBeCalled();
        // Injection happens when match() or generateUri() are called
        $this->auraGenerator->generateRaw('foo', [])->shouldBeCalled()->willReturn('/foo');

        $router = $this->getRouter();
        $router->addRoute($route);
        $router->generateUri('foo');
    }

    public function testCanSpecifyAuraRouteWildcardViaRouteOptions()
    {
        $route = new Route('/foo', 'foo', [RequestMethod::METHOD_GET]);
        $route->setOptions(['wildcard' => 'card']);

        $auraRoute = new AuraRoute();
        $auraRoute->name($route->getName());
        $auraRoute->path($route->getPath());
        $auraRoute->handler($route->getMiddleware());
        $auraRoute->allows($route->getAllowedMethods());
        $auraRoute->wildcard($route->getOptions()['wildcard']);

        $this->auraMap->addRoute($auraRoute)->shouldBeCalled();

        // Injection happens when match() or generateUri() are called
        $this->auraGenerator->generateRaw('foo', [])->willReturn('/foo');

        $router = $this->getRouter();
        $router->addRoute($route);
        $router->generateUri('foo');
    }

    public function testMatchingRouteShouldReturnSuccessfulRouteResult()
    {
        $uri = $this->prophesize(UriInterface::class);
        $uri->getPath()->willReturn('/foo');

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn($uri);
        $request->getMethod()->willReturn(RequestMethod::METHOD_GET);
        $request->getServerParams()->willReturn([]);

        $auraRoute = new AuraRoute();
        $auraRoute->name('/foo');
        $auraRoute->path('/foo');
        $auraRoute->handler('foo');
        $auraRoute->allows([RequestMethod::METHOD_GET]);
        $auraRoute->attributes([
            'action' => 'foo',
            'bar'    => 'baz',
        ]);

        $this->auraMatcher->match($request)->willReturn($auraRoute);

        $router = $this->getRouter();
        $router->addRoute(new Route('/foo', 'foo', [RequestMethod::METHOD_GET], '/foo'));
        $result = $router->match($request->reveal());
        $this->assertInstanceOf(RouteResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('/foo', $result->getMatchedRouteName());
        $this->assertEquals('foo', $result->getMatchedMiddleware());
        $this->assertSame([
            'action' => 'foo',
            'bar'    => 'baz',
        ], $result->getMatchedParams());
    }

    public function testMatchFailureDueToHttpMethodReturnsRouteResultWithAllowedMethods()
    {
        $route = new Route('/foo', 'foo', [RequestMethod::METHOD_POST]);

        $uri = $this->prophesize(UriInterface::class);
        $uri->getPath()->willReturn('/foo');

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn($uri);
        $request->getMethod()->willReturn(RequestMethod::METHOD_GET);
        $request->getServerParams()->willReturn([]);

        $this->auraMatcher->match($request)->willReturn(false);

        $auraRoute = new AuraRoute();
        $auraRoute->allows([RequestMethod::METHOD_POST]);

        $this->auraMatcher->getFailedRoute()->willReturn($auraRoute);

        $router = $this->getRouter();
        $result = $router->match($request->reveal());
        $this->assertInstanceOf(RouteResult::class, $result);
        $this->assertTrue($result->isFailure());
        $this->assertSame([RequestMethod::METHOD_POST], $result->getAllowedMethods());
    }

    public function testMatchFailureNotDueToHttpMethodReturnsGenericRouteFailureResult()
    {
        $route = new Route('/foo', 'foo', [RequestMethod::METHOD_GET]);

        $uri = $this->prophesize(UriInterface::class);
        $uri->getPath()->willReturn('/bar');

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn($uri);
        $request->getMethod()->willReturn(RequestMethod::METHOD_PUT);
        $request->getServerParams()->willReturn([]);

        $this->auraMatcher->match($request)->willReturn(false);

        $auraRoute = new AuraRoute();

        $this->auraMatcher->getFailedRoute()->willReturn($auraRoute);

        $router = $this->getRouter();
        $result = $router->match($request->reveal());
        $this->assertInstanceOf(RouteResult::class, $result);
        $this->assertTrue($result->isFailure());
        $this->assertFalse($result->isMethodFailure());
        $this->assertSame([], $result->getAllowedMethods());
    }

    /**
     * @group 53
     */
    public function testCanGenerateUriFromRoutes()
    {
        $router = new AuraRouter();
        $route1 = new Route('/foo', 'foo', [RequestMethod::METHOD_POST], 'foo-create');
        $route2 = new Route('/foo', 'foo', [RequestMethod::METHOD_GET], 'foo-list');
        $route3 = new Route('/foo/{id}', 'foo', [RequestMethod::METHOD_GET], 'foo');
        $route4 = new Route('/bar/{baz}', 'bar', Route::HTTP_METHOD_ANY, 'bar');

        $router->addRoute($route1);
        $router->addRoute($route2);
        $router->addRoute($route3);
        $router->addRoute($route4);

        $this->assertEquals('/foo', $router->generateUri('foo-create'));
        $this->assertEquals('/foo', $router->generateUri('foo-list'));
        $this->assertEquals('/foo/bar', $router->generateUri('foo', ['id' => 'bar']));
        $this->assertEquals('/bar/BAZ', $router->generateUri('bar', ['baz' => 'BAZ']));
    }

    /**
     * @group 85
     */
    public function testReturns404ResultIfAuraReturnsNullForFailedRoute()
    {
        $route = new Route('/foo', 'foo', [RequestMethod::METHOD_GET]);

        $uri = $this->prophesize(UriInterface::class);
        $uri->getPath()->willReturn('/bar');

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn($uri);
        $request->getMethod()->willReturn(RequestMethod::METHOD_PUT);
        $request->getServerParams()->willReturn([]);

        $this->auraMatcher->match($request)->willReturn(false);
        $this->auraMatcher->getFailedRoute()->willReturn(null);

        $router = $this->getRouter();
        $result = $router->match($request->reveal());
        $this->assertInstanceOf(RouteResult::class, $result);
        $this->assertTrue($result->isFailure());
        $this->assertFalse($result->isMethodFailure());
        $this->assertSame([], $result->getAllowedMethods());
    }

    /**
     * @group 149
     */
    public function testGeneratedUriIsNotEncoded()
    {
        $router = new AuraRouter();
        $route  = new Route('/foo/{id}', 'foo', [RequestMethod::METHOD_GET], 'foo');

        $router->addRoute($route);

        $this->assertEquals(
            '/foo/bar is not encoded',
            $router->generateUri('foo', ['id' => 'bar is not encoded'])
        );
    }

    public function testSuccessfulRouteResultComposesMatchedRoute()
    {
        $route = new Route('/foo', 'foo', [RequestMethod::METHOD_GET]);

        $uri = $this->prophesize(UriInterface::class);
        $uri->getPath()->willReturn('/foo');

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn($uri);
        $request->getMethod()->willReturn(RequestMethod::METHOD_GET);
        $request->getServerParams()->willReturn([]);

        $router = new AuraRouter();
        $router->addRoute($route);

        $result = $router->match($request->reveal());
        $this->assertInstanceOf(RouteResult::class, $result);
        $this->assertTrue($result->isSuccess());

        $matched = $result->getMatchedRoute();
        $this->assertSame($route, $matched);
    }

    public function implicitMethods()
    {
        return [
            'head'    => [RequestMethod::METHOD_HEAD],
            'options' => [RequestMethod::METHOD_OPTIONS],
        ];
    }

    /**
     * @dataProvider implicitMethods
     */
    public function testHeadAndOptionsAlwaysResultInRoutingSuccessIfPathMatches($method)
    {
        $uri = $this->prophesize(UriInterface::class);
        $uri->getPath()->willReturn('/foo');

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn($uri);
        $request->getMethod()->willReturn($method);
        $request->getServerParams()->willReturn([]);

        // Not mocking the router container or Aura\Route; this particular test
        // is testing how the parts integrate.
        $router = new AuraRouter();
        $route  = new Route('/foo', 'foo', [RequestMethod::METHOD_POST]);
        $router->addRoute($route);

        $result = $router->match($request->reveal());
        $this->assertInstanceOf(RouteResult::class, $result);
        $this->assertTrue($result->isSuccess());

        $matched = $result->getMatchedRoute();
        $this->assertSame($route, $matched);
    }
}
