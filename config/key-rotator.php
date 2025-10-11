<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    |
    | The database connection to use for storing rotable API keys.
    | Set to null to use the default connection.
    |
    */

    'database_connection' => env('KEY_ROTATOR_DB_CONNECTION', null),

    /*
    |--------------------------------------------------------------------------
    | Encrypt Keys
    |--------------------------------------------------------------------------
    |
    | Whether to encrypt API keys when storing them in the database.
    | When enabled, keys will be encrypted using Laravel's encryption.
    |
    */

    'encrypt_keys' => env('KEY_ROTATOR_ENCRYPT_KEYS', true),

    /*
    |--------------------------------------------------------------------------
    | Table Name
    |--------------------------------------------------------------------------
    |
    | The name of the database table used to store rotable API keys.
    |
    */

    'table_name' => env('KEY_ROTATOR_TABLE_NAME', 'rotable_api_keys'),

];
