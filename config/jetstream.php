<?php

use Laravel\Jetstream\Features;
use Laravel\Jetstream\Http\Middleware\AuthenticateSession;

return [

    

    'stack' => 'livewire',

    

    'middleware' => ['web'],

    'auth_session' => AuthenticateSession::class,

    

    'guard' => 'sanctum',

    

    'features' => [
        
        Features::profilePhotos(),
        
        
        
        
        
    ],

    

    'profile_photo_disk' => 'public',

];
