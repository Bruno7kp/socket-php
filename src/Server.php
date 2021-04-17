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
        $uid = $this->mappedConn[$conn->resourceId];
        return $this->userInfo[$uid];
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
                $this->mappedConn[$from->resourceId] = $data->user->uid;
                $this->userInfo[$data->user->uid] = $data->user;
                $this->sendEnteringUser($from);
                $this->sendUsersList();
                break;
            case 'message':
                if ($data->to instanceof User) {
                    $from->send($msg);
                    $to = $this->getConnFromUser($data->to);
                    if ($to !== null) {
                        $to->send($msg);
                    }
                } else {
                    foreach ($this->clients as $client) {
                        $client->send($msg);
                    }
                }
                
                break;
        }
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $u = $this->getUserFromConn($conn);
        $u->status = 'offline';
        $this->userInfo[$u->uid] = $u;
        $this->sendLeavingUser($conn);
        unset($this->mappedConn[$conn->resourceId]);
        // unset($this->userInfo[$conn->resourceId]); perde conexão mas continua na lista como 'offline'
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
