<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ENAMAD (e-trust) badge
    |--------------------------------------------------------------------------
    |
    | Values come from .env and may be overridden in Admin → Settings → Trust.
    | Never invent id/code — paste the official values from enamad.ir panel.
    | id and Code appear in public HTML (they are not panel credentials).
    |
    */

    'enabled' => env('ENAMAD_ENABLED', false),

    'id' => env('ENAMAD_ID', ''),

    'code' => env('ENAMAD_CODE', ''),

    /** Optional full trustseal URL; id/Code are parsed when set. */
    'url' => env('ENAMAD_URL', ''),

    'samandehi_url' => env('SAMANDEHI_URL', ''),

];
