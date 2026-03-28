<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => view('auth.login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'canRegister'      => Features::enabled(Features::registration()),
            'status'           => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]));

        Fortify::requestPasswordResetLinkView(
            fn (Request $request) => view('auth.forgot-password', [
                'status' => $request->session()->get('status'),
            ])
        );

        Fortify::verifyEmailView(
            fn (Request $request) => view('auth.verify-email', [
                'status' => $request->session()->get('status'),
            ])
        );

        Fortify::registerView(fn () => view('auth.register'));

        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(
                Str::lower($request->input(Fortify::username())) . '|' . $request->ip()
            );

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
