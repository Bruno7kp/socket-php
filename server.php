<?php

require './vendor/autoload.php';

use SocketChat\Server;

$app = new Ratchet\App('localhost', 9990);
$app->route('/chat', new Server, ['*']);
$app->run();
