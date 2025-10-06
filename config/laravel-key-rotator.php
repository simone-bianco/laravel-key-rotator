<?php

return [
    'model' => \SimoneBianco\LaravelKeyRotator\Models\RotableKey::class,

    'encrypt_keys' => false,

    'db_connection' => env('DB_CONNECTION', 'mysql'),

    'timeout' => 10,
];
