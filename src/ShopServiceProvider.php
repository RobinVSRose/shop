<?php

namespace Ejoy\Shop;

use Illuminate\Support\ServiceProvider;

class ShopServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function boot(Shop $extension)
    {
        if (! Shop::boot()) {
            return ;
        }

        if ($views = $extension->views()) {
            $this->loadViewsFrom($views, 'shop');
        }

        if ($this->app->runningInConsole() && $assets = $extension->assets()) {
            $this->publishes(
                [$assets => public_path('vendor/ejoy/shop')],
                'shop'
            );
        }

        $this->app->booted(function () {
            Shop::routes(__DIR__.'/../routes/web.php');
        });
    }
}