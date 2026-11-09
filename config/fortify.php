<?php

use Laravel\Fortify\Features;

return [

    

    'guard' => 'web',

    

    'passwords' => 'users',

    

    'username' => 'email',

    'email' => 'email',

    

    'lowercase_usernames' => true,

    

    'home' => '/dashboard',

    

    'prefix' => '',

    'domain' => null,

    

    'middleware' => ['web'],

    

    'limiters' => [
        'login' => 'login',
        'two-factor' => 'two-factor',
        'passkeys' => 'passkeys',
    ],

    

    'views' => true,

    

    'passkeys' => [
        'relying_party_id' => parse_url(config('app.url'), PHP_URL_HOST),
        'allowed_origins' => [config('app.url')],
        'timeout' => 60000,
    ],

    

    'features' => [
        
        
        
        
        
        
        
        
        
        Features::resetPasswords(),
        
        Features::updateProfileInformation(),
        Features::updatePasswords(),
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
            
        ]),
        Features::passkeys([
            'confirmPassword' => true,
        ]),
    ],

];
