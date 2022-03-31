<?php

use Phalcon\Cli\Task;

/**
 * Class UserTask
 * @property \Phalcon\Security\Random random
 */
class UserTask extends Task
{
    /**
     * @param array $params
     * @throws Exception
     */
    function createAction(array $params = [])
    {
        $pwdGenerated = null;
        $uname = reset($params);
        if (empty($uname)) throw new Exception('Username[:Password] must be given');
        $uname = explode(':', $uname);
        $user = new User;
        $user->username = $uname[0];

        if (empty($uname[1])) {
            $pwdGenerated = $this->random->hex(20);
            $user->pwd = $pwdGenerated;
        } else {
            $user->pwd = $uname[1];
        }
        $user->pwdConfirm = $user->pwd;

        if (!empty($params[1])) {
            switch ($params[1]) {
                case "moder":
                    $user->role = User::ROLE_MODER;
                    break;
                case "admin":
                    $user->role = User::ROLE_ADMIN;
                    break;
                default:
                    $user->role = User::ROLE_USER;
            }
        }

        if (!$user->create()) {
            echo "Cannot create User:\n";
            foreach ($user->getMessages() as $message) {
                echo $message->getMessage() . "\n";
            }
            die(1);
        }

        echo "New user (id: {$user->id}) created.\n";
        if ($pwdGenerated !== null) {
            echo "Password generated: {$pwdGenerated}\n";
        }
    }
}