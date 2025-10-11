<?php

return [
    'model' => \YourPackage\Models\RotableApiKey::class,

    'encrypt_keys' => true,

    'db_connection' => env('DB_CONNECTION', 'mysql'),

    'timeout' => 10,
];
