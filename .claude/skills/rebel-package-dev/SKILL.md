---
name: rebel-package-dev
description: Use when adding or changing code in any padosoft/laravel-rebel-* package — encodes the suite's TDD loop, PHPStan-max recipes, security/telemetry rules, and the branch→PR→CI→tag/release Definition of Done.
---

# Developing a Laravel Rebel package

You are extending a package in the **Laravel Rebel** enterprise-auth suite. Follow this exactly.

## The loop (per sub-task)

1. **Write the test first** (Pest + Testbench): happy path, **auth/fail-closed**, Throwable
   fail-closed, empty state. Run `composer test`.
2. Implement with the conventions: `declare(strict_types=1)`, `final` classes, constructor
   promotion, English docblocks.
3. Make the gate green: `composer test` · `composer phpstan` (**level max**) · `composer pint -- --test`.
4. Commit on the feature branch. Repeat.

## PHPStan level max — fix the cause, never suppress

Forbidden: `@phpstan-ignore*`, baseline entries, `assert()`/inline `@var` to override inference,
type-casts/`mixed` widening just to silence. Instead:

- Narrow before casting: `is_scalar($x) ? (string) $x : null`.
- `json_decode($s, true)` returns `array<array-key, mixed>` — type/annotate accordingly.
- `app()->make('request')` is typed `Illuminate\Http\Request` (no redundant `instanceof`).
- `cursor()` for memory-safe scans; `withoutGlobalScopes()` for cross-tenant admin reads.
- Nested Eloquent `where(fn ($q) => …)` closures receive `Illuminate\Database\Eloquent\Builder`.
- Larastan's view-string rule → return `response()->view('ns::x', $data)`.
- `Aal::tryFrom()` (fail-closed), not `from()`. Add `@property` blocks to Eloquent models.
- Run with `--memory-limit=512M`.

## Security & telemetry (non-negotiable)

- Identifiers/IP/User-Agent are **keyed HMACs** (core `KeyedHasher`); never cleartext PII.
- **Never log the raw WebAuthn assertion or credential material** — audit metadata is sanitized
  by `Redactor`. Never put `$input` (assertion JSON) into `AuditEvent.metadata`.
- Record events through the core `AuditLogger` (persisted to `rebel_auth_events`; configurable
  **sync|queue**, Horizon-ready). For this bridge: capture `started`/`verified`/`failed` with
  assurance + AMR so the step-up funnel panel and compliance AMR breakdown light up.
- Leave a field empty only if unsupported, and show an honest empty state.

## Definition of Done (per change)

- One feature branch → one PR to `main`; CI matrix **PHP 8.3/8.4/8.5 × Laravel 12/13** green.
- README + CHANGELOG updated; squash-merge.
- **Release every change:** `git tag vX.Y.Z && git push origin vX.Y.Z` + `gh release create`.
  Stay within `0.1.x` (`^0.1` excludes `0.2.0`). CI pins BOTH `illuminate/contracts` and
  `illuminate/support`; `composer check` uses `pint --test`.

## Tooling notes

- `php`/`node`/`composer` run in PowerShell (Herd), not the Bash tool.
- spatie `hasAssets()` publishes to `public/vendor/{shortName}` (the short name strips `laravel-`).
- Package migrations load by filename order — don't let an `add_*` migration sort before its
  `create_*` (merge the column into `create_*` for 0.x, or prefix to sort after).
- This package has no migrations — WebAuthn credential storage belongs to the WebAuthn library
  (e.g. `spatie/laravel-passkeys` creates its own `passkeys` table).
