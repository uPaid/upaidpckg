<?php

namespace Upaidpckg\Config\Providers;

use Illuminate\Support\ServiceProvider;

class ConfigServiceProvider extends ServiceProvider
{
    protected $commands = [
        'Upaidpckg\Config\Commands\GetConfigCommand',
        'Upaidpckg\Config\Commands\RevertConfigCommand',
    ];


    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        require_once(__DIR__ . '/../Config/config.php');
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom( __DIR__ . '/../Config/config.php', 'upaidpckg');
        $this->commands($this->commands);
    }
}
