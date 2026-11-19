<?php

namespace App\Providers;

use App\Enums\Rol;
use App\Models\User;
use App\Services\Calendario\CalendarioLaboral;
use App\Services\Parametros;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Una sola instancia por petición: comparten su caché en memoria.
        $this->app->singleton(Parametros::class);
        $this->app->singleton(CalendarioLaboral::class);
    }

    public function boot(): void
    {
        Date::use(CarbonImmutable::class);

        // En desarrollo y pruebas se detectan consultas N+1 y atributos mal escritos.
        Model::shouldBeStrict(! $this->app->isProduction());

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // El superadministrador (soporte técnico) puede hacer todo.
        Gate::before(fn (User $usuario): ?bool => $usuario->hasRole(Rol::Superadmin->value) ? true : null);

        Password::defaults(fn (): Password => Password::min(8)->letters()->numbers());

        $this->limitadores();
    }

    private function limitadores(): void
    {
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));

        // La app envía fotos una a una: se limita por usuario para proteger el servidor.
        RateLimiter::for('visitas', fn (Request $request): Limit => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('ingreso', fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('consulta-publica', function (Request $request): Limit {
            $limite = max(1, app(Parametros::class)->entero('consulta_publica_limite'));

            return Limit::perMinute($limite)->by($request->ip());
        });
    }
}
