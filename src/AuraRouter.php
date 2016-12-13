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
     * Aura router
     *
     * @var Router
     */
    private $router;

    /**
     * Store the path and the HTTP methods allowed
     *
     * @var array
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

        return $this->marshalMatchedRoute($route);
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

        if ($failedRoute->allows
            && !in_array($request->getMethod(), $failedRoute->allows)
        ) {
            return RouteResult::fromRouteFailure($failedRoute->allows);
        }

        // Check to see if the route regex matched; if so, and we have an entry
        // for the path, register a 405.
        list($path) = explode('^', $failedRoute->name);
        if (array_key_exists($path, $this->routes)
        ) {
            return RouteResult::fromRouteFailure($this->routes[$path]);
        }

        return RouteResult::fromRouteFailure();
    }

    /**
     * Marshals a route result based on the matched AuraRoute.
     *
     * Note: no actual typehint is provided here; Aura Route instances provide
     * property overloading, which is difficult to mock for testing; we simply
     * assume an object at this point.
     *
     * @param AuraRoute $route
     *
     * @return RouteResult
     */
    private function marshalMatchedRoute($route)
    {
        return RouteResult::fromRouteMatch(
            $route->name,
            $route->handler,
            $route->attributes
        );
    }

    /**
     * Loops through any un-injected routes and injects them into the Aura.Router instance.
     */
    private function injectRoutes()
    {
        foreach ($this->routesToInject as $index => $route) {
            $this->injectRoute($route);
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
            }
        }

        $allowedMethods = $route->getAllowedMethods();
        if (Route::HTTP_METHOD_ANY === $allowedMethods) {
            // Add route here for improved testability
            $this->router->getMap()->addRoute($auraRoute);

            return;
        }

        $auraRoute->allows($allowedMethods);
        // Add route here for improved testability
        $this->router->getMap()->addRoute($auraRoute);

        if (array_key_exists($path, $this->routes)) {
            $allowedMethods = array_merge($this->routes[$path], $allowedMethods);
        }
        $this->routes[$path] = $allowedMethods;
    }
}
