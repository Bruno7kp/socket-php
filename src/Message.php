<?php
namespace SocketChat;

class Message {
    /**
     * @var string
     */
    public $type;

    /**
     * @var User
     */
    public $user;

    /**
     * @var mixed
     */
    public $value;

    /**
     * @param $jsonData
     * @return Message
     */
    public static function create($jsonData): Message
    {
        $msg = new Message();
        $msg->type = $jsonData->type;
        $msg->user = User::create($jsonData->user);
        $msg->value = $jsonData->value;
        return $msg;
    }
}
