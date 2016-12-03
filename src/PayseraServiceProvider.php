<?php

namespace Artme\Paysera;

use Illuminate\Support\ServiceProvider;


class PayseraServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {

    }

    /**
     * Register the application services.
     *
     *
     *
     * @return void
     */
    public function register()
    {
        require_once(__DIR__.'/../lib/WebToPay.php');
    }
}
