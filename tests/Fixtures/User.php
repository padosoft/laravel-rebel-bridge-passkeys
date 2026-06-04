<?php

declare(strict_types=1);

namespace Padosoft\Rebel\Bridge\Passkeys\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Minimal Eloquent user model for bridge tests.
 *
 * No two_factor_* columns needed; the passkey challenger is handled
 * entirely by the FakePasskeyChallenger seam.
 */
class User extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = false;
}
