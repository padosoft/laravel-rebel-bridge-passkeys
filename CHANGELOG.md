# Changelog

All notable changes to `padosoft/laravel-rebel-bridge-passkeys` will be documented here.

This project adheres to [Semantic Versioning](https://semver.org/) and stays in the `0.1.x`
family to remain compatible with `^0.1` constraints in dependents.

---

## [0.1.0] — 2026-06-04

### Added

- `PasskeysStepUpDriver` — AAL3, phishing-resistant passkey step-up driver for Rebel's
  `DriverRegistry`. Key: `'passkeys'`. AMR: `['webauthn', 'hwk']`.
- `Contracts\PasskeyChallenger` interface — the seam between the step-up driver and any
  WebAuthn library. Implement and bind to swap the backend.
- `Challengers\SpatiePasskeyChallenger` — production adapter backed by `spatie/laravel-passkeys`
  (feature-detected; only required in `require-dev`, runtime-optional).
- `Testing\FakePasskeyChallenger` — deterministic test double: configurable registration state,
  challenge, and accept/reject behaviour. Fully offline.
- `RebelPasskeysBridgeServiceProvider` — wires the driver into the step-up registry when
  `rebel-bridge-passkeys.drivers.passkeys` is `true` AND a `PasskeyChallenger` is bound.
- `config/rebel-bridge-passkeys.php` — `drivers.passkeys` toggle (`REBEL_PASSKEYS_DRIVER_PASSKEYS`).
- Audit events via core `AuditLogger` (→ `rebel_auth_events`, sync|queue):
  - `stepup.passkeys.started` — challenge issued.
  - `stepup.passkeys.verified` — assertion verified.
  - `stepup.passkeys.failed` — assertion rejected or any exception (fail-closed).
- 17 Pest tests covering AAL3 assurance, phishing-resistance, driver registration gates,
  `isAvailableFor`, `start`, `verify` happy path, all rejection paths, fail-closed
  `\Throwable` handling, and no-credential-in-audit structural test.
- `CLAUDE.md`, `AGENTS.md`, `.claude/skills/rebel-package-dev/SKILL.md` — batteries for AI agents.
- PHPStan level max, Pint (Laravel preset), CI matrix PHP 8.3/8.4/8.5 × Laravel 12/13.
