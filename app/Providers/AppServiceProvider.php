<?php

namespace App\Providers;

use App\Interpretacion\Interpretador;
use App\Interpretacion\InterpretadorBase;
use App\Interpretacion\InterpretadorIA;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // La interpretación (plan v3 §13.6): con la IA si está configurada y hay clave; si no, la base.
        $this->app->bind(Interpretador::class, function () {
            $proveedor = config('interpretacion.proveedor');
            $conClave = filled(config("prism.providers.{$proveedor}.api_key"));

            return config('interpretacion.interpretador') === 'ia' && $conClave
                ? new InterpretadorIA($proveedor, config('interpretacion.modelo'), config('interpretacion.timeout'), config('interpretacion.intervalo_ms'))
                : new InterpretadorBase;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
