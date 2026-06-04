<?php

declare(strict_types=1);

namespace Padosoft\Rebel\Bridge\Passkeys\Challengers;

use Illuminate\Contracts\Auth\Authenticatable;
use Padosoft\Rebel\Bridge\Passkeys\Contracts\PasskeyChallenger;
use Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyAuthenticationOptionsAction;
use Spatie\LaravelPasskeys\Models\Concerns\HasPasskeys;

/**
 * Production {@see PasskeyChallenger} backed by `spatie/laravel-passkeys`.
 *
 * This class imports Spatie classes directly (they are in require-dev). At
 * runtime, if Spatie is not installed, this class will fail to load — so only
 * register it when you are certain `spatie/laravel-passkeys` is installed.
 * The service provider guards this with `class_exists()`.
 *
 * ## How Spatie manages challenges
 * `GeneratePasskeyAuthenticationOptionsAction::execute()` returns the full
 * WebAuthn `PublicKeyCredentialRequestOptions` as a JSON string and stores the
 * challenge in the Laravel session under 'passkey-authentication-options'.
 * The step-up driver stores this JSON as the opaque reference and passes it
 * back to `verifyAssertion()` as the `$challenge` / `$passkeyOptionsJson` argument.
 *
 * `FindPasskeyToAuthenticateAction::execute()` verifies the browser's assertion
 * against those stored options and returns the matching `Passkey` model on
 * success or `null` on any failure.
 *
 * ## Binding in your AppServiceProvider
 * ```php
 * use Padosoft\Rebel\Bridge\Passkeys\Challengers\SpatiePasskeyChallenger;
 * use Padosoft\Rebel\Bridge\Passkeys\Contracts\PasskeyChallenger;
 *
 * $this->app->singleton(PasskeyChallenger::class, SpatiePasskeyChallenger::class);
 * ```
 *
 * ## Extending / replacing
 * If your application does not use `spatie/laravel-passkeys`, implement the
 * {@see PasskeyChallenger} contract against your own WebAuthn library and bind
 * it in the container. The step-up driver is fully decoupled from Spatie.
 */
final class SpatiePasskeyChallenger implements PasskeyChallenger
{
    public function __construct(
        private readonly GeneratePasskeyAuthenticationOptionsAction $generateOptions,
        private readonly FindPasskeyToAuthenticateAction $findPasskey,
    ) {}

    /**
     * Does the user have at least one passkey registered?
     *
     * Delegates to Spatie's `HasPasskeys` interface, which the user model must
     * implement (add the `\Spatie\LaravelPasskeys\Models\Concerns\HasPasskeys`
     * interface + trait to your User model).
     */
    public function hasPasskey(Authenticatable $user): bool
    {
        if (! $user instanceof HasPasskeys) {
            return false;
        }

        return $user->passkeys()->exists();
    }

    /**
     * Generate a fresh WebAuthn challenge and return the options JSON.
     *
     * Spatie stores the challenge in the session and returns the full
     * `PublicKeyCredentialRequestOptions` as a JSON string. The step-up
     * driver stores this as the opaque reference and passes it back on verify.
     *
     * The user argument is not required by Spatie's global generate action, but
     * the interface requires it for future per-user challenge scoping.
     */
    public function startChallenge(Authenticatable $user): string
    {
        return $this->generateOptions->execute();
    }

    /**
     * Verify the WebAuthn assertion against the options that were generated at
     * challenge time.
     *
     * @param  string  $assertion  JSON-encoded `PublicKeyCredential` from the browser.
     * @param  string  $challenge  The options JSON returned by {@see startChallenge()}.
     */
    public function verifyAssertion(Authenticatable $user, string $assertion, string $challenge): bool
    {
        $passkey = $this->findPasskey->execute($assertion, $challenge);

        return $passkey !== null;
    }
}
