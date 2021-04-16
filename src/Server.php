<?php

namespace SocketChat;

use Exception;
use SplObjectStorage;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;

final class Server implements MessageComponentInterface
{
    private $clients;
    private $mappedConn = [];
    private $userInfo = [];

    public function __construct()
    {
        $this->clients = new SplObjectStorage();
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn);
        $this->mappedConn[$conn->resourceId] = $conn;
        $this->userInfo[$conn->resourceId] = new User();
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $data = Message::create(json_decode($msg));
        switch ($data->type) {
            case 'enter':
                // Mensagem de entrada no chat
                $this->userInfo[$from->resourceId] = $data->user;
                $this->sendEnteringUser();
                $this->sendUsersList();
                break;
            case 'message':
                foreach ($this->clients as $client) {
                    $client->send($msg);
                }
                break;
        }
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $this->sendLeavingUser($conn);
        unset($this->mappedConn[$conn->resourceId]);
        unset($this->userInfo[$conn->resourceId]);
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
        $data->value = $this->userInfo;
        // Atualiza a lista de usuários de todos os clientes
        foreach ($this->clients as $client) {
            $client->send(json_encode($data));
        }
    }

    public function sendEnteringUser(ConnectionInterface $conn): void
    {
        foreach ($this->clients as $client) {
            $data = new Message();
            $data->type = 'enter';
            $data->value = $this->userInfo[$conn->resourceId];
            $client->send(json_encode($data));
        }
    }

    public function sendLeavingUser(ConnectionInterface $conn): void
    {
        foreach ($this->clients as $client) {
            if ($client->resourceId !== $conn->resourceId) {
                $data = new Message();
                $data->type = 'leave';
                $data->value = $this->userInfo[$conn->resourceId];
                $client->send(json_encode($data));
            }
        }
    }
}
