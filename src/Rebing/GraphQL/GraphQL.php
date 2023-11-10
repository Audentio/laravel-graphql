<?php

namespace Audentio\LaravelGraphQL\Rebing\GraphQL;

use Audentio\LaravelGraphQL\Utils\ServerTimingUtil;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Introspection;
use GraphQL\Type\Schema;
use Illuminate\Support\Facades\Cache;
use Rebing\GraphQL\GraphQL as BaseGraphQL;
use Audentio\LaravelGraphQL\Opis\Closure\SerializableClosure;
use Rebing\GraphQL\Support\Field;

class GraphQL extends BaseGraphQL
{
    public static function buildLaravelSchemaCache(?string $schemaName = null, ?int $duration = null): void
    {
        $instance = new self(app(), config());
        $schemaName = $schemaName ?? config()->get('graphql.default_schema', 'default');

        $schema = $instance->schema($schemaName, true);
        $instance->storeSchemaInLaravelCache($schemaName, $schema, $duration);
    }

    public static function clearLaravelSchemaCache(?string $schemaName = null): void
    {
        $instance = new self(app(), config());
        $schemaName = $schemaName ?? config()->get('graphql.default_schema', 'default');
        $instance->clearSchemaLaravelCache($schemaName);
    }

    protected function buildObjectTypeFromFields(array $fields, array $opts = []): ObjectType
    {
        $typeFields = [];

        foreach ($fields as $name => $field) {
            if (\is_string($field)) {
                $field = $this->app->make($field);
                /** @var Field $field */
                $field = $field->toArray();

                // START CUSTOM CODE FOR DYNAMIC TYPES
                $this->iterateFieldForDynamicTypes($field);
                // END CUSTOM CODE FOR DYNAMIC TYPES
            }
            $name = is_numeric($name) ? $field['name'] : $name;
            $field['name'] = $name;
            $typeFields[$name] = $field;
        }

        return new ObjectType(array_merge([
            'fields' => $typeFields,
        ], $opts));
    }

    protected function iterateFieldForDynamicTypes(array $field): void
    {
        $fieldType = $field['type'] ?? null;
        if (!empty($field['args'])) {
            $this->iterateArgsForDynamicTypes($field['args']);
        }
        if ($fieldType instanceof ObjectType) {
            $this->iterateObjectTypeForDynamicTypes($fieldType);
        }
    }

    protected function iterateObjectTypeForDynamicTypes(ObjectType $type): void
    {
        if (!array_key_exists($type->name, $this->types)) {
            $this->addType($type);
        }
    }

    protected function iterateArgsForDynamicTypes(array $args): void
    {
        foreach ($args as $key => $arg) {
            if (!is_array($arg)) {
                continue;
            }
            $type = $arg['type'];
            if ($type instanceof InputObjectType) {
                if (!array_key_exists($type->name, $this->types)) {
                    $this->addType($type);
                }
            }
        }
    }

    public function addType($class, string $name = null): void
    {
        parent::addType($class, $name);

        if ($class instanceof ObjectType || $class instanceof InputObjectType) {
            if (!$name) {
                $name = $class->name;
            }

            $this->typesInstances[$name] = $class;
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

        // Cache is disabled as performance is much worse.
//        if (!$this->config->get('audentioGraphQL.enableSchemaCache')) {
//            ServerTimingUtil::start($timingKey);
//            $return = parent::schema($schemaName);
//            ServerTimingUtil::stop($timingKey);
//
//            return $return;
//        }
//
//        $schemaName = $schemaName ?? $this->config->get('graphql.default_schema', 'default');
//
//        if (isset($this->schemas[$schemaName])) {
//            ServerTimingUtil::start($timingKey);
//            $return = $this->schemas[$schemaName];
//            ServerTimingUtil::stop($timingKey);
//            return $return;
//        }
//
//        ServerTimingUtil::start($timingKey);
//        if (!$forceRefresh && Cache::has('gqlSchema.' . $schemaName)) {
//            $schemaConfig = static::getNormalizedSchemaConfiguration($schemaName);
//            $schema = $this->buildSchemaFromLaravelCache($schemaName, $schemaConfig);
//        } else {
//            $schema = parent::schema($schemaName);
//            $this->storeSchemaInLaravelCache($schemaName, $schema, $this->config->get('audentioGraphQL.schemaCacheTTL'));
//        }
//        ServerTimingUtil::stop($timingKey);
//
//        return $schema;

        return $return;
    }

    public function storeSchemaInLaravelCache(string $schemaName, Schema $schema, ?int $duration = 300)
    {
        $schemaConfig = $schema->getConfig();
        $prop = new \ReflectionProperty($schemaConfig, 'types');
        $prop->setAccessible(true);
        $prop->setValue($schemaConfig, null);

        $prop = new \ReflectionProperty($schema, 'config');
        $prop->setAccessible(true);
        $prop->setValue($schema, $schemaConfig);

        $cache = [
            'schema' => $schema,
            'types' => $this->types,
            'typeInstances' => $this->typesInstances
        ];

        $cacheContent = \Audentio\OpisClosureWrapper\serialize($cache);
        if (config('audentioGraphQL.schemaCacheStorageMechanism') == 'file') {
            file_put_contents($this->getSchemaFileCacheName($schemaName), $cacheContent);
        } else {
            if ($duration === null) {
                Cache::forever('gqlSchema.' . $schemaName, $cacheContent);
            } else {
                Cache::put('gqlSchema.' . $schemaName, $cacheContent);
            }
        }
    }

    protected function getSchemaFileCacheName(string $schemaName): string
    {
        $cachePath = config('audentioGraphQL.schemaFileCachePath');
        if (!$cachePath) {
            $cachePath = storage_path();
        }
        return rtrim($cachePath, '/') . '/gqlSchema-' . $schemaName . '.dat';
    }

    protected function clearSchemaLaravelCache(string $schemaName): void
    {
        if (config('audentioGraphQL.schemaCacheStorageMechanism') == 'file') {
            unlink($this->getSchemaFileCacheName($schemaName));
        } else {
            Cache::forget('gqlSchema.' . $schemaName);
        }
    }

    protected function buildSchemaFromLaravelCache(string $schemaName, array $schemaConfig)
    {
        if (config('audentioGraphQL.schemaCacheStorageMechanism') == 'file') {
            $cacheContent = file_get_contents($this->getSchemaFileCacheName($schemaName));
        } else {
            $cacheContent = Cache::get('gqlSchema.' . $schemaName);
        }

        /** @var Schema $schema */
        $cache = \Audentio\OpisClosureWrapper\unserialize($cacheContent);
        $schema = $cache['schema'];

        $this->clearTypeInstances();
        $this->clearTypes();
        $this->types = $cache['types'];
        $this->typesInstances = $cache['typeInstances'];

        $config = $schema->getConfig();

        $config->setTypes(function () {
            $types = [];

            foreach ($this->getTypes() as $name => $type) {
                $types[] = $this->type($name);
            }

            return $types;
        });

        foreach ($schemaConfig['query'] as $query) {
            new $query;
        }

        $prop = new \ReflectionProperty($schema, 'config');
        $prop->setAccessible(true);
        $prop->setValue($schema, $config);

        $prop = new \ReflectionProperty($schema, 'resolvedTypes');
        $prop->setAccessible(true);
        $resolvedTypes = $prop->getValue($schema);

        $standardTypes = array_intersect_key($resolvedTypes, array_flip(['ID', 'String', 'Int', 'Float', 'Boolean']));
        Type::overrideStandardTypes($standardTypes);

        $class = new \ReflectionClass(Introspection::class);
        $map = array_intersect_key($resolvedTypes, array_flip(['__Schema', '__Type', '__Directive', '__Field', '__InputValue', '__EnumValue', '__TypeKind', '__DirectiveLocation']));
        $class->setStaticPropertyValue('map', $map);

        $prop->setValue($schema, $resolvedTypes);

        return $schema;
    }
}
