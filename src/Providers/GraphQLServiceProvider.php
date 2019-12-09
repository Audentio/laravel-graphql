<?php

namespace Audentio\LaravelGraphQL\Providers;

use Audentio\LaravelBase\Traits\ExtendServiceProviderTrait;
use Audentio\LaravelGraphQL\GraphQL\Console\ConfigGraphqlCommand;
use Audentio\LaravelGraphQL\GraphQL\Console\EnumMakeCommand;
use Audentio\LaravelGraphQL\Illuminate\Foundation\Console\ModelMakeCommand;
use Audentio\LaravelGraphQL\Rebing\GraphQL\Console\MutationMakeCommand;
use Audentio\LaravelGraphQL\Rebing\GraphQL\Console\QueryMakeCommand;
use Audentio\LaravelGraphQL\Rebing\GraphQL\Console\TypeMakeCommand;
use Illuminate\Support\ServiceProvider;

class GraphQLServiceProvider extends ServiceProvider
{
    use ExtendServiceProviderTrait;

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->bootConsole();
        }

        $this->bootPublishes();
    }

    protected function bootConsole()
    {
        $this->extendRebing();
        $this->extendBase();

        $this->commands(EnumMakeCommand::class);
        $this->commands(ConfigGraphqlCommand::class);
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

    protected function bootPublishes(): void
    {
        $this->publishes([
            __DIR__.'/../../config/config.php' => config_path('graphql.php')
        ], 'config');
    }
}