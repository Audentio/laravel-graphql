<?php

namespace Audentio\LaravelGraphQL\Rebing\GraphQL;

use Audentio\LaravelGraphQL\Utils\ServerTimingUtil;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Introspection;
use GraphQL\Type\Schema;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Cache;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Rebing\GraphQL\GraphQL as BaseGraphQL;
use Safe\Exceptions\PcreException;

class GraphQL extends BaseGraphQL
{
    private static GraphQL $instance;
    private static array $dynamicObjectTypes = [];
    private static array $dynamicInputObjectTypes = [];

    private static bool $called = false;

    /**
     * Whether we have already triggered a full root-field resolution pass.
     * Guarded so we never call getFields() on all root types more than once
     * per GraphQL instance (i.e. per PHP-FPM worker lifetime after the first
     * schema build).
     */
    private bool $rootFieldsResolved = false;

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

    /**
     * @throws PcreException
     */
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

        // If the type is still not found it may be a dynamic type (e.g. a filter
        // InputObjectType created by FilterableQueryTrait) that only gets registered
        // as a side-effect of calling toArray() inside a per-field lazy closure.
        // This can happen when a query uses a typed variable declaration like
        // `($filter: filterNotificationsQuery)` — TypeInfo processes variable
        // definitions before traversing the selection set, so the typeLoader fires
        // before findField() has resolved that field's callable.
        //
        // Solution: trigger a one-time full resolution pass for all root-type fields.
        // After this, all dynamic types are in $this->types and the retry succeeds.
        if (!array_key_exists($name, $this->types)
            && !array_key_exists($name, Type::getStandardTypes())
            && !$this->rootFieldsResolved
        ) {
            $this->rootFieldsResolved = true;
            foreach ($this->schemas as $schema) {
                $queryType = $schema->getQueryType();
                if ($queryType) {
                    $queryType->getFields();
                }
                $mutationType = $schema->getMutationType();
                if ($mutationType) {
                    $mutationType->getFields();
                }
            }
        }

        return parent::type($name, $fresh);
    }

    public function schema(?string $schemaName = null, bool $forceRefresh = false): Schema
    {
        $suffixKey = substr(md5(rand(0, 1000)), 0, 5);
        $timingKey = 'GQL:schema:' . $suffixKey;
        ServerTimingUtil::start($timingKey);

        $schemaName = $schemaName ?? $this->config->get('graphql.default_schema', 'default');

        if (!$forceRefresh && !isset($this->schemas[$schemaName])) {
            $this->tryLoadSchemaFromCache($schemaName);
        }

        $return = parent::schema($schemaName);
        ServerTimingUtil::stop($timingKey);

        return $return;
    }

    protected function buildObjectTypeFromFields(array $fields, array $opts = []): ObjectType
    {
        return new ObjectType(array_merge([
            // The outer closure is called once when any field is first accessed,
            // constructing the field map cheaply (no class instantiation yet).
            'fields' => function () use ($fields) {
                $typeFields = [];
                foreach ($fields as $name => $field) {
                    if (\is_string($field)) {
                        $className = $field;
                        if (is_numeric($name)) {
                            // Numeric-indexed: must instantiate now to learn the field name.
                            $instance  = app()->make($className);
                            $def       = $instance->toArray();
                            $fieldName = $def['name'];
                            $typeFields[$fieldName] = $def;
                        } else {
                            // Named key: return a callable so webonyx wraps it in
                            // UnresolvedFieldDefinition and only resolves it when
                            // findField($name) is called for this specific field.
                            // Each class is instantiated on-demand, not all at once.
                            // We must override 'name' with $name (the map key) to match
                            // what the eager path does — the schema config key is
                            // authoritative, not $attributes['name'] from the class.
                            $typeFields[$name] = static function () use ($className, $name) {
                                $def         = app()->make($className)->toArray();
                                $def['name'] = $name;
                                return $def;
                            };
                        }
                    } else {
                        $fieldName = is_numeric($name) ? $field['name'] : $name;
                        $field['name'] = $fieldName;
                        $typeFields[$fieldName] = $field;
                    }
                }
                return $typeFields;
            },
        ], $opts));
    }

    public function buildSchemaFromConfig(array $schemaConfig): Schema
    {
        $schemaQuery = $schemaConfig['query'] ?? [];
        $schemaMutation = $schemaConfig['mutation'] ?? [];
        $schemaSubscription = $schemaConfig['subscription'] ?? [];
        $schemaTypes = $schemaConfig['types'] ?? [];
        $schemaDirectives = $schemaConfig['directives'] ?? [];

        $this->addTypes($schemaTypes);

        $query = $this->objectType($schemaQuery, ['name' => 'Query']);

        $mutation = $schemaMutation
            ? $this->objectType($schemaMutation, ['name' => 'Mutation'])
            : null;

        $subscription = $schemaSubscription
            ? $this->objectType($schemaSubscription, ['name' => 'Subscription'])
            : null;

        $directives = \GraphQL\Type\Definition\Directive::getInternalDirectives();

        foreach ($schemaDirectives as $class) {
            $directive = app()->make($class);
            $directives[$directive->name] = $directive;
        }

        $lazyload = $this->config->get('graphql.lazyload_types', true);

        return new Schema([
            'query' => $query,
            'mutation' => $mutation,
            'subscription' => $subscription,
            'directives' => $directives,
            'types' => static function () {
                $types = [];
                foreach (app(BaseGraphQL::class)->getTypes() as $name => $type) {
                    $types[] = app(BaseGraphQL::class)->type($name);
                }
                return $types;
            },
            'typeLoader' => $lazyload
                ? static function (string $name): Type {
                    return app(BaseGraphQL::class)->type($name);
                }
                : null,
            'assumeValid' => $lazyload,
        ]);
    }

    protected function tryLoadSchemaFromCache(string $schemaName): void
    {
        if (!$this->config->get('audentioGraphQL.enableSchemaCache', false)) {
            return;
        }

        $path = $this->getSchemaCacheFilePath($schemaName);

        if (!file_exists($path)) {
            return;
        }

        try {
            $data = unserialize(file_get_contents($path));
        } catch (\Throwable $e) {
            return;
        }

        if (!is_array($data) || !isset($data['hash'], $data['schemaConfig'])) {
            return;
        }

        if ($data['hash'] !== $this->getSchemaCacheConfigHash()) {
            return;
        }

        // Build a fresh lightweight schema from the cached config (O(1) with lazy closures).
        // Storing the result in $this->schemas means parent::schema() will hit the fast path.
        $this->clearTypeInstances();
        $schema = $this->buildSchemaFromConfig($data['schemaConfig']);
        $this->schemas[$schemaName] = $schema;
    }

    public function buildAndStoreSchemaCache(string $schemaName): string
    {
        // Serialize only the plain-string schemaConfig (no webonyx objects, no closures).
        // tryLoadSchemaFromCache rebuilds the lightweight Schema from this on each worker start.
        $schemaConfig = static::getNormalizedSchemaConfiguration($schemaName);

        $data = [
            'hash'         => $this->getSchemaCacheConfigHash(),
            'schemaConfig' => $schemaConfig,
        ];

        $path = $this->getSchemaCacheFilePath($schemaName);
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, serialize($data));

        return $path;
    }

    public function clearSchemaCache(string $schemaName): void
    {
        $path = $this->getSchemaCacheFilePath($schemaName);

        if (file_exists($path)) {
            unlink($path);
        }

        if (isset($this->schemas[$schemaName])) {
            unset($this->schemas[$schemaName]);
        }
    }

    public function getSchemaCacheFilePath(string $schemaName): string
    {
        $hash = $this->getSchemaCacheConfigHash();
        return storage_path("framework/cache/graphql/{$schemaName}-{$hash}.dat");
    }

    public function getSchemaCacheConfigHash(): string
    {
        return md5(serialize(config('gqlData')));
    }

    public function __construct(Container $app, Repository $config)
    {
        parent::__construct($app, $config);

        static::$instance = $this;
    }
}