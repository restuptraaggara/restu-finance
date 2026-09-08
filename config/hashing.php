<?php

return [

    'driver' => env('HASH_DRIVER', 'bcrypt'),

    'bcrypt' => [
        'rounds' => (int) (env('BCRYPT_ROUNDS') ?: 10),
        'verify' => false,
    ],

    'argon' => [
        'memory' => (int) (env('ARGON_MEMORY') ?: 65536),
        'threads' => (int) (env('ARGON_THREADS') ?: 1),
        'time' => (int) (env('ARGON_TIME') ?: 4),
        'verify' => false,
    ],

    'rehash_on_login' => true,

];
