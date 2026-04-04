<?php
return [
    // "middleware" => [
    //     \Juzdy\Http\Router\Router::class => [
    //         'priority' => PHP_INT_MAX - 200,
    //     ],
    // ],

    "http-router" => [
        "handler-query-param" => "_h",
        "route" => [
            "/http-router-ping" => [
                "GET" => \Juzdy\Http\Handler\Demo\HttpRouterPingHandler::class,
            ]
        ],
    ]
];