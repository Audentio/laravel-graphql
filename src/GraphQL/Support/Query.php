<?php

namespace Audentio\LaravelGraphQL\GraphQL\Support;

use Audentio\LaravelGraphQL\GraphQL\Traits\ErrorTrait;
use Audentio\LaravelGraphQL\GraphQL\Traits\PaginationTrait;
use Audentio\LaravelGraphQL\Rebing\GraphQL\Support\SelectFields;
use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type as GraphQLType;
use Rebing\GraphQL\Error\AuthorizationError;
use Rebing\GraphQL\Error\ValidationError;
use Rebing\GraphQL\Support\Query as BaseQuery;
use Rebing\GraphQL\Support\ResolveInfoFieldsAndArguments;

abstract class Query extends BaseQuery
{
    use ErrorTrait, PaginationTrait;

    public static function getQueryArgs(): array
    {
        throw new \LogicException('Contents of getArgs must be overridden');
    }

    public static function getQueryType(): GraphQLType
    {
        throw new \LogicException('Contents of getType must be overridden');
    }

    public function type(): GraphQLType
    {
        $class = get_called_class();

        return $class::getQueryType();
    }

    public function args(): array
    {
        $class = get_called_class();

        $baseScope = '';
        if (config('audentioGraphQL.enableBaseScopeGeneration')) {
            $baseScope = ucfirst($this->attributes['name']);
        }

        return $class::getQueryArgs($baseScope);
    }

    /**
     * @param array<int,mixed> $arguments
     * @param int $depth
     * @param array<string,mixed> $fieldsAndArguments
     */
    protected function instanciateOverrideSelectFields(array $arguments, array $fieldsAndArguments, int $depth = null): SelectFields
    {
        $ctx = $arguments[2] ?? null;

        if (null !== $depth && $depth !== $this->depth) {
            $fieldsAndArguments = (new ResolveInfoFieldsAndArguments($arguments[3]))
                ->getFieldsAndArgumentsSelection($depth);
        }

        return new SelectFields($this->type(), $arguments[1], $ctx, $fieldsAndArguments);
    }

    /*
     * The following function is copied directly from the parent class so we can extend
     * the SelectFields class since `instanciateSelectFields` is private.
     */
    protected function originalResolver(): ?Closure
    {
        if (!method_exists($this, 'resolve')) {
            return null;
        }

        $resolver = [$this, 'resolve'];
        $authorize = [$this, 'authorize'];

        return function () use ($resolver, $authorize) {
            // 0 - the "root" object; `null` for queries, otherwise the parent of a type
            // 1 - the provided `args` of the query or type (if applicable), empty array otherwise
            // 2 - the "GraphQL query context" (see \Rebing\GraphQL\GraphQLController::queryContext)
            // 3 - \GraphQL\Type\Definition\ResolveInfo as provided by the underlying GraphQL PHP library
            // 4 (!) - added by this library, encapsulates creating a `SelectFields` instance
            $arguments = func_get_args();

            // Validate mutation arguments
            $args = $arguments[1];

            $rules = $this->getRules($args);

            if (count($rules)) {
                $validator = $this->getValidator($args, $rules);

                if ($validator->fails()) {
                    throw new ValidationError('validation', $validator);
                }
            }

            $fieldsAndArguments = (new ResolveInfoFieldsAndArguments($arguments[3]))->getFieldsAndArgumentsSelection($this->depth);

            // Validate arguments in fields
            $this->validateFieldArguments($fieldsAndArguments);

            $arguments[1] = $this->getArgs($arguments);

            // Authorize
            if (true != call_user_func_array($authorize, $arguments)) {
                throw new AuthorizationError($this->getAuthorizationMessage());
            }

            $method = new \ReflectionMethod($this, 'resolve');

            $additionalParams = array_slice($method->getParameters(), 3);

            $additionalArguments = array_map(function ($param) use ($arguments, $fieldsAndArguments) {
                /** @var \ReflectionNamedType $paramType */
                $paramType = $param->getType();

                if ($paramType->isBuiltin()) {
                    throw new \InvalidArgumentException("'{$param->name}' could not be injected");
                }

                $className = $param->getType()->getName();

                if (Closure::class === $className) {
                    return function (int $depth = null) use ($arguments, $fieldsAndArguments): SelectFields {
                        return $this->instanciateOverrideSelectFields($arguments, $fieldsAndArguments, $depth);
                    };
                }

                if (SelectFields::class === $className) {
                    return $this->instanciateOverrideSelectFields($arguments, $fieldsAndArguments, null);
                }

                if (ResolveInfo::class === $className) {
                    return $arguments[3];
                }

                return app()->make($className);
            }, $additionalParams);

            return call_user_func_array($resolver, array_merge(
                [$arguments[0], $arguments[1], $arguments[2]],
                $additionalArguments
            ));
        };
    }


}