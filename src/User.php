<?php
namespace SocketChat;

class User {
    public $name = '';
    public $status = 'online';
    public $uid = 0;

    public static function create($jsonData) {
        $user = new User();
        $user->name = $jsonData->name;
        $user->status = $jsonData->status;
        $user->uid = $jsonData->uid;
        return $user;
    }

}
