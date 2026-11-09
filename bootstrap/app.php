<?php

use App\Http\Middleware\CabecerasDeSeguridad;
use App\Http\Middleware\EnsureUsuarioActivo;
use App\Http\Middleware\RedirigirSiDebeActualizarPassword;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        
        
        
        

        
        
        

        
        
        
        
        
        
        $avisarFalla = fn (string $comando) => function () use ($comando): void {
            Log::critical("Scheduler: el comando '{$comando}' falló.", [
                'comando' => $comando,
                'hora' => now()->toDateTimeString(),
            ]);
        };

        
        
        $schedule->command('papeletas:procesar-vencimientos')
            ->everyMinute()
            ->withoutOverlapping(5)
            ->onFailure($avisarFalla('papeletas:procesar-vencimientos'));

        
        
        $schedule->command('papeletas:procesar-abandono-no-marcado')
            ->everyFiveMinutes()
            ->withoutOverlapping(15)
            ->onFailure($avisarFalla('papeletas:procesar-abandono-no-marcado'));

        
        $schedule->command('papeletas:procesar-vencimiento-sustentos')
            ->hourly()
            ->withoutOverlapping(30)
            ->onFailure($avisarFalla('papeletas:procesar-vencimiento-sustentos'));

        
        
        $schedule->command('papeletas:avisar-posthoc-acumulado')
            ->hourly()
            ->withoutOverlapping(30)
            ->onFailure($avisarFalla('papeletas:avisar-posthoc-acumulado'));

        
        
        
        
        
        
        $schedule->command('turnos:generar-proximo-mes')
            ->monthlyOn(25, '02:00')
            ->withoutOverlapping(120)
            ->onFailure($avisarFalla('turnos:generar-proximo-mes'));

        
        
        $schedule->command('jefaturas:avisar-faltantes')
            ->hourly()
            ->withoutOverlapping(30)
            ->onFailure($avisarFalla('jefaturas:avisar-faltantes'));
    })
    ->withMiddleware(function (Middleware $middleware): void {
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        $proxiesConfiables = trim((string) env('TRUSTED_PROXIES', ''));

        if ($proxiesConfiables !== '') {
            $middleware->trustProxies(
                at: $proxiesConfiables === '*' ? '*' : array_map('trim', explode(',', $proxiesConfiables)),
            );
        }

        
        
        
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);

        
        
        
        
        
        $middleware->web(append: [
            CabecerasDeSeguridad::class,
            EnsureUsuarioActivo::class,
            RedirigirSiDebeActualizarPassword::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
