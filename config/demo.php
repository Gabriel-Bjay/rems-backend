<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public demo
    |--------------------------------------------------------------------------
    |
    | Setting DEMO_PASSWORD turns the demo on: the database seeder loads the
    | demo portfolio with one login per role below, and visitors can sign in
    | as those accounts from the login page without knowing the password.
    | Leave it empty on a deployment that holds real data.
    |
    */

    'password' => env('DEMO_PASSWORD'),

    // One login per role. Admin is never offered for demo sign-in.
    'accounts' => [
        'owner' => 'demo.owner@rems.test',
        'agent' => 'demo.agent@rems.test',
        'tenant' => 'demo.tenant@rems.test',
    ],

    // Demo sessions expire after this many hours.
    'token_hours' => 12,

];
