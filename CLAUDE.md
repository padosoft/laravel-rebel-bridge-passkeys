# CLAUDE.md — AI working guide for `padosoft/laravel-rebel-bridge-passkeys`

> Working on this package with an AI agent (Claude Code, Cursor, Copilot, Codex)? Read this first.
> It's the "batteries" that make vibe-coding here land on the first try. Plain Markdown — every
> tool can read it.

## What this package is

A WebAuthn **passkey step-up driver** for the Laravel Rebel enterprise-auth suite. It bridges
`spatie/laravel-passkeys` (or any WebAuthn library) into Rebel's step-up `DriverRegistry` and
provides a phishing-resistant **AAL3** step-up factor (`key: 'passkeys'`, AMR: `['webauthn','hwk']`).

Part of the **Laravel Rebel** suite. The shared language (value objects, contracts, the audit trail)
lives in `padosoft/laravel-rebel-core`; the step-up contracts and registry live in
`padosoft/laravel-rebel-step-up`.

### Key design decisions

- **Seam pattern:** the browser-side WebAuthn ceremony (challenge / assertion) is abstracted behind
  `Contracts\PasskeyChallenger`. The real implementation (`Challengers\SpatiePasskeyChallenger`) is
  feature-detected at runtime (`class_exists`); a `Testing\FakePasskeyChallenger` ships for tests.
- **Fail-closed everywhere:** no bound challenger = driver not registered. Empty reference = verify
  returns false. Any `\Throwable` in the challenger = false + failed audit event.
- **Audit completeness:** three event types (`stepup.passkeys.started`, `.verified`, `.failed`)
  recorded via the core `AuditLogger` (persisted to `rebel_auth_events`, supports sync|queue).

## Non-negotiable conventions

- `declare(strict_types=1);` in every PHP file; `final` classes; constructor property promotion.
- **PHPStan level max** must stay green. Do NOT add `@phpstan-ignore`, baseline entries, or
  `assert()`/inline `@var` to silence errors — fix the root cause. Common recipes:
  - narrow `mixed` before casting: `is_scalar($x) ? (string) $x : null`;
  - `json_decode($s, true)` is `array<array-key, mixed>`;
  - the container's `make('request')` is already typed `Illuminate\Http\Request`;
  - use `cursor()` for large scans, `withoutGlobalScopes()` for cross-tenant admin reads;
  - nested Eloquent `where(fn ($q) => …)` closures receive `Illuminate\Database\Eloquent\Builder`.
- **Tests:** Pest, Testbench. Cover happy path, auth/fail-closed, Throwable fail-closed, empty state.
- **Style:** Pint (`composer pint`). **Docs/comments in English.**
- Package wiring uses `spatie/laravel-package-tools` (`configurePackage`).

## Security & telemetry rules (suite-wide)

- Never store PII in cleartext: identifiers, IPs and User-Agents are **keyed HMACs** (core
  `KeyedHasher`). **Never log the raw WebAuthn assertion or credential bytes** — the `AuditEvent`
  `metadata` field must never contain assertion JSON, credential IDs in the clear, or public keys.
- **Telemetry completeness:** capture `started`/`verified`/`failed` with assurance + AMR so the
  step-up funnel panel, compliance AMR breakdown, and audit explorer all light up. Leave a field
  empty only when the driver genuinely can't supply it — honest empty state, never faked data.
- Record through the core `AuditLogger` contract — persists to `rebel_auth_events` (never session);
  configurable sync|queue dispatch (Horizon-ready).

## How to extend it

- **Swap the WebAuthn backend:** implement `Contracts\PasskeyChallenger` against your preferred
  library (e.g. `web-auth/webauthn-framework`) and bind it in the container. The driver is fully
  decoupled from Spatie.
- **Add audit metadata:** the driver emits three `AuditEvent` types. You can enrich them by
  intercepting the `AuditLogger` or by extending `PasskeysStepUpDriver` — but never forward raw
  credential bytes into `metadata`.

## Definition of Done (per change)

1. Red→green with Pest; `composer phpstan` (max) + `composer pint -- --test` clean.
2. One feature branch, one PR to `main`. CI matrix **PHP 8.3/8.4/8.5 × Laravel 12/13** must be green.
3. Update `README.md` + `CHANGELOG.md`. Squash-merge.
4. **Release:** `git tag vX.Y.Z && git push origin vX.Y.Z` + `gh release create`. Stay in `0.1.x`
   (Composer `^0.1` excludes `0.2.0` and would break dependents).

## Skills

This repo ships invocable skills under `.claude/skills/` — at least `rebel-package-dev` (the dev
loop + PHPStan-max recipes). Invoke it before non-trivial work.

## Session startup

At the start of each session, in this order:
1. Read `CLAUDE.md` (this file — AI working guide and design contract).
2. Read `AGENTS.md` (operational rules: branching, DoD, guardrails, design-lock).
3. Run `composer test` + `composer phpstan` to verify a clean baseline before making changes.

Key reminders: one PR per macro-task; sub-tasks are local commits on the branch; update
`CHANGELOG.md` + `README.md` at the end.
