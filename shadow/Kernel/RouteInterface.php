<?php

/**
 * MimimalCMS v1 APIs
 * 
 * @author mimimiku778 <0203.sub@gmail.com>
 * @license https://github.com/mimimiku778/MimimalCMS/blob/master/LICENSE.md
 */

namespace Shadow\Kernel;

use Shadow\Kernel\RouteClasses\RouteSecondInterface;
use Shadow\Kernel\RouteClasses\RouteMiddlewareGroupInterface;

interface RouteInterface
{
    /**
     * Adds a path.
     * 
     * @param string $path The first argument must be a string representing the path.  
     * 
     * * **Optional:** If you want to define the controller(s) explicitly instead of determining them dynamically from the path,
     *     pass them as additional arguments after the path string.  
     *     `['explicitControllerClassName ', 'methodName'] + ['optionalRequestMethod']`  
     * 
     * * **Example:** `path('mypage')` `path('mypage/post@POST@DELETE')` `path('mypage/posts/{post_id}')`  
     *     `path('mypage', [Mypage::class, 'index'])`  
     *     `path('posts/likes/{post_id}@POST@DELETE', [PostLikes::class, 'like', 'POST'], [PostLikes::class, 'disLike', 'DELETE'])`
     * 
     * @return RouteSecondInterface The instance of the Route class, to allow for method chaining.
     * 
     * @throws \InvalidArgumentException
     */
    public static function path(string|array ...$path): RouteSecondInterface;

    /**
     * Runs the application.
     *
     * @param string ...$middlewareName The names of the middleware to run for all routing.
     * @return void
     */
    public static function run();

    /**
     * Adds the specified middleware group to all routes chained after this method.
     *
     * @param string ...$name The names of the middleware group to add.
     *
     * @return RouteMiddlewareGroupInterface The middleware group instance, to allow for method chaining.
     */
    public static function middlewareGroup(string ...$name): RouteMiddlewareGroupInterface;
}