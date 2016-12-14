<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Router;

use Aura\Router\Route as AuraRoute;
use Aura\Router\RouterContainer as Router;
use Fig\Http\Message\RequestMethodInterface as RequestMethod;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Router implementation bridging the Aura.Router.
 *
 * Adds routes to the Aura.Router, using the path as the name, and a
 * middleware value equivalent to the middleware in the Route instance.
 *
 * If HTTP methods are defined (and not the wildcard), they are imploded
 * with a pipe symbol and added as server REQUEST_METHOD criteria.
 *
 * If tokens or values are present in the options array, they are also
 * added to the router.
 */
class AuraRouter implements RouterInterface
{
    /**
     * Implicit HTTP methods (should work for any route)
     */
    const HTTP_METHODS_IMPLICIT = [
        RequestMethod::METHOD_HEAD,
        RequestMethod::METHOD_OPTIONS,
    ];

    /**
     * Map paths to allowed HTTP methods.
     *
     * @var array
     */
    private $pathMethodMap = [];

    /**
     * @var Router
     */
    private $router;

    /**
     * @var Route[]
     */
    private $routes = [];

    /**
     * @var Route[] Routes aggregated to inject.
     */
    private $routesToInject = [];

    /**
     * Constructor
     *
     * If no Aura.Router instance is provided, the constructor will lazy-load
     * an instance. If you need to customize the Aura.Router instance in any
     * way, you MUST inject it yourself.
     *
     * @param null|Router $router
     */
    public function __construct(Router $router = null)
    {
        if (null === $router) {
            $router = $this->createRouter();
        }

        $this->router = $router;
    }

    /**
     * @inheritDoc
     */
    public function addRoute(Route $route)
    {
        $this->routesToInject[] = $route;
    }

    /**
     * @inheritDoc
     */
    public function match(Request $request)
    {
        // Must inject routes prior to matching.
        $this->injectRoutes();

        $route = $this->router->getMatcher()->match($request);

        if (false === $route) {
            return $this->marshalFailedRoute($request);
        }

        return $this->marshalMatchedRoute($route, $request->getMethod());
    }

    /**
     * @inheritDoc
     */
    public function generateUri($name, array $substitutions = [])
    {
        // Must inject routes prior to generating URIs.
        $this->injectRoutes();

        return $this->router->getGenerator()->generateRaw($name, $substitutions);
    }

    /**
     * Create a default Aura router instance
     *
     * @return Router
     */
    private function createRouter()
    {
        return new Router();
    }

    /**
     * Marshal a RouteResult representing a route failure.
     *
     * If the route failure is due to the HTTP method, passes the allowed
     * methods when creating the result.
     *
     * @param Request $request
     *
     * @return RouteResult
     */
    private function marshalFailedRoute(Request $request)
    {
        $failedRoute = $this->router->getMatcher()->getFailedRoute();

        // Evidently, getFailedRoute() can sometimes return null; these are 404 conditions.
        if (null === $failedRoute) {
            return RouteResult::fromRouteFailure();
        }

        // Allow HEAD and OPTIONS requests if the failed route matches the path
        if (in_array($request->getMethod(), self::HTTP_METHODS_IMPLICIT, true)
            && $failedRoute->allows
        ) {
            return $this->marshalMatchedRoute($failedRoute, $request->getMethod());
        }

        if ($failedRoute->allows
            && ! in_array($request->getMethod(), $failedRoute->allows)
        ) {
            return RouteResult::fromRouteFailure($failedRoute->allows);
        }

        // Check to see if the route regex matched; if so, and we have an entry
        // for the path, register a 405.
        list($path) = explode('^', $failedRoute->name);
        if (array_key_exists($path, $this->pathMethodMap)) {
            return RouteResult::fromRouteFailure($this->pathMethodMap[$path]);
        }

        return RouteResult::fromRouteFailure();
    }

    /**
     * Marshals a route result based on the matched AuraRoute and request method.
     *
     * @param AuraRoute $auraRoute
     * @return RouteResult
     */
    private function marshalMatchedRoute(AuraRoute $auraRoute, $method)
    {
        $route = array_reduce($this->routes, function ($matched, $route) use ($auraRoute, $method) {
            if ($matched) {
                return $matched;
            }

            // We store the route name already, so we can match on that
            // as long it is non-empty.
            if (! empty($auraRoute->name)
                && $auraRoute->name === $route->getName()
            ) {
                return $route;
            }

            // Otherwise, we need to look at the path and HTTP method
            if ($auraRoute->path !== $route->getPath()) {
                return $matched;
            }

            if (! $route->allowsMethod($method)) {
                return $matched;
            }

            return $route;
        }, false);

        if (! $route) {
            // This should likely never occur, but is present for completeness.
            return RouteResult::fromRouteFailure();
        }

        return RouteResult::fromRoute($route, $auraRoute->attributes);
    }

    /**
     * Loops through any un-injected routes and injects them into the Aura.Router instance.
     */
    private function injectRoutes()
    {
        foreach ($this->routesToInject as $index => $route) {
            $this->injectRoute($route);
            $this->routes[] = $route;
            unset($this->routesToInject[$index]);
        }
    }

    /**
     * Inject a route into the underlying Aura.Router instance.
     *
     * @param Route $route
     */
    private function injectRoute(Route $route)
    {
        $path = $route->getPath();

        // Convert Route to AuraRoute
        $auraRoute = new AuraRoute();
        $auraRoute->name($route->getName());
        $auraRoute->path($path);
        $auraRoute->handler($route->getMiddleware());

        foreach ($route->getOptions() as $key => $value) {
            switch ($key) {
                case 'tokens':
                    $auraRoute->tokens($value);
                    break;
                case 'values':
                    $auraRoute->defaults($value);
                    break;
                case 'wildcard':
                    $auraRoute->wildcard($value);
                    break;
            }
        }

        $allowedMethods = $route->getAllowedMethods();
        if (Route::HTTP_METHOD_ANY === $allowedMethods) {
            // Matches any method; no special handling required
            $this->router->getMap()->addRoute($auraRoute);
            return;
        }

        // Inject allowed methods, and map them for 405 detection
        $auraRoute->allows($allowedMethods);
        $this->router->getMap()->addRoute($auraRoute);

        $this->pathMethodMap[$path] = array_key_exists($path, $this->pathMethodMap)
            ? array_merge($this->pathMethodMap[$path], $allowedMethods)
            : $allowedMethods;
    }
}
