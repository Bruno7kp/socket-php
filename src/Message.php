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
     * @var User
     */
    public $to;

    /**
     * @var string
     */
    public $date;

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
        $msg->date = $jsonData->date;
        $msg->to = null;
        if (property_exists($jsonData, 'to') && $jsonData->to) {
            $msg->to = User::create($jsonData->to);
        }
        return $msg;
    }
}
