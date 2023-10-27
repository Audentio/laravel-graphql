<?php

namespace Audentio\LaravelGraphQL\GraphQL\Support\Traits;

use Audentio\LaravelGraphQL\Utils\ServerTimingUtil;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Pipeline\Pipeline;
use Rebing\GraphQL\Support\Middleware;

trait CustomResolveHandlingTrait
{

    protected function getResolver(): ?\Closure
    {
        $resolver = $this->originalResolver();

        if (!$resolver) {
            return null;
        }

        return function ($root, ...$arguments) use ($resolver) {
            $middleware = $this->getMiddleware();

            return app()->make(Pipeline::class)
                ->send(array_merge([$this], $arguments))
                ->through($middleware)
                ->via('resolve')
                ->then(function ($arguments) use ($middleware, $resolver, $root) {
                    // CUSTOM: Start time tracking
                    /** @var ResolveInfo $info */
                    $info = $arguments[3];
                    $key = 'GraphQL:' . $info->parentType->name . ':' . $info->path[0] ?? 'undefined';
                    ServerTimingUtil::start($key);
                    // END CUSTOM: Start time tracking
                    $result = $resolver($root, ...\array_slice($arguments, 1));
                    // CUSTOM: End time tracking
                    ServerTimingUtil::stop($key);
                    $this->postResultHook($result);
                    // END CUSTOM: End time tracking

                    foreach ($middleware as $name) {
                        /** @var Middleware $instance */
                        $instance = app()->make($name);

                        if (method_exists($instance, 'terminate')) {
                            app()->terminating(function () use ($arguments, $instance, $result): void {
                                $instance->terminate($this, ...\array_slice($arguments, 1), ...[$result]);
                            });
                        }
                    }

                    return $result;
                });
        };
    }

    protected function postResultHook(mixed &$result): void
    {
        // Override this method to do something with the result
    }
}