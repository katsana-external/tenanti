<?php

namespace Orchestra\Tenanti;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Support\DeferrableProvider;
use Orchestra\Support\Providers\ServiceProvider;

class TenantiServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('orchestra.tenanti', function (Container $app) {
            $manager = new TenantiManager($app);

            $this->registerConfigurationForManager($manager);

            return $manager;
        });

        $this->app->alias('orchestra.tenanti', TenantiManager::class);
    }

    /**
     * Register configuration for manager.
     */
    protected function registerConfigurationForManager(TenantiManager $manager): void
    {
        $namespace = $this->hasPackageRepository() ? 'orchestra/tenanti::' : 'orchestra.tenanti';

        $this->app->booted(static function ($app) use ($manager, $namespace) {
            $manager->setConfiguration($app->make('config')->get($namespace));
        });
    }

    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot()
    {
        $path = \realpath(__DIR__.'/../');

        $this->mergeConfigFrom("{$path}/config/config.php", 'orchestra.tenanti');

        $this->publishes([
           "{$path}/config/config.php" => config_path('orchestra/tenanti.php'),
       ], ['orchestra-tenanti', 'laravel-config']);

    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['orchestra.tenanti'];
    }
}
