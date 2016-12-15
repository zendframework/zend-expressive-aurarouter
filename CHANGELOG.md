# Changelog

All notable changes to this project will be documented in this file, in reverse chronological order by release.

## 1.1.3 - 2016-12-15

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#19](https://github.com/zendframework/zend-expressive-aurarouter/pull/19)
  ensures that when `HTTP_METHOD_ANY` is specified for the route, any HTTP
  method results in a successful routing result.

## 1.1.2 - 2016-12-15

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#18](https://github.com/zendframework/zend-expressive-aurarouter/pull/18)
  fixes what happens when a route specifies no valid HTTP methods. Aura.Router
  always treats these as routing matches, but they should not be. This patch
  updates the implementation to determine if the matched route specifies any
  valid HTTP methods; if not, it treats it as a potential failed result
  (potential only, as HEAD and OPTIONS requests will be implicitly successful).

## 1.1.1 - 2016-12-14

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#17](https://github.com/zendframework/zend-expressive-aurarouter/pull/17)
  fixes how the router reports a failure when a root path matches a portion of
  the request path. With the upgrade to Aura.Router v3, these were incorrectly
  being reported as 405 errors instead of 404; they are not reported correctly.

- [#17](https://github.com/zendframework/zend-expressive-aurarouter/pull/17)
  fixes how the router returns allowed methods when a 405 occurs; previously, it
  would return the allowed methods from the first route matching the path; it
  now returns the aggregated set of methods supported from all routes with the
  same path.

## 1.1.0 - 2016-12-14

### Added

- [#7](https://github.com/zendframework/zend-expressive-aurarouter/pull/7) adds
  support for specifying wildcard segments via the `wildcard` option passed to a
  route:

  ```php
  $app->get('/foo', $middleware, 'foo')
      ->setOptions(['wildcard' => 'captured']); // captures to "captured" param
  ```

### Changed

- [#11](https://github.com/zendframework/zend-expressive-aurarouter/pull/11)
  updates the component to use the Aura.Router version 3 series instead of the
  version 2 series. The exposed API remains the same.

- [#15](https://github.com/zendframework/zend-expressive-aurarouter/pull/15)
  updates the router to populate the returned `RouteResult` with the associated
  `Zend\Expressive\Router\Route` instance on a successful route match.

- [#15](https://github.com/zendframework/zend-expressive-aurarouter/pull/15)
  updates the router to always honor `HEAD` and `OPTIONS` requests when a path
  matches. Dispatchers will need to check the `Route` composed in the
  `RouteResult` to determine if matches against these methods were explicit or
  implicit (using `Route::implicitHead()` and `Route::implicitOptions()`).

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 1.0.0 - 2015-12-07

First stable release.

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 0.3.0 - 2015-12-02

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Updated to use [zendframework/zend-expressive-router](https://github.com/zendframework/zend-expressive-router)
  instead of zendframework/zend-expressive.

## 0.2.0 - 2015-10-20

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Updated to Expressive RC builds.

## 0.1.0 - 2015-10-10

Initial release.

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.
