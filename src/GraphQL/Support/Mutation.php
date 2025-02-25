<?php

namespace Audentio\LaravelGraphQL\GraphQL\Support;

use Audentio\LaravelGraphQL\GraphQL\Support\Resource as BaseResource;
use Audentio\LaravelGraphQL\GraphQL\Support\Traits\CustomResolveHandlingTrait;
use GraphQL\Type\Definition\Type as GraphqlType;
use Rebing\GraphQL\Support\Mutation as BaseMutation;

abstract class Mutation extends BaseMutation
{
    use CustomResolveHandlingTrait;

    protected $resource;

    public function type(): GraphqlType
    {
        $fields = [
            lcfirst($this->getActionType() . $this->getResource()->getGraphQLTypeName()) => [
                'name' => lcfirst($this->getResource()->getGraphQLTypeNameWithoutPrefix()),
                'type' => \GraphQL::type($this->getResource()->getGraphQLTypeName()),
            ],
        ];

        if ($this->getReturnedMutationAction()) {
            $mutationActionKey = lcfirst($this->getActionType() . $this->getResource()->getGraphQLTypeName() . 'MutationAction');
            $fields[$mutationActionKey] = [
                'name' => 'mutationAction',
                'type' => \GraphQL::type('MutationActionEnum'),
            ];
        }

        return \GraphQL::newObjectType([
            'name' => lcfirst($this->getActionType() . $this->getResource()->getGraphQLTypeName()),
            'fields' => $fields,
        ]);
    }

    public function args(): array
    {
        $actionType = $this->getActionType();

        if ($actionType !== 'update' && $actionType !== 'create' && $actionType !== 'delete'
            && $actionType !== 'restore' && $actionType !== 'undelete') {
            throw new \LogicException('You must extend the args() function on ' . get_class($this));
        }

        $fields = [];

        $dataType = lcfirst($this->getResource()->getGraphQLTypeNameWithoutPrefix());

        $baseScope = $this->getResource()->getBaseScope($this->getActionType());

        if ($actionType === 'delete' || $actionType === 'restore' || $actionType === 'undelete') {
            $fields['id'] = [
                'type' => GraphqlType::nonNull(GraphqlType::id()),
                'rules' => ['required'],
            ];
        } else {
            $additionalFields = $this->getAdditionalResourceFields();

            $isUpdate = false;
            if ($actionType === 'update') {
                $isUpdate = true;

                if (!$this->removeUpdateResourceIDField()) {
                    $additionalFields['id'] = [
                        'type' => GraphqlType::nonNull(GraphqlType::id()),
                        'rules' => ['required'],
                    ];
                }
            }

            $fields = array_merge(
                $additionalFields,
                $this->getResource()->getInputFields($baseScope, $isUpdate),
                $this->getResource()->getCommonFields($baseScope, $isUpdate)
            );
        }

        return [
            $dataType => [
                'rules' => ['required'],
                'type' => \GraphQL::newInputObjectType([
                    'name' => $this->getActionType() . $this->getResource()->getGraphQLTypeName() . 'Data',
                    'fields' => $fields
                ]),
            ],
        ];
    }

    protected function getAdditionalResourceFields(): array
    {
        return [];
    }

    protected function removeUpdateResourceIDField(): bool
    {
        return false;
    }

    protected function getResource(): BaseResource
    {
        if (!$this->resource) {
            $className = $this->getResourceClassName();
            $this->resource = new $className;
        }

        return $this->resource;
    }

    protected function postResultHook(mixed &$result): void
    {
        if (is_array($result)) {
            $result['mutationAction'] = $this->getReturnedMutationAction();
        }
    }

//    protected function getResolver(): ?\Closure
//    {
//        $return = $this->__getResolver();
//
//        return $return;
//    }
//
//    private function __getResolver(): ?\Closure
//    {
//        if (! method_exists($this, 'resolve')) {
//            return null;
//        }
//
//        $resolver = [$this, 'resolve'];
//        $authorize = [$this, 'authorize'];
//
//        return function () use ($resolver, $authorize) {
//            // 0 - the "root" object; `null` for queries, otherwise the parent of a type
//            // 1 - the provided `args` of the query or type (if applicable), empty array otherwise
//            // 2 - the "GraphQL query context" (see \Rebing\GraphQL\GraphQLController::queryContext)
//            // 3 - \GraphQL\Type\Definition\ResolveInfo as provided by the underlying GraphQL PHP library
//            // 4 (!) - added by this library, encapsulates creating a `SelectFields` instance
//            $arguments = func_get_args();
//
//            $info = null;
//            foreach ($arguments as $arg) {
//                if ($arg instanceof ResolveInfo) {
//                    $info = $arg;
//                }
//            }
//
//            // Validate mutation arguments
//            $args = $arguments[1];
//            $rules = call_user_func_array([$this, 'getRules'], [$args]);
//            if (count($rules)) {
//
//                // allow our error messages to be customised
//                $messages = $this->validationErrorMessages($args);
//
//                $validator = Validator::make($args, $rules, $messages);
//                if ($validator->fails()) {
//                    throw with(new ValidationError('validation', $info))->setValidator($validator);
//                }
//            }
//
//            // Add the 'selects and relations' feature as 5th arg
//            if (isset($arguments[3])) {
//                $arguments[] = function (int $depth = null) use ($arguments): SelectFields {
//                    $ctx = $arguments[2] ?? null;
//
//                    return new SelectFields($arguments[3], $this->type(), $arguments[1], $depth ?? 5, $ctx);
//                };
//            }
//
//            // Authorize
//            if (call_user_func_array($authorize, $arguments) != true) {
//                throw new AuthorizationError('Unauthorized');
//            }
//
//            $return = call_user_func_array($resolver, $arguments);
//
//            if (is_array($return)) {
//                $return['mutationAction'] = $this->getReturnedMutationAction();
//            }
//            return $return;
//        };
//    }

    public function getReturnedMutationAction(): ?string
    {
        switch ($this->getActionType()) {
            case 'create':
            case 'delete':
            case 'update':
                return $this->getActionType();
        }

        return null;
    }

    abstract protected function getActionType(): string;

    abstract protected function getResourceClassName(): string;
}
