<?php

namespace App\Providers;

use App\Models\Sustento;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\Observers\UnidadOrganicaObserver;
use App\Observers\UserObserver;
use App\Policies\PapeletaPolicy;
use App\View\Composers\NavegacionComposer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        Model::preventLazyLoading(! $this->app->isProduction());
        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation): void {
            logger()->warning(sprintf('Lazy loading: [%s] en %s', $relation, $model::class));
        });

        User::observe(UserObserver::class);
        UnidadOrganica::observe(UnidadOrganicaObserver::class);

        Gate::policy(Sustento::class, PapeletaPolicy::class);

        View::composer(['layouts.app', 'components.trabajador-layout'], NavegacionComposer::class);

    }
}
