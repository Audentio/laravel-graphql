<?php

namespace Audentio\LaravelGraphQL\GraphQL\Support;

use Audentio\LaravelGraphQL\GraphQL\Errors\ValidationError;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Rebing\GraphQL\Error\AuthorizationError;
use Rebing\GraphQL\Support\Mutation as BaseMutation;
use Rebing\GraphQL\Support\SelectFields;

abstract class Mutation extends BaseMutation
{
    protected function getResolver(): ?\Closure
    {
        if (! method_exists($this, 'resolve')) {
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

            $info = null;
            foreach ($arguments as $arg) {
                if ($arg instanceof ResolveInfo) {
                    $info = $arg;
                }
            }

            // Validate mutation arguments
            $args = $arguments[1];
            $rules = call_user_func_array([$this, 'getRules'], [$args]);
            if (count($rules)) {

                // allow our error messages to be customised
                $messages = $this->validationErrorMessages($args);

                $validator = Validator::make($args, $rules, $messages);
                if ($validator->fails()) {
                    throw with(new ValidationError('validation', $info))->setValidator($validator);
                }
            }

            // Add the 'selects and relations' feature as 5th arg
            if (isset($arguments[3])) {
                $arguments[] = function (int $depth = null) use ($arguments): SelectFields {
                    $ctx = $arguments[2] ?? null;

                    return new SelectFields($arguments[3], $this->type(), $arguments[1], $depth ?? 5, $ctx);
                };
            }

            // Authorize
            if (call_user_func_array($authorize, $arguments) != true) {
                throw new AuthorizationError('Unauthorized');
            }

            return call_user_func_array($resolver, $arguments);
        };
    }
}