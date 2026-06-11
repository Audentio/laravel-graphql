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

            // Fast path: with no middleware the Pipeline is just a pass-through, so skip building
            // it (and resolving middleware instances) on every resolved field. This runs once per
            // field, so on a large collection it is invoked thousands of times per request.
            if (empty($middleware)) {
                return $this->resolveWithTiming($resolver, $root, $arguments);
            }

            return app()->make(Pipeline::class)
                ->send(array_merge([$this], $arguments))
                ->through($middleware)
                ->via('resolve')
                ->then(function ($arguments) use ($middleware, $resolver, $root) {
                    $arguments = \array_slice($arguments, 1);

                    $result = $this->resolveWithTiming($resolver, $root, $arguments);

                    foreach ($middleware as $name) {
                        /** @var Middleware $instance */
                        $instance = app()->make($name);

                        if (method_exists($instance, 'terminate')) {
                            app()->terminating(function () use ($arguments, $instance, $result): void {
                                $instance->terminate($this, ...$arguments, ...[$result]);
                            });
                        }
                    }

                    return $result;
                });
        };
    }

    /**
     * Resolve a field, wrapping it with server-timing tracking and the post-result hook.
     *
     * @param array $arguments The resolver arguments after $root, i.e. [args, context, ResolveInfo].
     */
    private function resolveWithTiming(\Closure $resolver, $root, array $arguments): mixed
    {
        /** @var ResolveInfo $info */
        $info = $arguments[2];
        $key = 'GQL:' . substr($info->parentType->name, 0, 1) . ':' . ($info->path[0] ?? 'undefined');

        ServerTimingUtil::start($key);
        $result = $resolver($root, ...$arguments);
        ServerTimingUtil::stop($key);
        $this->postResultHook($result);

        return $result;
    }

    protected function postResultHook(mixed &$result): void
    {
        // Override this method to do something with the result
    }
}
