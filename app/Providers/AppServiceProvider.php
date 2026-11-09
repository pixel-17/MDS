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
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;
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
        
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        
        Event::listen(function (RequestHandled $event): void {
            $esJson = str_contains((string) $event->response->headers->get('Content-Type'), 'application/json');

            if ($esJson && method_exists($event->response, 'setEncodingOptions')) {
                $event->response->setEncodingOptions(
                    JsonResponse::DEFAULT_ENCODING_OPTIONS | JSON_UNESCAPED_UNICODE
                );
            }
        });

        
        
        
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation): void {
            logger()->warning(sprintf('Lazy loading: [%s] en %s', $relation, $model::class));
        });

        User::observe(UserObserver::class);
        UnidadOrganica::observe(UnidadOrganicaObserver::class);

        
        Gate::policy(Sustento::class, PapeletaPolicy::class);

        
        View::composer(['layouts.app', 'components.trabajador-layout'], NavegacionComposer::class);

        /*
         * sede-mapa-leaflet (JS + CSS, ~150 kB) solo lo necesita el
         * formulario de Sede, y se carga con import() dinámico para no
         * ir en el bundle general (ver resources/js/sede-mapa.js). Pero
         * @vite() de Laravel precarga en TODA página el CSS (y a veces
         * el JS) de cualquier chunk que aparezca en "dynamicImports" del
         * manifest, para evitar flash de estilos sin aplicar cuando el
         * import dinámico resuelva — eso es justamente lo que hacía que
         * estos dos archivos aparecieran en cada acción/página, aunque
         * no hubiera ningún mapa en pantalla. Se excluye ese chunk del
         * preload: solo se pedirá cuando el mapa realmente se use.
         */

    }
}
