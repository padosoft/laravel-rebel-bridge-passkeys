<?php

declare(strict_types=1);

namespace Padosoft\Rebel\Bridge\Passkeys;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Service provider for the laravel-rebel-bridge-passkeys package (initial skeleton).
 * The full implementation will arrive in its roadmap macro-task.
 */
final class RebelPasskeysBridgeServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('laravel-rebel-bridge-passkeys');
    }
}
