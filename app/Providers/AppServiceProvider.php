<?php

namespace App\Providers;

use App\Models\Imovel;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // O painel espera o imóvel/lista em bruto, sem a chave "data" do Laravel.
        JsonResource::withoutWrapping();

        Route::bind('imovel', function (string $value) {
            return Imovel::query()->where(function ($q) use ($value) {
                $q->where('id', $value)->orWhere('slug', $value);
            })->firstOrFail();
        });

        Route::bind('utilizador', fn (string $value) => User::query()->findOrFail($value));

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip())->response(function () {
                return response()->json([
                    'message' => 'Demasiadas tentativas. Tente novamente dentro de um minuto.',
                ], 429);
            });
        });

        RateLimiter::for('pedidos', function (Request $request) {
            return Limit::perMinute(8)->by($request->ip())->response(function () {
                return response()->json([
                    'message' => 'Demasiados pedidos. Tente novamente dentro de um minuto.',
                ], 429);
            });
        });

        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $url = rtrim((string) config('leads.frontend_url'), '/')
                .'/admin/#/redefinir-senha?token='.$token
                .'&email='.urlencode($notifiable->getEmailForPasswordReset());

            return (new MailMessage)
                ->subject('Redefinir a senha do painel')
                ->greeting('Olá '.$notifiable->name)
                ->line('Recebemos um pedido para redefinir a senha do painel de João Domingues.')
                ->action('Criar uma senha nova', $url)
                ->line('Se não fez este pedido, ignore este e-mail. A senha actual mantém-se.');
        });
    }
}
