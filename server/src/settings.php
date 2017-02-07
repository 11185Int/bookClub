<?php
return [
    'settings' => [
        'displayErrorDetails' => true, // set to false in production
        'addContentLengthHeader' => false, // Allow the web server to send the content-length header

        // Renderer settings
        'renderer' => [
            'template_path' => __DIR__ . '/../templates/',
        ],

        // Monolog settings
        'logger' => [
            'name' => 'slim-app',
            'path' => __DIR__ . '/../logs/app.log',
            'level' => \Monolog\Logger::DEBUG,
        ],
    ],

    //handle Exception and return json
    'errorHandler' => function ($c) {
        return function ($request, $response, $exception) use ($c) {
            return $c['response']->withStatus(200)
                ->withJson(array(
                    'status' => $exception->getCode() ?: 99999,
                    'message' => $exception->getMessage(),
                ));
        };
    }
];
