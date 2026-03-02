<?php

namespace Audentio\LaravelGraphQL\Rebing\GraphQL;

use Audentio\LaravelGraphQL\Utils\ServerTimingUtil;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Rebing\GraphQL\GraphQL as BaseGraphQL;

class GraphQL extends BaseGraphQL
{
    private static GraphQL $instance;
    private static array $dynamicObjectTypes = [];
    private static array $dynamicInputObjectTypes = [];

    private static bool $called = false;

    public static function newObjectType(array $config): ObjectType
    {
        if (!config('graphql.lazyload_types')) {
            return new ObjectType($config);
        }

        if (!isset(static::$dynamicObjectTypes[$config['name']])) {
            static::$dynamicObjectTypes[$config['name']] = new ObjectType($config);
            static::$instance->addType(static::$dynamicObjectTypes[$config['name']]);
        }

        return static::$dynamicObjectTypes[$config['name']];
    }

    public static function newInputObjectType(array $config): InputObjectType
    {
        if (!config('graphql.lazyload_types')) {
            return new InputObjectType($config);
        }

        if (!isset(static::$dynamicInputObjectTypes[$config['name']])) {
            static::$dynamicInputObjectTypes[$config['name']] = new InputObjectType($config);
            static::$instance->addType(static::$dynamicInputObjectTypes[$config['name']]);
        }

        return static::$dynamicInputObjectTypes[$config['name']];
    }

    public function addType($class, string $name = null): void
    {
        parent::addType($class, $name);

        if (config('graphql.lazyload_types')) {
            if ($class instanceof ObjectType || $class instanceof InputObjectType) {
                if (!$name) {
                    $name = $class->name;
                }

                $this->typesInstances[$name] = $class;
            }
        }
    }

    public function type(string $name, bool $fresh = false): Type
    {
        $modifiers = [];

        while (true) {
            if (\Safe\preg_match('/^(.+)!$/', $name, $matches)) {
                $name = $matches[1];
                array_unshift($modifiers, 'nonNull');
            } elseif (\Safe\preg_match('/^\[(.+)]$/', $name, $matches)) {
                $name = $matches[1];
                array_unshift($modifiers, 'listOf');
            } else {
                break;
            }
        }

        $suffixedType = $name . 'Type';
        if (!array_key_exists($name, $this->types) && array_key_exists($suffixedType, $this->types)) {
            $name = $suffixedType;
        }

        return parent::type($name, $fresh);
    }

    public function schema(?string $schemaName = null, bool $forceRefresh = false): Schema
    {
        $suffixKey = substr(md5(rand(0,1000)), 0, 5);
        $timingKey = 'GQL:schema:' . $suffixKey;
        ServerTimingUtil::start($timingKey);
        $return = parent::schema($schemaName);
        ServerTimingUtil::stop($timingKey);

        return $return;
    }

    public function __construct(Container $app, Repository $config)
    {
        parent::__construct($app, $config);

        static::$instance = $this;
    }
}
