<?php

namespace Audentio\LaravelGraphQL\Providers;

use Audentio\LaravelBase\Traits\ExtendServiceProviderTrait;
use Audentio\LaravelGraphQL\Illuminate\Foundation\Console\ModelMakeCommand;
use Audentio\LaravelGraphQL\Rebing\GraphQL\Console\MutationMakeCommand;
use Audentio\LaravelGraphQL\Rebing\GraphQL\Console\QueryMakeCommand;
use Audentio\LaravelGraphQL\Rebing\GraphQL\Console\TypeMakeCommand;
use Illuminate\Support\ServiceProvider;

class ExtendServiceProvider extends ServiceProvider
{
    use ExtendServiceProviderTrait;

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->registerConsole();
        }
    }

    protected function registerConsole()
    {
        $this->extendRebing();
        $this->extendBase();
    }

    protected function extendBase()
    {
        $this->overrideIlluminateCommand('command.model.make', ModelMakeCommand::class);
    }

    protected function extendRebing()
    {
        $this->commands(TypeMakeCommand::class);
        $this->commands(MutationMakeCommand::class);
        $this->commands(QueryMakeCommand::class);
    }
}