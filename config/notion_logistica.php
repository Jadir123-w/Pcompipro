<?php

return [
    'token'   => env('NOTION_LOGISTICA_API_TOKEN'),
    'version' => env('NOTION_LOGISTICA_VERSION', '2022-06-28'),

    'databases' => [
        'pipeline' => env('NOTION_LOGISTICA_DB_PIPELINE'),
        'backlog'  => env('NOTION_LOGISTICA_DB_BACKLOG'),
    ],
];

