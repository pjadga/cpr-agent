<?php

return [
    'default_collection' => 'wiki',

    'collections' => [

        // Renders resources under /wiki as the in-app documentation site
        // (see App\Http\Controllers\WikiController and resources/views/wiki).
        'wiki' => [
            'disk' => 'wiki',
        ],

    ],
];
