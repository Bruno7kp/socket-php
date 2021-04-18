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

/**
 * Classe que roda todas as funcionalidades do servidor
 */
final class Server implements MessageComponentInterface
{
    /**
     * Armazena todas as conexões abertas ao servidor
     * @var SplObjectStorage
     */
    private $clients;

    /**
     * Mapeia a relação de conexão e usuário no formato [id da conexão => id do usuário]
     * @var array
     */
    private $mappedConn = [];

    /**
     * Armazena informações de cada usuário pelo seu id [id do usuário => informações do usuário]
     * @var User[]
     */
    private $userInfo = [];

    /**
     * Armazena as informações do servidor em log
     * @var Logger
     */
    private $logger;

    /**
     * Conexão da visão do servidor, é um cliente como outros usuários, mas é tratado de forma diferente 
     * (mostrando logs, usuários, sem enviar/receber mensagens)
     * @var ConnectionInterface
     */
    private $server;

    /**
     * Define as configurações do log
     */
    public function __construct()
    {
        $this->clients = new SplObjectStorage();
        $this->logger = new Logger('chat_log');
        
        $logFormat = "%message%\n";
        $formatter = new LineFormatter($logFormat, null, true, true);
        $handler = new StreamHandler(__DIR__.'/../chat_log.log', Logger::INFO);
        $handler->setFormatter($formatter);
        $this->logger->pushHandler($handler);
    }

    /**
     * Salva log no formato
     * Ex: 06/04/2021; 12:54; 192.168.10.50; luciano; 200.10.10.10; servidor; login
     */
    public function log($senderIp, $senderName, $receiverIp, $receiverName, $action): void
    {
        $now = new DateTime();
        $date = $now->format('d/m/Y');
        $hour = $now->format('H:i');
        $this->logger->info("$date; $hour; $senderIp; $senderName; $receiverIp; $receiverName; $action");
        $this->sendLogs();
    }

    /**
     * Envia logs para o cliente com a visão do servidor
     */
    public function sendLogs(): void
    {
        if ($this->server instanceof ConnectionInterface) {
            $data = new Message();
            $data->type = 'log';
            $data->value = file_get_contents(__DIR__.'/../chat_log.log');
            $this->server->send(json_encode($data));
        }
    }

    /**
     * Abre e armazena conexão
     */
    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn);
    }

    /**
     * Busca conexão pelo seu id
     */
    public function getConnFromResourceId($id) 
    {
        foreach ($this->clients as $client) {
            if ($client->resourceId === $id) {
                return $client;
            }
        }
        return null;
    }

    /**
     * Busca usuário pela conexão
     */
    public function getUserFromConn(ConnectionInterface $conn)
    {
        if (array_key_exists($conn->resourceId, $this->mappedConn)) {
            $uid = $this->mappedConn[$conn->resourceId];
            return $this->userInfo[$uid];
        }
        return null;
    }

    /**
     * Busca conexão pelo usuário
     */
    public function getConnFromUser(User $user)
    {
        foreach ($this->mappedConn as $resourceId => $uid) {
            if ($uid === $user->uid) {
                return $this->getConnFromResourceId($resourceId);
            }
        }
        return null;
    }

    /**
     * Define a transmissão de dados
     */
    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $data = Message::create(json_decode($msg));
        switch ($data->type) {
            case 'enter':
                // Entrada no chat
                $data->user->ip = $from->remoteAddress;
                if ($data->user->uid !== 'server') {
                    // Adiciona usuário ao mapeamento e na lista de usuários
                    $this->mappedConn[$from->resourceId] = $data->user->uid;
                    $this->userInfo[$data->user->uid] = $data->user;
                    $hostAddr = gethostname();
                    $ipAddr = gethostbyname($hostAddr);
                    // Log de login
                    $this->log($from->remoteAddress, $data->user->name, $ipAddr, 'server', 'login');
                    // Avisa clientes que um novo usuário entrou
                    $this->sendEnteringUser($from);
                } else {
                    // Se está na visão do servidor, envia os logs logo que abre a conexão
                    $this->server = $from;
                    $this->sendLogs();
                }
                // Envia lista de usuários atualizada
                $this->sendUsersList();
                break;
            case 'message':
            case 'file':
                if ($data->to instanceof User) {
                    // Mensagem privada
                    # $from->send($msg);
                    $to = $this->getConnFromUser($data->to);
                    if ($to !== null) {
                        $to->send($msg);
                    }
                    // Log do envio
                    if ($data->type === 'message') {
                        $this->log($from->remoteAddress, $data->user->name, $data->to->ip, $data->to->name, 'msg:'.$data->value);
                    } else {
                        $this->log($from->remoteAddress, $data->user->name, $data->to->ip, $data->to->name, 'arq:'.$data->value->name);
                    }
                } else {
                    // Mensagem no grupo
                    $ips = [];
                    $names = [];
                    foreach ($this->clients as $client) {
                        $u = $this->getUserFromConn($client);
                        if ($u !== null && $u->uid !== $data->user->uid) {
                            $ips[] = $u->ip;
                            $names[] = $u->name;
                            $client->send($msg);
                        }
                    }
                    // Log de envio
                    if ($data->type === 'message') {
                        $this->log($from->remoteAddress, $data->user->name, implode('-', $ips), implode('-', $names), 'msg:'.$data->value);
                    } else {
                        $this->log($from->remoteAddress, $data->user->name, implode('-', $ips), implode('-', $names), 'arq:'.$data->value->name);
                    }
                }
                break;
        }
    }

    /**
     * Ao fechar conexão, mantém usuário na lista com seu id, podendo entrar com uma nova conexão com o mesmo id de usuário
     * Também lista usuário como desconectado para outros usuários
     */
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

    /**
     * Fecha conexão caso ocorra erro
     */
    public function onError(ConnectionInterface $conn, Exception $exception): void
    {
        $conn->close();
    }

    /**
     * Envia lista de usuários para todos os clientes
     */
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

    /**
     * Envia novo usuário para todos os clientes
     */
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

    /**
     * Envia saída de usuário para todos os clientes
     */
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
