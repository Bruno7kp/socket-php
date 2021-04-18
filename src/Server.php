<?php

namespace SocketChat;

use DateTime;
use Exception;
use SplObjectStorage;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

final class Server implements MessageComponentInterface
{
    private $clients;
    private $mappedConn = [];
    private $userInfo = [];
    private $logger;
    private $server;

    public function __construct()
    {
        $this->clients = new SplObjectStorage();
        $this->logger = new Logger('chat_log');
        
        $logFormat = "%message%\n";
        $formatter = new LineFormatter($logFormat,null,true,true);
        $handler = new StreamHandler(__DIR__.'/../chat_log.log', Logger::INFO);
        $handler->setFormatter($formatter);
        $this->logger->pushHandler($handler);
    }

    public function log($senderIp, $senderName, $receiverIp, $receiverName, $action): void
    {
        // Exemplo: 06/04/2021; 12:54; 192.168.10.50; luciano; 200.10.10.10; servidor; login
        $now = new DateTime();
        $date = $now->format('d/m/Y');
        $hour = $now->format('H:i');
        $this->logger->info("$date; $hour; $senderIp; $senderName; $receiverIp; $receiverName; $action");
        $this->sendLogs();
    }

    public function sendLogs(): void
    {
        if ($this->server instanceof ConnectionInterface) {
            $data = new Message();
            $data->type = 'log';
            $data->value = file_get_contents(__DIR__.'/../chat_log.log');
            $this->server->send(json_encode($data));
        }
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn);
    }

    public function getConnFromResourceId($id) 
    {
        foreach ($this->clients as $client) {
            if ($client->resourceId === $id) {
                return $client;
            }
        }
        return null;
    }

    public function getUserFromConn(ConnectionInterface $conn)
    {
        if (array_key_exists($conn->resourceId, $this->mappedConn)) {
            $uid = $this->mappedConn[$conn->resourceId];
            return $this->userInfo[$uid];
        }
        return null;
    }

    public function getConnFromUser(User $user)
    {
        foreach ($this->mappedConn as $resourceId => $uid) {
            if ($uid === $user->uid) {
                return $this->getConnFromResourceId($resourceId);
            }
        }
        return null;
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $data = Message::create(json_decode($msg));
        switch ($data->type) {
            case 'enter':
                // Mensagem de entrada no chat
                $data->user->ip = $from->remoteAddress;
                if ($data->user->uid !== 'server') {
                    $this->mappedConn[$from->resourceId] = $data->user->uid;
                    $this->userInfo[$data->user->uid] = $data->user;
                    $hostAddr = gethostname();
                    $ipAddr = gethostbyname($hostAddr);
                    $this->log($from->remoteAddress, $data->user->name, $ipAddr, 'server', 'login');
                    $this->sendEnteringUser($from);
                } else {
                    $this->server = $from;
                    $this->sendLogs();
                }
                $this->sendUsersList();
                break;
            case 'message':
            case 'file':
                if ($data->to instanceof User) {
                    $from->send($msg);
                    $to = $this->getConnFromUser($data->to);
                    if ($to !== null) {
                        $to->send($msg);
                    }
                    if ($data->type === 'message') {
                        $this->log($from->remoteAddress, $data->user->name, $data->to->ip, $data->to->name, 'msg:'.$data->value);
                    } else {
                        $this->log($from->remoteAddress, $data->user->name, $data->to->ip, $data->to->name, 'arq:'.$data->value->name);
                    }
                } else {
                    $ips = [];
                    $names = [];
                    foreach ($this->clients as $client) {
                        $client->send($msg);
                        $u = $this->getUserFromConn($client);
                        if ($u !== null && $u->uid !== $data->user->uid) {
                            $ips[] = $u->ip;
                            $names[] = $u->name;
                        }
                    }
                    if ($data->type === 'message') {
                        $this->log($from->remoteAddress, $data->user->name, implode('-', $ips), implode('-', $names), 'msg:'.$data->value);
                    } else {
                        $this->log($from->remoteAddress, $data->user->name, implode('-', $ips), implode('-', $names), 'arq:'.$data->value->name);
                    }
                }
                break;
        }
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $u = $this->getUserFromConn($conn);
        if ($u !== null) {
            $u->status = 'offline';
            $this->userInfo[$u->uid] = $u;
            $hostAddr = gethostname();
            $ipAddr = gethostbyname($hostAddr);
            $this->log($conn->remoteAddress, $u->name, $ipAddr, 'server', 'logoff');
            $this->sendLeavingUser($conn);
            unset($this->mappedConn[$conn->resourceId]);
            // unset($this->userInfo[$conn->resourceId]); perde conexão mas continua na lista como 'offline'
        }
        
        $this->clients->detach($conn);
        $this->sendUsersList();
    }

    public function onError(ConnectionInterface $conn, Exception $exception): void
    {
        $conn->close();
    }

    public function sendUsersList(): void
    {
        $data = new Message();
        $data->type = 'users';
        $data->value = array_values($this->userInfo);
        // Atualiza a lista de usuários de todos os clientes
        foreach ($this->clients as $client) {
            $client->send(json_encode($data));
        }
    }

    public function sendEnteringUser(ConnectionInterface $conn): void
    {
        $user = $this->getUserFromConn($conn);
        foreach ($this->clients as $client) {
            $data = new Message();
            $data->type = 'enter';
            $data->value = $user;
            $client->send(json_encode($data));
        }
    }

    public function sendLeavingUser(ConnectionInterface $conn): void
    {
        $user = $this->getUserFromConn($conn);
        foreach ($this->clients as $client) {
            if ($client->resourceId !== $conn->resourceId) {
                $data = new Message();
                $data->type = 'leave';
                $data->value = $user;
                $client->send(json_encode($data));
            }
        }
    }
}
