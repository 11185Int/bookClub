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

        //configuration
        'config' => include __DIR__.'/../config/config.php',
    ],

    //handle Exception and return json
    'errorHandler' => function ($c) {
        return function ($request, $response, $exception) use ($c) {
            $ret = $c['response']->withStatus(200)
                ->withJson(array(
                    'status' => $exception->getCode() ?: 99999,
                    'message' => $exception->getMessage(),
                ));
            $logger = new \CP\common\Logger();
            $logger->log($request, $ret);
            return $ret;
        };
    },
    //handle page not found
    'notFoundHandler' => function ($c) {
        return function ($request, $response) use ($c) {
            return $c['response']->withStatus(200)
                ->withJson(array(
                    'status' => 40400,
                    'message' => 'Page Not Found.',
                ));
        };
    },
    //handle method not allowed
    'notAllowedHandler' => function ($c) {
        return function ($request, $response, $methods) use ($c) {
            return $c['response']->withStatus(200)
                ->withJson(array(
                    'status' => 40500,
                    'message' => 'Method not allowed. Must be one of: '. implode(',', $methods),
                ));
        };
    },
];
