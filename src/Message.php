<?php
namespace SocketChat;

class Message 
{
    /**
     * Tipo de mensagem
     * message: Mensagem de texto
     * file: Envio de arquivo
     * enter: Entrada na conexão
     * leave: Saída da conexão
     * users: Lista de usuários
     * log: Envio de logs
     * @var string
     */
    public $type;

    /**
     * Usuário enviado mensagem
     * @var User
     */
    public $user;

    /**
     * Mensagem/arquivo
     * @var mixed
     */
    public $value;

    /**
     * Destinatário
     * @var User
     */
    public $to;

    /**
     * Data de envio
     * @var string
     */
    public $date;

    /**
     * Transforma objeto do json_decode() em uma instância de Message
     * @param $jsonData
     * @return Message
     */
    public static function create($jsonData): Message
    {
        $msg = new Message();
        $msg->type = $jsonData->type;
        $msg->user = User::create($jsonData->user);
        $msg->value = $jsonData->value;
        $msg->date = $jsonData->date;
        $msg->to = null;
        if (property_exists($jsonData, 'to') && $jsonData->to) {
            $msg->to = User::create($jsonData->to);
        }
        return $msg;
    }
}
