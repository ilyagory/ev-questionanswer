<?php
/**
 * Entry point of MVC application
 */

use App\Util\Acl;
use Phalcon\Acl\Adapter;
use Phalcon\Acl\Adapter\Memory;
use Phalcon\Acl\Resource;
use Phalcon\Acl\Role;
use Phalcon\Di;
use Phalcon\Di\FactoryDefault;
use Phalcon\Events\Event;
use Phalcon\Events\Manager;
use Phalcon\Filter;
use Phalcon\Flash\Session;
use Phalcon\Http\Response\Cookies;
use Phalcon\Loader;
use Phalcon\Mvc\Application;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Mvc\Router;
use Phalcon\Mvc\View;
use Phalcon\Session\Adapter\Files;
use Phalcon\Session\AdapterInterface;

define('BASE_PATH', dirname(__DIR__) . '/');
define('APP_PATH', BASE_PATH . 'app/');
define('ENV_PRODUCTION', getenv('PHP_ENV') !== 'development');

$loader = new Loader();
$loader->registerDirs([
    APP_PATH . '/controllers/',
    APP_PATH . '/models/',
    APP_PATH . '/util/',
]);
$loader->registerNamespaces([
    'App\Util' => APP_PATH . '/util/'
]);
$loader->register();

$di = new FactoryDefault();

require_once APP_PATH . 'bootstrap.php';
require_once BASE_PATH . '/vendor/autoload.php';

$di->set('filter', function () {
    $f = new Filter;
    $c = HTMLPurifier_Config::createDefault();
    $c->loadArray([
        'HTML.Allowed' => 'p, em, strong, img[src|alt|width|height], span[style]',
        'CSS.AllowedProperties' => 'text-decoration',
        'HTML.TidyLevel' => 'heavy',
        'AutoFormat.RemoveEmpty' => true,
        'AutoFormat.RemoveEmpty.RemoveNbsp' => true,
        'URI.DisableExternalResources' => true,
    ]);
    $purifier = new HTMLPurifier($c);

    $f->add('text', function ($value) use ($purifier, $f) {
        $txt = $purifier->purify($value);
        // no text just tags
        return empty($f->sanitize(strip_tags($txt, '<img>'), 'trim')) ? null : $txt;
    });

    return $f;
});
$di->set('flashSession', function () {
    $fs = new Session;
    $fs->setCssClasses([
        'error' => 'alert alert-error',
        'success' => 'alert alert-success',
        'notice' => 'alert alert-notice',
        'warning' => 'alert alert-warning'
    ]);
    return $fs;
});
$di->set('url', function () {
    $url = new Phalcon\Mvc\Url;
    $url->setBaseUri("{$_SERVER['REQUEST_SCHEME']}://{$_SERVER['HTTP_HOST']}/");
    return $url;
});
$di->set('view', function () {
    $em = new Manager;
    $em->attach('view:beforeRender', function (Event $event, View $view) {
        $u = Di::getDefault()->get('session')->get('user-ob', false);
        $view->setVar('user', $u);
    });
    $v = new View;
    $v->setViewsDir(APP_PATH . '/views/');
    $v->setEventsManager($em);
    return $v;
});
$di->set('dispatcher', function () {
    $em = new Manager;
    $em->attach('dispatch:beforeException', function (Event $event, Dispatcher $dispatcher, Exception $exception) {
        $dispatcher->forward([
            'controller' => 'index',
            'action' => 'error',
            'params' => [$exception, $event],
        ]);
        return false;
    });
    $em->attach('dispatch:beforeExecuteRoute', function (Event $event, Dispatcher $dispatcher) {
        /**
         * @var AdapterInterface $session
         */
        $session = $dispatcher->getDI()->get('session');
        /**
         * @var \Phalcon\Http\Request $req
         */
        $req = $dispatcher->getDI()->get('request');
        $uid = (int)$session->get('user', 0);
        $u = User::findFirst(['id=?0', 'bind' => [$uid]]);

        $ctrl = $dispatcher->getControllerName();
        $actn = $dispatcher->getActionName();

        $savePage = [
            'category' => [
                'show'
            ],
            'topic' => [
                'show'
            ],
        ];
        $skipRedirect = [
            'user' => [
                'login',
                'loginPost',
            ],
            'index' => [
                'error',
                'attachment'
            ],
            'category' => [
                'index',
                'show'
            ],
            'topic' => [
                'show'
            ],
            'comment' => [
                'create',
                'show'
            ]
        ];

        if ($u !== false) $session->set('user-ob', $u);

        foreach ($savePage as $ctrlName => $actions) {
            if (!($ctrlName == $ctrl && in_array($actn, $actions))) continue;
            $pageQuery = (int)$req->getQuery('page', 'int', 1);
            $id = $dispatcher->getParam('id');
            $idx = "pager:$ctrlName.$actn.$id";

            if ($pageQuery > 1) {
                $session->set($idx, $pageQuery);
            } elseif ($session->has($idx)) {
                $session->remove($idx);
            }
        }

        foreach ($skipRedirect as $ctrlName => $actions) {
            if ($ctrlName == $ctrl && in_array($actn, $actions)) return true;
        }

        if (!$u) {
            $dispatcher->getDI()->get('response')->redirect(['for' => 'user.login'], false, 301);
            return false;
        }
        return true;
    });
    $dis = new Dispatcher;
    $dis->setEventsManager($em);
    return $dis;
});
$di->set('cookies', function () {
    $c = new Cookies;
    $c->useEncryption(ENV_PRODUCTION);
    return $c;
});
$di->set('session', function () {
    $sess = new Files;
    $sess->start();
    return $sess;
});
$di->set('acl', function () {
    $acl = new Memory;
    $acl->setDefaultAction(\Phalcon\Acl::DENY);

    $roleAdmin = new Role('role' . User::ROLE_ADMIN);
    $roleModer = new Role('role' . User::ROLE_MODER);
    $roleUser = new Role('role' . User::ROLE_USER);

    $category = new Resource(Category::class);
    $topic = new Resource(Topic::class);
    $comment = new Resource(Comment::class);
    $admin = new Resource('admin');

    $acl->addResource($category, ['delete', 'create', 'edit']);
    $acl->addResource($topic, ['delete', 'create']);
    $acl->addResource($comment, ['delete', 'edit']);
    $acl->addResource($admin, ['admin']);

    $acl->addRole($roleAdmin);
    $acl->addRole($roleModer);
    $acl->addRole($roleUser);

    $acl->addInherit($roleAdmin, $roleUser);
    $acl->addInherit($roleModer, $roleUser);

    // ADMIN
    $acl->allow($roleAdmin->getName(), $category->getName(), ['delete', 'create', 'edit']);
    $acl->allow($roleAdmin->getName(), $topic->getName(), ['delete', 'create']);
    $acl->allow($roleAdmin->getName(), $comment->getName(), ['delete', 'edit']);
    $acl->allow($roleAdmin->getName(), $admin->getName(), ['admin']);
    // MODER
    $acl->allow($roleModer->getName(), $topic->getName(), 'create');
    $acl->allow($roleModer->getName(), $topic->getName(), 'delete', function (Topic $topic) {
        return Acl::moderAccess($topic->category->moder);
    });
    $acl->allow($roleModer->getName(), $comment->getName(), ['edit', 'delete'], function (Comment $comment) {
        $moders = $comment->topic->category->moder;
        if (empty($moders)) return false;
        $author = $comment->owner;

        if (!empty($author) && Acl::userAccess($author)) return true;

        return Acl::moderAccess($moders);
    });
    // USER
    $acl->allow($roleUser->getName(), $topic->getName(), 'create');
    $acl->allow($roleUser->getName(), $comment->getName(), 'edit', function (Comment $comment) {
        $author = $comment->owner;
        if (empty($author)) return false;
        return Acl::userAccess($author);
    });
    return $acl;
});
$di->set('random', function () {
    return new \Phalcon\Security\Random;
});

$di->set('router', function () {
    $r = new Router(false);
    $r->removeExtraSlashes(true);
    $r->setUriSource(Router::URI_SOURCE_SERVER_REQUEST_URI);
    $maxInt = strlen(PHP_INT_MAX);

//    Category
    $r->addGet('/', 'Category::index')->setName('category.list');
    $r->addGet("/category/{id:[0-9]{1,$maxInt}}", 'Category::show')->setName('category.show');

//    Topic
    $r->addGet("/topic/{id:[0-9]{1,$maxInt}}", 'Topic::show')->setName('topic.show');
    $r->addGet("/topic/{id:[0-9]{1,$maxInt}}/delete", 'Topic::delete')->setName('topic.delete');
    $r->add("/topic/new/{category:[0-9]{1,$maxInt}}", 'Topic::create', ['GET', 'POST'])->setName('topic.create');
    $r->addGet("/attachment/{id:[0-9]{1,$maxInt}}", ['controller' => 'index', 'action' => 'attachment'])->setName('attach.get');

//    Comment
    $r->add("/comment/new/{topic:[0-9]{1,$maxInt}}", 'Comment::create', ['GET', 'POST'])->setName('comment.create');
    $r->addGet("/comment/{id:[0-9]{1,$maxInt}}", 'Comment::show')->setName('comment.show');
    $r->addGet("/comment/{id:[0-9]{1,$maxInt}}/delete", 'Comment::delete')->setName('comment.delete');
    $r->add("/comment/{id:[0-9]{1,$maxInt}}/edit", 'Comment::edit')->setName('comment.edit');

//    User
    $r->addPost('/login', 'User::loginPost');
    $r->addGet('/login', 'User::login')->setName('user.login');
    $r->addGet('/logout', 'User::logout')->setName('user.logout');


//    ADMIN
    $adminr = new Router\Group();
    $adminr->beforeMatch(function () {
        /**
         * @var Adapter $acl
         */
        $acl = Di::getDefault()->get('acl');
        /**
         * @var User $u
         */
        $u = Di::getDefault()->get('session')->get('user-ob');
        if (empty($u)) return false;
        return $acl->isAllowed($u->role, 'admin', 'admin');
    });

    $adminr->setPrefix('/admin');

//    User
    $adminr->addGet('/user', 'User::list')->setName('admin.user.index');
    $adminr->add('/user/new', 'User::create', ['GET', 'POST'])->setName('admin.user.create');
    $adminr->add("/user/{id:[0-9]{1,$maxInt}}", 'User::edit', ['GET', 'POST'])->setName('admin.user.edit');
    $adminr->addGet("/user/{id:[0-9]{1,$maxInt}}/delete", 'User::delete')->setName('admin.user.delete');
//    Category
    $adminr->addGet("/category/{id:[0-9]{1,$maxInt}}/delete", 'Category::delete')->setName('admin.category.delete');
    $adminr->add('/category/new', 'Category::create', ['GET', 'POST'])->setName('admin.category.create');
    $adminr->add("/category/{id:[0-9]{1,$maxInt}}", 'Category::edit', ['GET', 'POST'])->setName('admin.category.edit');
    $adminr->add('/category', 'Category::adminIndex')->setName('admin.category.index');

    $r->mount($adminr);

    return $r;
});

#------------------------------------------------------------------------------
try {
    (new Application($di))->handle()->send();
} catch (Exception $exception) {
    syslog(LOG_DEBUG, $exception);
}
