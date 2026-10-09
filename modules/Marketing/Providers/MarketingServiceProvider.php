<?php

namespace Modules\Marketing\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Factory;

class MarketingServiceProvider extends ServiceProvider
{
    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->registerFactories();
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->registerCommands();
    }

    /**
     * Registrar comandos artisan del módulo.
     */
    protected function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\Marketing\Console\Mkt03MaintenanceCommand::class,
                \Modules\Marketing\Console\Mkt03CrudTestCommand::class,
                \Modules\Marketing\Console\Mkt03ImportHistoricalCommand::class,
                \Modules\Marketing\Console\Mkt01EmailTriageReadCommand::class,
                \Modules\Marketing\Console\Mkt01EmailTriageClassifyCommand::class,
                \Modules\Marketing\Console\Mkt01EmailTriageRunCommand::class,
                \Modules\Marketing\Console\Mkt01EmailTriageImportEmlCommand::class,
                \Modules\Marketing\Console\Mkt01EmailTriageResetBreakerCommand::class,
                \Modules\Marketing\Console\Com02E2TestCommand::class,
                \Modules\Marketing\Console\Com02E4TestCommand::class,
                \Modules\Marketing\Console\ChatbotCloseExpiredCommand::class,
                \Modules\Marketing\Console\QuotationAutoPrequoteCommand::class,
                \Modules\Marketing\Console\QuotationAnalyzePatternsCommand::class,
                \Modules\Marketing\Console\Com07DetectarOportunidadesCommand::class,
            ]);
        }
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(RouteServiceProvider::class);
        $this->app->register(ChatbotServiceProvider::class);
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__.'/../Config/config.php' => config_path('marketing.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../Config/config.php', 'marketing'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/marketing');

        $sourcePath = __DIR__.'/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath
        ],'views');

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path . '/modules/marketing';
        }, \Config::get('view.paths')), [$sourcePath]), 'marketing');
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/marketing');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'marketing');
        } else {
            $this->loadTranslationsFrom(__DIR__ .'/../Resources/lang', 'marketing');
        }
    }

    /**
     * Register an additional directory of factories.
     *
     * @return void
     */
    public function registerFactories()
    {
        if (! app()->environment('production') && $this->app->runningInConsole()) {
            app(Factory::class)->load(__DIR__ . '/../Database/factories');
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }
}
