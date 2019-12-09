<?php

namespace Audentio\LaravelGraphQL\GraphQL\Errors;

class ValidationError extends AbstractError
{
    protected $errorType = 'validation';
    public $validator;
    protected $messages;

    public function setValidator($validator)
    {
        $this->validator = $validator;
        $this->messages = $validator->messages();

        return $this;
    }

    public function setValidatorMessages($messages)
    {
        $this->messages = $messages;
    }

    public function getValidator()
    {
        return $this->validator;
    }

    public function getValidatorMessages()
    {
        return $this->messages ?: [];
    }
}