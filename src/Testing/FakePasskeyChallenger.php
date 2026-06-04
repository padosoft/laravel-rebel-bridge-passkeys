<?php

declare(strict_types=1);

namespace Padosoft\Rebel\Bridge\Passkeys\Testing;

use Illuminate\Contracts\Auth\Authenticatable;
use Padosoft\Rebel\Bridge\Passkeys\Contracts\PasskeyChallenger;

/**
 * Deterministic {@see PasskeyChallenger} for tests.
 *
 * Configurable state:
 *  - {@see $registered}         — whether subjects have a registered passkey (default: true).
 *  - {@see $expectedAssertion}  — the assertion string that will be accepted as valid.
 *  - {@see $challenge}          — the fixed challenge returned by {@see startChallenge()}.
 *  - {@see $shouldAccept}       — global on/off override: when false, verifyAssertion always returns false.
 *
 * Verification succeeds only when BOTH the assertion AND the challenge match the configured
 * values (mirroring real replay-binding: a correct assertion against the wrong challenge fails).
 *
 * Per-subject passkey registration can be simulated by swapping $registered per-test or by
 * constructing a new instance with registered: false.
 */
final class FakePasskeyChallenger implements PasskeyChallenger
{
    public function __construct(
        public bool $registered = true,
        public string $expectedAssertion = 'valid-assertion',
        public string $challenge = 'fake-challenge',
        public bool $shouldAccept = true,
    ) {}

    public function hasPasskey(Authenticatable $user): bool
    {
        return $this->registered;
    }

    public function startChallenge(Authenticatable $user): string
    {
        return $this->challenge;
    }

    public function verifyAssertion(Authenticatable $user, string $assertion, string $challenge): bool
    {
        if (! $this->shouldAccept) {
            return false;
        }

        return hash_equals($this->challenge, $challenge)
            && hash_equals($this->expectedAssertion, $assertion);
    }
}
