<?php

declare(strict_types=1);

namespace Padosoft\Rebel\Bridge\Passkeys\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Padosoft\Rebel\Bridge\Passkeys\Testing\FakePasskeyChallenger;

/**
 * Seam between the Rebel step-up system and any WebAuthn/passkey implementation.
 *
 * The WebAuthn assertion ceremony is a two-step browser interaction:
 *  1. The server issues a challenge (nonce): {@see startChallenge()} — the browser feeds
 *     this to `navigator.credentials.get()`.
 *  2. The browser returns an assertion (JSON from the FIDO2 device): {@see verifyAssertion()}
 *     must validate it against the SAME challenge that was issued in step 1.
 *
 * Because the implementation depends on your relying-party setup (domain, origin, user
 * verification policy, passkey storage), this bridge does NOT hard-code one implementation.
 * Bind your own (e.g. {@see SpatiePasskeyChallenger} backed by `spatie/laravel-passkeys`) to
 * this contract in the container. A {@see FakePasskeyChallenger} ships for tests.
 *
 * If no implementation is bound in the container, the passkeys step-up driver is simply
 * not registered — the application continues to work with other drivers.
 *
 * IMPORTANT (replay resistance): {@see startChallenge()} must issue a single-use, server-side
 * nonce. {@see verifyAssertion()} MUST validate the assertion against THAT exact challenge.
 * The step-up driver stores the challenge as its opaque reference and passes it back on
 * verify, so a captured assertion cannot be replayed against a different challenge.
 */
interface PasskeyChallenger
{
    /**
     * Does this user have at least one passkey / FIDO2 credential registered?
     *
     * This is used by {@see PasskeysStepUpDriver::isAvailableFor()} to decide whether
     * the passkey step-up option should be offered to the user. Return false when the
     * user has no registered credentials so the step-up manager falls back gracefully
     * to another factor.
     */
    public function hasPasskey(Authenticatable $user): bool;

    /**
     * Issue a fresh single-use challenge (nonce) for this user.
     *
     * The returned string is:
     *  - sent to the browser as the WebAuthn options/challenge (your frontend feeds it to
     *    `navigator.credentials.get({ publicKey: { challenge: ... } })`);
     *  - stored by the step-up driver as its opaque reference and passed back verbatim to
     *    {@see verifyAssertion()}, so the server can cryptographically bind the assertion
     *    to this exact nonce.
     *
     * The string MUST be a fresh, unguessable value each time (at minimum a secure random
     * hex/base64 string). The spatie implementation stores it in the session.
     */
    public function startChallenge(Authenticatable $user): string;

    /**
     * Verify the WebAuthn assertion against the specific challenge previously issued.
     *
     * @param  string  $assertion  The JSON-encoded assertion returned by `navigator.credentials.get()`.
     * @param  string  $challenge  The challenge string that was issued by {@see startChallenge()}.
     *
     * Return true only on FULL cryptographic success (signature valid + challenge match +
     * origin/rpId match + user verification as required). Return false on any failure.
     * Never throw (let the caller handle failures uniformly). Never fail-open.
     */
    public function verifyAssertion(Authenticatable $user, string $assertion, string $challenge): bool;
}
