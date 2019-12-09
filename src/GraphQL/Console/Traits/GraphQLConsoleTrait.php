<?php

namespace Audentio\LaravelGraphQL\GraphQL\Console\Traits;

trait GraphQLConsoleTrait
{
    protected function getDataType($name, $suffix)
    {
        preg_match('/([^\\\]+)$/', $name, $matches);

        return substr($matches[1], 0, (-1 * strlen($suffix)));
    }

    protected function replaceTypeClass($stub)
    {
        if (class_exists('App\GraphQL\Definitions\Type')) {
            $stub = str_replace('Audentio\LaravelGraphQL\GraphQL\Definitions\Type', 'App\GraphQL\Definitions\Type', $stub);
        }

        return $stub;
    }
}