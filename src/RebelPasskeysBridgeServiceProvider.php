<?php

declare(strict_types=1);

namespace Padosoft\Rebel\Bridge\Passkeys;

use Illuminate\Contracts\Config\Repository;
use Padosoft\Rebel\Bridge\Passkeys\Contracts\PasskeyChallenger;
use Padosoft\Rebel\Bridge\Passkeys\Drivers\PasskeysStepUpDriver;
use Padosoft\Rebel\StepUp\DriverRegistry;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Bridges spatie/laravel-passkeys (or any WebAuthn library) into the Laravel Rebel
 * step-up system as a phishing-resistant AAL3 passkey driver.
 *
 * The driver is registered into the step-up {@see DriverRegistry} only when:
 *   1. The config key `rebel-bridge-passkeys.drivers.passkeys` is true (default), AND
 *   2. A {@see PasskeyChallenger} implementation is bound in the service container.
 *
 * This means the bridge installs and boots correctly even when:
 *   - `spatie/laravel-passkeys` is not installed (feature-detected at runtime via class_exists);
 *   - the application has not yet bound a PasskeyChallenger (the driver is silently skipped).
 *
 * To activate, bind your PasskeyChallenger in AppServiceProvider:
 * ```php
 * use Padosoft\Rebel\Bridge\Passkeys\Challengers\SpatiePasskeyChallenger;
 * use Padosoft\Rebel\Bridge\Passkeys\Contracts\PasskeyChallenger;
 *
 * $this->app->singleton(PasskeyChallenger::class, SpatiePasskeyChallenger::class);
 * ```
 */
final class RebelPasskeysBridgeServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-rebel-bridge-passkeys')
            ->hasConfigFile('rebel-bridge-passkeys');
    }

    public function packageBooted(): void
    {
        $this->registerStepUpDrivers();
    }

    private function registerStepUpDrivers(): void
    {
        $config = $this->app->make(Repository::class);
        $registry = $this->app->make(DriverRegistry::class);

        // Passkeys driver: config-gated AND requires a PasskeyChallenger to be bound.
        // This mirrors the pattern in bridge-fortify's PasskeyConfirmer gate.
        if (
            $config->get('rebel-bridge-passkeys.drivers.passkeys', true) === true
            && $this->app->bound(PasskeyChallenger::class)
        ) {
            $registry->register($this->app->make(PasskeysStepUpDriver::class));
        }
    }
}
