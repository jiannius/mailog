<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mail logging
    |--------------------------------------------------------------------------
    |
    | Outgoing emails are logged to the database by default. Set "enabled" to
    | false (or MAILOG_ENABLED=false) to switch all logging off. "except" lists
    | mailer names and/or Mailable classes that should never be logged.
    |
    */

    'enabled' => env('MAILOG_ENABLED', true),

    'table' => 'mail_logs',

    'except' => [
        'mailers' => [],
        'mailables' => [],
    ],

];
