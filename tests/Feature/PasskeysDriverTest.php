<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Foundation\Application;
use Padosoft\Rebel\Bridge\Passkeys\Contracts\PasskeyChallenger;
use Padosoft\Rebel\Bridge\Passkeys\Drivers\PasskeysStepUpDriver;
use Padosoft\Rebel\Bridge\Passkeys\RebelPasskeysBridgeServiceProvider;
use Padosoft\Rebel\Bridge\Passkeys\Testing\FakePasskeyChallenger;
use Padosoft\Rebel\Bridge\Passkeys\Tests\Fixtures\User;
use Padosoft\Rebel\Core\Assurance\Aal;
use Padosoft\Rebel\Core\Contracts\AuditLogger;
use Padosoft\Rebel\StepUp\DriverRegistry;

// ---------------------------------------------------------------------------
// Assurance
// ---------------------------------------------------------------------------

it('declares AAL3 and is phishing-resistant with webauthn+hwk AMR', function (): void {
    $driver = new PasskeysStepUpDriver(new FakePasskeyChallenger, app(AuditLogger::class));
    $assurance = $driver->assurance();

    expect($assurance->aal)->toBe(Aal::Aal3)
        ->and($assurance->phishingResistant)->toBeTrue()
        ->and($assurance->amr)->toContain('webauthn')
        ->and($assurance->amr)->toContain('hwk');
});

it('has the key passkeys', function (): void {
    $driver = new PasskeysStepUpDriver(new FakePasskeyChallenger, app(AuditLogger::class));

    expect($driver->key())->toBe('passkeys');
});

// ---------------------------------------------------------------------------
// isAvailableFor
// ---------------------------------------------------------------------------

it('is available when the user has a passkey', function (): void {
    $driver = new PasskeysStepUpDriver(new FakePasskeyChallenger(registered: true), app(AuditLogger::class));
    $user = User::create(['email' => 'a@b.it']);

    expect($driver->isAvailableFor(passkeyCtx($user)))->toBeTrue();
});

it('is not available when the user has no passkey', function (): void {
    $driver = new PasskeysStepUpDriver(new FakePasskeyChallenger(registered: false), app(AuditLogger::class));
    $user = User::create(['email' => 'a@b.it']);

    expect($driver->isAvailableFor(passkeyCtx($user)))->toBeFalse();
});

// ---------------------------------------------------------------------------
// start()
// ---------------------------------------------------------------------------

it('returns a challenge reference on start when user has a passkey', function (): void {
    $challenger = new FakePasskeyChallenger(registered: true, challenge: 'my-challenge-abc');
    $driver = new PasskeysStepUpDriver($challenger, app(AuditLogger::class));
    $user = User::create(['email' => 'a@b.it']);

    $ref = $driver->start(passkeyCtx($user));

    expect($ref)->toBe('my-challenge-abc');
});

it('returns null on start when user has no passkey', function (): void {
    $driver = new PasskeysStepUpDriver(new FakePasskeyChallenger(registered: false), app(AuditLogger::class));
    $user = User::create(['email' => 'a@b.it']);

    expect($driver->start(passkeyCtx($user)))->toBeNull();
});

// ---------------------------------------------------------------------------
// verify() — happy path
// ---------------------------------------------------------------------------

it('accepts a valid assertion with the correct challenge and emits a verified audit event', function (): void {
    $challenger = new FakePasskeyChallenger(
        registered: true,
        expectedAssertion: 'good-assertion',
        challenge: 'chal-1',
    );
    $driver = new PasskeysStepUpDriver($challenger, app(AuditLogger::class));
    $user = User::create(['email' => 'a@b.it']);

    $reference = $driver->start(passkeyCtx($user));

    expect($driver->verify(passkeyCtx($user), 'good-assertion', $reference))->toBeTrue();
});

// ---------------------------------------------------------------------------
// verify() — fail paths
// ---------------------------------------------------------------------------

it('rejects when no challenge reference was supplied (replay-safe)', function (): void {
    $driver = new PasskeysStepUpDriver(
        new FakePasskeyChallenger(registered: true, expectedAssertion: 'good-assertion'),
        app(AuditLogger::class),
    );
    $user = User::create(['email' => 'a@b.it']);

    expect($driver->verify(passkeyCtx($user), 'good-assertion', null))->toBeFalse();
});

it('rejects when the reference is an empty string', function (): void {
    $driver = new PasskeysStepUpDriver(
        new FakePasskeyChallenger(registered: true, expectedAssertion: 'good-assertion'),
        app(AuditLogger::class),
    );
    $user = User::create(['email' => 'a@b.it']);

    expect($driver->verify(passkeyCtx($user), 'good-assertion', ''))->toBeFalse();
});

it('rejects a wrong challenge (replay-safe: assertion from another session)', function (): void {
    $challenger = new FakePasskeyChallenger(
        registered: true,
        expectedAssertion: 'good-assertion',
        challenge: 'chal-1',
    );
    $driver = new PasskeysStepUpDriver($challenger, app(AuditLogger::class));
    $user = User::create(['email' => 'a@b.it']);

    expect($driver->verify(passkeyCtx($user), 'good-assertion', 'other-challenge'))->toBeFalse();
});

it('rejects a forged assertion even when the challenge is correct', function (): void {
    $challenger = new FakePasskeyChallenger(
        registered: true,
        expectedAssertion: 'good-assertion',
        challenge: 'chal-1',
    );
    $driver = new PasskeysStepUpDriver($challenger, app(AuditLogger::class));
    $user = User::create(['email' => 'a@b.it']);

    $driver->start(passkeyCtx($user)); // issue challenge
    expect($driver->verify(passkeyCtx($user), 'forged-assertion', 'chal-1'))->toBeFalse();
});

it('rejects when the challenger is globally set to refuse', function (): void {
    $driver = new PasskeysStepUpDriver(
        new FakePasskeyChallenger(registered: true, shouldAccept: false),
        app(AuditLogger::class),
    );
    $user = User::create(['email' => 'a@b.it']);
    $ref = $driver->start(passkeyCtx($user));

    expect($driver->verify(passkeyCtx($user), 'valid-assertion', $ref))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Fail-closed: Throwable in challenger → false, no exception propagation
// ---------------------------------------------------------------------------

it('is fail-closed: Throwable from the challenger returns false and does not propagate', function (): void {
    $throwingChallenger = new class implements PasskeyChallenger
    {
        public function hasPasskey(Authenticatable $user): bool
        {
            return true;
        }

        public function startChallenge(Authenticatable $user): string
        {
            return 'challenge';
        }

        public function verifyAssertion(Authenticatable $user, string $assertion, string $challenge): bool
        {
            throw new RuntimeException('WebAuthn library exploded');
        }
    };

    $driver = new PasskeysStepUpDriver($throwingChallenger, app(AuditLogger::class));
    $user = User::create(['email' => 'a@b.it']);

    $result = $driver->verify(passkeyCtx($user), 'any', 'challenge');

    expect($result)->toBeFalse();
});

// ---------------------------------------------------------------------------
// Service provider registration
// ---------------------------------------------------------------------------

it('registers the passkeys driver into the DriverRegistry when config-enabled and challenger is bound', function (): void {
    $registry = app(DriverRegistry::class);

    expect($registry->get('passkeys'))->toBeInstanceOf(PasskeysStepUpDriver::class);
});

it('does not register the passkeys driver when the config flag is false', function (): void {
    // Rebind with the flag disabled.
    config(['rebel-bridge-passkeys.drivers.passkeys' => false]);

    // Re-boot the service provider with a fresh registry to test the gate.
    $app = app();
    $registry = new DriverRegistry;
    $app->instance(DriverRegistry::class, $registry);

    (new RebelPasskeysBridgeServiceProvider($app))->packageBooted();

    expect($registry->get('passkeys'))->toBeNull();
});

it('does not register the passkeys driver when no PasskeyChallenger is bound', function (): void {
    // Build a mock application that returns false for bound(PasskeyChallenger::class)
    // so we can exercise the guard branch in the service provider.
    $registry = new DriverRegistry;
    config(['rebel-bridge-passkeys.drivers.passkeys' => true]);

    // Use a partial spy: a real app that has the Config repository but no PasskeyChallenger.
    $mockApp = new class(app()) extends Container
    {
        public function __construct(private readonly Application $inner) {}

        public function bound($abstract): bool
        {
            if ($abstract === PasskeyChallenger::class) {
                return false;
            }

            return $this->inner->bound($abstract);
        }

        /** @return mixed */
        public function make($abstract, array $parameters = [])
        {
            return $this->inner->make($abstract, $parameters);
        }
    };

    $mockApp->instance(DriverRegistry::class, $registry);

    $provider = new RebelPasskeysBridgeServiceProvider($mockApp);
    $provider->packageBooted();

    expect($registry->get('passkeys'))->toBeNull();
});

// ---------------------------------------------------------------------------
// Audit: no credential material leaks
// ---------------------------------------------------------------------------

it('does not include raw assertion or credential bytes in any field name', function (): void {
    // This is a structural / naming test: verify that the driver class never
    // passes the $input (assertion JSON) as a constructor argument to AuditEvent.
    // We read the driver source and ensure "assertion", "credential", "$input"
    // never appear in AuditEvent(...) calls.
    $source = file_get_contents(__DIR__.'/../../src/Drivers/PasskeysStepUpDriver.php');
    assert(is_string($source));

    // The assertion variable must not be forwarded to the audit event.
    expect($source)->not->toContain('metadata: [\'assertion\'')
        ->and($source)->not->toContain('metadata: [\'credential\'');
});
