<?php

use Phalcon\Config;
use Phalcon\Di;
use Phalcon\Mvc\Model;
use Phalcon\Validation;
use Phalcon\Validation\Validator\Confirmation;
use Phalcon\Validation\Validator\PresenceOf;
use Phalcon\Validation\Validator\StringLength;

/**
 * Class User
 *
 * @property string role
 * @property string roleName
 */
class User extends Model
{
    const ROLE_ADMIN = 3;
    const ROLE_MODER = 2;
    const ROLE_USER = 1;

    static $roleNames = [
        self::ROLE_USER => 'User',
        self::ROLE_MODER => 'Moderator',
        self::ROLE_ADMIN => 'Administrator',
    ];

    /**
     * @var int|null
     */
    public $id;
    /**
     * @var string
     */
    public $pwd;
    /**
     * @var string
     */
    public $pwdConfirm;
    /**
     * @var int
     */
    protected $role;
    /**
     * @var string
     */
    public $username;

    function initialize()
    {
        $this->keepSnapshots(true);
    }

    function getRole(): string
    {
        return 'role' . $this->role;
    }

    function setRole(int $i)
    {
        $this->role = $i;
    }

    function getRoleName(): string
    {
        return self::$roleNames[$this->role];
    }

    public function validation(): bool
    {
        $validator = new Validation;
        /**
         * @var Config $cfg
         */
        $cfg = $this->getDI()->get('config');

        // Password
        if (!$this->id || $this->hasChanged('pwd')) {
            $pwdMin = $cfg->path('user.pwd_min');
            $pwdMax = $cfg->path('user.pwd_max');
            $pwdRuls = [
                new PresenceOf([
                    'message' => 'Password should not be empty.',
                ]),
                new StringLength([
                    'min' => $pwdMin,
                    'max' => $pwdMax,
                    'messageMinimum' => "Minimum password length is {$pwdMin}.",
                    'messageMaximum' => "Maximum password length is {$pwdMax}.",
                ]),
                new Confirmation([
                    'with' => 'pwdConfirm',
                    'message' => 'Passwords should match.'
                ]),
            ];
            $validator->rules('pwd', $pwdRuls);
        }

        // Username
        $nameMin = $cfg->path('user.name_min');
        $nameMax = $cfg->path('user.name_max');
        $validator->rules('username', [
            new PresenceOf([
                'message' => 'Username should not be empty',
            ]),
            new Validation\Validator\Uniqueness([
                'message' => 'Username should be unique',
            ]),
            new StringLength([
                'min' => $nameMin,
                'max' => $nameMax,
                'messageMinimum' => "Minimum username length is {$nameMin}.",
                'messageMaximum' => "Maximum username length is {$nameMax}.",
            ]),
        ]);

        return $this->validate($validator);
    }

    function beforeSave()
    {
        if (!$this->id || $this->hasChanged('pwd')) {
            $this->pwd = $this->getDI()->get('security')->hash($this->pwd);
        }

        if (empty($this->role)) $this->role = self::ROLE_USER;
    }

    static function findByCreds(string $uname, string $pwd)
    {
        $di = Di::getDefault();
        $user = User::findFirst(['username=?0', 'bind' => $uname]);
        if (!$user) return false;

        if (!$di->get('security')->checkHash($pwd, $user->pwd)) return false;
        return $user;
    }
}