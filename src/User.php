<?php
namespace SocketChat;

/**
 * Classe para armazenar informações do usuário
 */
class User 
{
    /**
     * Nome do usuário
     * @var string
     */
    public $name = '';

    /**
     * Status do usuário (online, offline)
     * @var string
     */
    public $status = 'online';

    /**
     * Identificador único do usuário (gerado no JavaScript)
     * @var string
     */
    public $uid = '';

    /**
     * IP do usuário
     * @var string
     */
    public $ip = null;

    /**
     * Cria instância da classe a partir de um objeto convertido pelo json_decode()
     */
    public static function create($jsonData): User
    {
        $user = new User();
        $user->name = $jsonData->name;
        $user->status = $jsonData->status;
        $user->uid = $jsonData->uid;
        $user->ip = $jsonData->ip;
        return $user;
    }
}
