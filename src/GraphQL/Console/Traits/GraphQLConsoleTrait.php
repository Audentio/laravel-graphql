<?php

namespace Audentio\LaravelGraphQL\GraphQL\Console\Traits;

use Audentio\LaravelBase\Traits\ExtendConsoleCommandTrait;

trait GraphQLConsoleTrait
{
    use ExtendConsoleCommandTrait;

    protected function getDataType($name, $suffix)
    {
        preg_match('/([^\\\]+)$/', $name, $matches);

        $name = substr($matches[1], 0, (-1 * strlen($suffix)));

        if ($prefix = config('audentioGraphQL.namePrefix')) {
            $name = substr($name, strlen($prefix));
        }

        return $name;
    }

    protected function replaceTypeClass($stub)
    {
        if (class_exists('App\GraphQL\Definitions\Type')) {
            $stub = str_replace('Audentio\LaravelGraphQL\GraphQL\Definitions\Type', 'App\GraphQL\Definitions\Type', $stub);
        }

        return $stub;
    }

    public function normalizeTypeName(string $name, ?string $suffix = null, ?string $extraPrefix = null): string
    {
        if ($suffix) {
            $name = $this->suffixCommandClass($name, $suffix);
        }

        if ($extraPrefix && \Str::startsWith($name, $extraPrefix)) {
            $name = substr($name, strlen($extraPrefix));
        }

        $prefix = config('audentioGraphQL.namePrefix') ?? '';
        if ($prefix && \Str::startsWith($name, $prefix)) {
            $name = substr($name, strlen($prefix));
        }
        if ($extraPrefix) {
            $prefix = $extraPrefix . $prefix;
        }

        if ($prefix) {
            $name = $this->prefixCommandClass($name, $prefix);
        }

        return $name;
    }
}