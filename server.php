<?php
date_default_timezone_set('America/Sao_Paulo');

require './vendor/autoload.php';

use SocketChat\Server;

$app = new Ratchet\App('localhost', 9990);
$app->route('/chat', new Server, ['*']);
$app->run();
