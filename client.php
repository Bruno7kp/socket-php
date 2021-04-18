<?php
date_default_timezone_set('America/Sao_Paulo');

require './vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

function endsWith($string, $endString)
{
    $len = strlen($endString);
    if ($len == 0) {
        return true;
    }
    return (substr($string, -$len) === $endString);
}

$server = new React\Http\Server($loop, function (Psr\Http\Message\ServerRequestInterface $request) {
    $path = $request->getUri()->getPath();
    $type = 'text/html';
    if ($path === '/') {
        $path = 'index.html';
    } else if ($path === '/server') {
        $path = 'server.html';
    } else if (endsWith($path, '.js')) {
        $type = 'text/javascript';
    } else if (endsWith($path, '.css')) {
        $type = 'text/css';
    } else if (endsWith($path, '.log')) {
        $type = 'text/plain';
    } else {
        $path = 'index.html';
    }
    return new React\Http\Message\Response(
        200,
        array(
            'Content-Type' => $type
        ),
        file_get_contents(trim($path, '/'))
    );
});

$socket = new React\Socket\Server(8081, $loop);
$server->listen($socket);


$loop->run();