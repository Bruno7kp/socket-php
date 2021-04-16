<?php
namespace SocketChat;

class User {
    public $name;

    public static function create($jsonData) {
        $user = new User();
        $user->name = $jsonData->name;
        return $user;
    }

}
