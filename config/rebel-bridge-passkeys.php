<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Step-up drivers
    |--------------------------------------------------------------------------
    | Controls which step-up drivers this bridge registers into the Rebel step-up
    | DriverRegistry. A driver is only registered when:
    |   1. Its flag below is true, AND
    |   2. Its runtime prerequisite is satisfied (see each driver's note).
    */
    'drivers' => [

        /*
         | passkeys (bool, default: true)
         |
         | Register the WebAuthn/passkey step-up driver (key: 'passkeys', AAL3,
         | phishing-resistant). The driver is only registered when BOTH this flag
         | is true AND a PasskeyChallenger implementation is bound in the container.
         |
         | To enable:
         |   1. Set REBEL_PASSKEYS_DRIVER_PASSKEYS=true (the default).
         |   2. Bind Padosoft\Rebel\Bridge\Passkeys\Contracts\PasskeyChallenger in
         |      your AppServiceProvider (see README for the spatie/laravel-passkeys
         |      example or bring your own WebAuthn library).
         |
         | When the PasskeyChallenger is not bound the driver is silently skipped —
         | the bridge still installs and other drivers keep working.
         */
        'passkeys' => env('REBEL_PASSKEYS_DRIVER_PASSKEYS', true),

    ],

];
