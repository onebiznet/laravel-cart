<?php

namespace OneBiznet\LaravelCart;

use Illuminate\Foundation\AliasLoader;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelCartServiceProvider extends PackageServiceProvider
{
    public function register()
    {
        parent::register();

        $this->app->bind('cart', Cart::class);

        AliasLoader::getInstance()->alias('Cart', Facades\Cart::class);
    }

    public function boot()
    {
        parent::boot();
    }

    public function configurePackage(Package $package): void
    {
        $package->name('laravel-cart')
            ->hasConfigFile()
            // ->hasViews()
            // ->hasViewComponent('spatie', Alert::class)
            // ->hasViewComposer('*', MyViewComposer::class)
            // ->sharesDataWithAllViews('downloads', 3)
            // ->hasTranslations()
            // ->hasAssets()
            // ->publishesServiceProvider('MyProviderName')
            // ->hasRoute('web')
            ->hasMigration('create_laravel_cart_tables')
            ->runsMigrations()
            // ->hasCommand(YourCoolPackageCommand::class)
            // ->hasInstallCommand(function (InstallCommand $command) {
            //     $command
            //         ->publishConfigFile()
            //         ->publishAssets()
            //         ->publishMigrations()
            //         ->copyAndRegisterServiceProviderInApp()
            //         ->askToStarRepoOnGitHub();
            // })
            ;
    }
}
