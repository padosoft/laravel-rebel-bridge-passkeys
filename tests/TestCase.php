<?php

declare(strict_types=1);

namespace Padosoft\Rebel\Bridge\Passkeys\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Padosoft\Rebel\Bridge\Passkeys\Contracts\PasskeyChallenger;
use Padosoft\Rebel\Bridge\Passkeys\RebelPasskeysBridgeServiceProvider;
use Padosoft\Rebel\Bridge\Passkeys\Testing\FakePasskeyChallenger;
use Padosoft\Rebel\Core\RebelCoreServiceProvider;
use Padosoft\Rebel\EmailOtp\RebelEmailOtpServiceProvider;
use Padosoft\Rebel\StepUp\RebelStepUpServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            RebelCoreServiceProvider::class,
            RebelEmailOtpServiceProvider::class,
            RebelStepUpServiceProvider::class,
            RebelPasskeysBridgeServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('rebel-core.peppers', [1 => 'test-pepper']);
        $app['config']->set('rebel-core.pepper_current', 1);

        // Bind a FakePasskeyChallenger so the passkeys driver registers in tests.
        $app->singleton(PasskeyChallenger::class, fn () => new FakePasskeyChallenger);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../vendor/padosoft/laravel-rebel-core/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../vendor/padosoft/laravel-rebel-email-otp/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../vendor/padosoft/laravel-rebel-step-up/database/migrations');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });
    }
}
