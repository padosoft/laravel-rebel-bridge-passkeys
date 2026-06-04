# AGENTS.md — operational rules for `padosoft/laravel-rebel-bridge-passkeys`

> This file is the **working contract** for every session on this repo (human or AI agent).

## Stack & target

- **Laravel 12 + 13**, **PHP 8.3 + 8.4 + 8.5**. Constraint: `illuminate/support: ^12.0|^13.0`, `php: ^8.3`.
- Testbench `^10.0|^11.0`, **Pest 4**, **Larastan 3** (PHPStan **level max**), **Pint** (preset `laravel`), `spatie/laravel-package-tools`.
- Namespace PSR-4: `Padosoft\Rebel\Bridge\Passkeys\`. Composer name: `padosoft/laravel-rebel-bridge-passkeys`.

## Branching & PR

- One branch per macro-task: `feat/<macro>` from `main`.
- Sub-tasks are **local commits** on the macro branch (no PR per sub-task).
- When macro is complete: push → **one PR `feat/<macro>` → `main`** → CI gate → merge → tag/release.
- Commit: gitmoji + clear message; `Co-Authored-By` as per harness rules.

## Definition of Done

### Local loop per sub-task (no PR)

1. Implement + **guardrail:**
   - **Pest** for all logic;
   - only code (no UI) → no Playwright needed for this package.
2. Green locally: `composer test` · `composer phpstan` (max) · `composer pint -- --test`.
3. Commit locally. Update `CHANGELOG.md` if user-facing behaviour changed.

### GitHub gate once (PR macro→main)

1. `git push` the branch; `gh pr create` (feat/<macro> → main).
2. Wait for **CI all green** + any review comments addressed.
3. Green + 0 open comments → merge (squash). Then `git tag vX.Y.Z` + `gh release create`.

## Guardrails = mandatory, not optional

Every sub-task must have: a precise objective, implementation details, and unit tests (Pest always).
Nothing is "done" without green tests.

## README = final mandatory sub-task before PR (DIDACTIC)

The README must be **verbose and didactic**: a junior / non-auth-expert must immediately understand
what passkeys/WebAuthn/phishing-resistance/AAL3 mean, how the step-up ceremony works step-by-step,
how to install and configure (every config key in a table), with ≥4 copy-paste examples and a
competitor card-battle table.

## Security (design-lock)

- ULID/UUID identifiers, keyed HMACs for IP/UA, never cleartext PII.
- **Never log the raw WebAuthn assertion or credential material.** The `AuditEvent.metadata` field
  must only contain non-sensitive telemetry (subject type/ID, purpose, AMR, channel).
- Fail-closed: unbound challenger → driver not registered; null reference → verify false; Throwable → false + audit.
- Test OTP/WebAuthn flows with fakes; never hit real WebAuthn APIs in unit tests.

## Banner & assets

Banner shared in `resources/screenshoots/Laravel-Rebel-banner.png`
(source: `Downloads\laravel-rebel\Laravel-Rebel-banner.png`).
