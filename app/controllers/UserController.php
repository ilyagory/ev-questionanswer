<?php

use App\Util\Arr;
use App\Util\NotFoundException;
use Phalcon\Config;
use Phalcon\Http\ResponseInterface;
use Phalcon\Logger\Adapter;
use Phalcon\Mvc\Controller;
use Phalcon\Mvc\View;

/**
 * Class UserController
 *
 * @property Adapter log
 * @property Config config
 */
class UserController extends Controller
{
    public function loginpostAction()
    {
        /**
         * @var User $u
         */
        $u = User::findByCreds(
            $this->request->getPost('username'),
            $this->request->getPost('password')
        );

        if ($u !== false) {
            $this->session->set('user', $u->id);
            return $this->response->redirect(
                $this->url->get(['for' => 'category.list']),
                false,
                303
            );
        }

        $this->response->setStatusCode(400);
        $this->view->pick('user/login');
        return $this->view->setVar('err', 'Wrong username or password.');
    }

    public function loginAction()
    {
        if ($this->session->get('user', 0)) {
            $this->response->redirect(
                $this->url->get(['for' => 'category.list']),
                false,
                301
            );
        }
    }

    public function logoutAction(): ResponseInterface
    {
        $this->session->destroy();
        return $this->response->redirect(
            $this->url->get(''),
            false,
            301
        );
    }

    public function listAction()
    {
        $users = User::find();
        $this->view->pick('user/index');
        $this->view->setVars([
            'users' => $users,
        ]);
    }

    protected function setPwd(User $u)
    {
        foreach (['pwd', 'pwdConfirm'] as $f) {
            if ($this->request->hasPost($f) && strlen($this->request->getPost($f, 'trim'))) {
                $u->{$f} = $this->request->getPost($f);
            }
        }
    }

    /**
     * @return ResponseInterface|View
     */
    public function createAction()
    {
        $u = new User;
        $validation = new Arr;
        if ($this->request->isPost()) {
            $this->setPwd($u);
            $saved = $u->create($this->request->getPost(), ['username', 'role']);

            if (!$saved) {
                foreach ($u->getMessages() as $message) {
                    $validation[$message->getField()] = $message->getMessage();
                }
            } else {
                return $this->response->redirect(
                    $this->url->get(['for' => 'admin.user.index']),
                    false,
                    303
                );
            }
        }
        return $this->view->setVars([
            'validation' => $validation,
            'item' => $u,
            'pwd' => [
                'min' => $this->config->path('user.pwd_min'),
                'max' => $this->config->path('user.pwd_max'),
            ],
        ]);
    }

    /**
     * @param int $id
     * @return ResponseInterface|View
     * @throws NotFoundException
     */
    function editAction(int $id)
    {
        $validation = new Arr;
        /**
         * @var User $u
         */
        $u = false;

        try {
            $u = User::findFirst(['id=?0', 'bind' => [$id]]);
        } catch (Exception $exception) {
            $this->log->debug($exception->getMessage());
        }

        if (!$u) throw new NotFoundException;

        if ($this->request->isPost()) {
            $this->setPwd($u);
            $saved = $u->update($this->request->getPost(), ['username', 'role']);

            if ($saved === true) {
                $this->flashSession->success('User saved');
                return $this->response->redirect(
                    $this->url->get(['for' => 'admin.user.edit', 'id' => $u->id]),
                    false,
                    303
                );
            } else {
                $this->response->setStatusCode(400);
                foreach ($u->getMessages() as $message) {
                    $validation[$message->getField()] = $message->getMessage();
                }
            }
        }
        
        return $this->view
            ->pick('user/create')
            ->setVars([
                'item' => $u,
                'validation' => $validation,
                'pwd' => [
                    'min' => $this->config->path('user.pwd_min'),
                    'max' => $this->config->path('user.pwd_max'),
                ],
            ]);
    }

    /**
     * @param int $id
     * @return ResponseInterface
     * @throws NotFoundException
     */
    function deleteAction(int $id): ResponseInterface
    {
        $u = false;

        try {
            $u = User::findFirst(['id=?0', 'bind' => [$id]]);
        } catch (Exception $exception) {
            $this->log->debug($exception->getMessage());
        }

        if (!$u) throw new NotFoundException;

        if (!$u->delete()) {
            foreach ($u->getMessages() as $message) {
                $this->log->warning($message->getMessage());
            }
        }
        return $this->response->redirect($this->url->get(['for' => 'admin.user.index']));
    }
}