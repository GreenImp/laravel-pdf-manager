<?php

namespace GreenImp\PdfManager;

use Illuminate\Support\ServiceProvider;

class PdfManagerServiceProvider extends ServiceProvider
{
    public const PACKAGE_NAME = 'pdf-manager';

    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        $this->config();
        $this->lang();
        $this->migrations();
        $this->views();
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
    }

    /**
     * Publishes config files
     */
    protected function config()
    {
    }

    /**
     * Loads and publishes language files
     */
    protected function lang()
    {
    }

    /**
     * Loads migrations
     */
    protected function migrations()
    {
    }

    /**
     * Load views
     */
    protected function views()
    {
    }
}