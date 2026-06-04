<?php

declare(strict_types=1);

namespace Padosoft\Rebel\Bridge\Passkeys\Drivers;

use Padosoft\Rebel\Bridge\Passkeys\Contracts\PasskeyChallenger;
use Padosoft\Rebel\Core\Assurance\Aal;
use Padosoft\Rebel\Core\Assurance\AssuranceLevel;
use Padosoft\Rebel\Core\Audit\AuditEvent;
use Padosoft\Rebel\Core\Contracts\AuditLogger;
use Padosoft\Rebel\StepUp\Contracts\StepUpDriver;
use Padosoft\Rebel\StepUp\StepUpContext;

/**
 * Step-up driver backed by a WebAuthn passkey / FIDO2 credential.
 *
 * Assurance: **AAL3 and phishing-resistant** — the strongest step-up factor available
 * in the Laravel Rebel suite. A passkey is bound to a specific origin (domain + protocol),
 * so a phishing site operating on a different domain cannot capture and replay the assertion.
 *
 * AMR values: 'webauthn' (method = public-key credential), 'hwk' (hardware key attestation
 * — passkeys are stored in a hardware-backed secure enclave or security key).
 *
 * Audit events:
 *  - 'stepup.passkeys.started'  — challenge issued (start() called, user has passkey).
 *  - 'stepup.passkeys.verified' — assertion successfully verified (true result).
 *  - 'stepup.passkeys.failed'   — assertion rejected or any error (false result).
 *
 * Fail-closed: any \Throwable during verify() returns false and emits a 'failed' audit event.
 * The raw assertion and credential material are NEVER logged.
 *
 * Replay protection: start() issues a single-use challenge via the PasskeyChallenger. The
 * step-up manager stores it as the opaque reference and passes it back to verify(). An
 * assertion without a challenge reference is refused immediately (fail-closed).
 */
final class PasskeysStepUpDriver implements StepUpDriver
{
    /** @var list<string> */
    private const AMR = ['webauthn', 'hwk'];

    public function __construct(
        private readonly PasskeyChallenger $challenger,
        private readonly AuditLogger $audit,
    ) {}

    public function key(): string
    {
        return 'passkeys';
    }

    public function assurance(): AssuranceLevel
    {
        return new AssuranceLevel(Aal::Aal3, phishingResistant: true, amr: self::AMR);
    }

    public function isAvailableFor(StepUpContext $context): bool
    {
        return $this->challenger->hasPasskey($context->subject);
    }

    /**
     * Issue a WebAuthn challenge for the subject and return it as the opaque reference.
     *
     * Returns null when the subject has no registered passkey (driver not available).
     * The challenge is emitted as a 'started' audit event so the step-up funnel panel
     * can track the initiated-but-not-completed rate.
     */
    public function start(StepUpContext $context): ?string
    {
        if (! $this->challenger->hasPasskey($context->subject)) {
            return null;
        }

        $challenge = $this->challenger->startChallenge($context->subject);

        $this->audit->record(new AuditEvent(
            type: 'stepup.passkeys.started',
            subjectType: $context->subjectType(),
            subjectId: $context->subjectId(),
            channel: 'passkey',
            amr: self::AMR,
        ));

        return $challenge;
    }

    /**
     * Verify the WebAuthn assertion against the previously issued challenge.
     *
     * $input     — the JSON-encoded assertion from navigator.credentials.get().
     * $reference — the challenge string issued by start() (stored by the step-up manager).
     *
     * Returns true only on full cryptographic success. Always returns false on:
     *  - missing reference (no challenge was issued for this step-up);
     *  - assertion mismatch / wrong challenge / invalid signature;
     *  - any \Throwable from the underlying PasskeyChallenger.
     */
    public function verify(StepUpContext $context, string $input, ?string $reference): bool
    {
        // No bound challenge ⇒ refuse immediately (replay-safe).
        if ($reference === null || $reference === '') {
            $this->recordFailed($context);

            return false;
        }

        try {
            $accepted = $this->challenger->verifyAssertion($context->subject, $input, $reference);
        } catch (\Throwable) {
            // Fail-closed: any exception from the challenger is treated as a rejection.
            // We do NOT log the exception details because they may contain credential material.
            $this->recordFailed($context);

            return false;
        }

        if ($accepted) {
            $this->audit->record(new AuditEvent(
                type: 'stepup.passkeys.verified',
                subjectType: $context->subjectType(),
                subjectId: $context->subjectId(),
                channel: 'passkey',
                amr: self::AMR,
            ));

            return true;
        }

        $this->recordFailed($context);

        return false;
    }

    private function recordFailed(StepUpContext $context): void
    {
        $this->audit->record(new AuditEvent(
            type: 'stepup.passkeys.failed',
            subjectType: $context->subjectType(),
            subjectId: $context->subjectId(),
            channel: 'passkey',
            amr: self::AMR,
        ));
    }
}
