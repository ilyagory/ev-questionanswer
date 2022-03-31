<?php

namespace App\Util;

use Phalcon\Di;
use User;

class Acl
{
    static protected function getUser(): User
    {
        return Di::getDefault()->get('session')->get('user-ob');
    }

    static function userAccess(User $party)
    {
        return self::getUser()->id === $party->id;
    }

    static function moderAccess(\Phalcon\Mvc\Model\Resultset\Simple $moders): bool
    {
        $uo = self::getUser();
        /**
         * @var User $moder
         */
        foreach ($moders as $moder) {
            if ($uo->id === $moder->id) return true;
        }
        return false;
    }
}