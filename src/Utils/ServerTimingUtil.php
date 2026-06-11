<?php

namespace Audentio\LaravelGraphQL\Utils;

use BeyondCode\ServerTiming\Facades\ServerTiming;

class ServerTimingUtil
{
    public static function start(string $key): void
    {
        if (!self::enabled()) {
            return;
        }

        ServerTiming::start($key);
    }

    public static function stop(string $key): void
    {
        if (!self::enabled()) {
            return;
        }

        ServerTiming::stop($key);
    }

    public static function setDuration(string $key, float $duration): void
    {
        if (!self::enabled()) {
            return;
        }

        ServerTiming::setDuration($key, $duration);
    }

    /**
     * Server timing is resolved per field during GraphQL resolution, which can mean thousands of
     * Stopwatch start/stop cycles on a single collection request. When timing is disabled (e.g.
     * production, where the timing middleware is bypassed and the data is never emitted) those
     * cycles are pure overhead, so skip them entirely. Defaults to enabled when the host app has
     * no `timing.enabled` config, preserving previous behaviour for consumers without the config.
     */
    private static function enabled(): bool
    {
        return class_exists(ServerTiming::class) && config('timing.enabled', true);
    }
}
