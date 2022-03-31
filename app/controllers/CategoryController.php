<?php

use App\Util\Arr;
use App\Util\NotFoundException;
use Phalcon\Acl\AdapterInterface;
use Phalcon\Config\Adapter\Ini;
use Phalcon\Http\ResponseInterface;
use Phalcon\Logger\Adapter;
use Phalcon\Mvc\Controller;
use Phalcon\Mvc\View;
use Phalcon\Paginator\Adapter\QueryBuilder;

/**
 * Class CategoryController
 *
 * @property Ini config
 * @property Adapter log
 * @property AdapterInterface acl
 */
class CategoryController extends Controller
{
    function indexAction()
    {
        $cats = Category::find(['order' => 'id desc']);
        $this->view->setVars([
            'catlist' => $cats,
        ]);
    }

    /**
     * @param int $id
     * @return ResponseInterface|View
     * @throws NotFoundException
     */
    function showAction(int $id)
    {
        $cat = false;
        try {
            $cat = Category::findFirst([
                'id=?0',
                'bind' => [$id]
            ]);
        } catch (Exception $exception) {
            $this->log->debug($exception->getMessage());
        }
        if (!$cat) throw new NotFoundException;
        $pageNum = (int)$this->request->getQuery('page', 'int', 1);
        if ($pageNum <= 0) $pageNum = 1;

        $topics = $this->modelsManager->createBuilder()
            ->columns('t.*')
            ->from(['t' => Topic::class])
            ->leftJoin(Comment::class, 't.id = c.topic', 'c')
            ->groupBy('t.id')
            ->orderBy('coalesce(max(c.created), t.created) DESC')
            ->where('t.category = :category:', ['category' => $id])
            ->limit($this->config->path('app.topics_perpage'));

        $paginator = new QueryBuilder([
            'builder' => $topics,
            'limit' => $this->config->path('app.topics_perpage'),
            'page' => $pageNum,
        ]);
        $page = $paginator->paginate();

        if ($page->last > 0) {
            if ($pageNum < $page->first) throw new NotFoundException;
            if ($pageNum > 1 && $pageNum > $page->last) {
                $pageNum = $page->last;
                return $this->response->redirect(
                    $this->url->get(['for' => 'category.show', 'id' => $id], ['page' => $pageNum > 1 ? $pageNum : null])
                );
            }
        }

        return $this->view->setVars([
            'category' => $cat,
            'topics' => $page,
        ]);
    }

    /**
     * @throws NotFoundException
     */
    function createAction()
    {
        $cat = new Category;
        $validation = new Arr;

        if (!$this->acl->isAllowed($this->session->get('user-ob')->role, Category::class, 'create')) throw new NotFoundException;

        if ($this->request->isPost()) {
            $cat->title = $this->request->getPost('title', ['string', 'trim']);
            if (!$cat->save()) {
                foreach ($cat->getMessages() as $message) {
                    $validation[$message->getField()] = $message->getMessage();
                }
            } else {
                $this->saveModers($cat);
                $this->response->redirect($this->url->get(['for' => 'admin.category.index']), false, 303);
                return;
            }
        }

        $this->view->setVars([
            'category_snapshot' => $cat->getSnapshotData(),
            'moders' => $this->getModersList($cat),
            'validation' => $validation,
            'category' => $cat,
        ]);
    }

    /**
     * @param int $id
     * @return ResponseInterface|View
     * @throws NotFoundException
     */
    function editAction(int $id)
    {
        /**
         * @var Category $cat
         */
        $cat = false;
        $validation = new Arr;

        try {
            $cat = Category::findFirst(['id=?0', 'bind' => [$id]]);
        } catch (Exception $exception) {
            $this->log->debug($exception->getMessage());
        }
        if (!$cat) throw new NotFoundException;

        if ($this->request->isPost()) {
            $cat->title = $this->request->getPost('title', ['string', 'trim']);
            if (!$cat->save()) {
                foreach ($cat->getMessages() as $message) {
                    $validation[$message->getField()] = $message->getMessage();
                }
            } else {
                $this->saveModers($cat);
                return $this->response->redirect(
                    $this->url->get(['for' => 'admin.category.index'])
                );
            }
        }
        return $this->view->pick('category/create')->setVars([
            'category' => $cat,
            'category_snapshot' => $cat->getSnapshotData(),
            'moders' => $this->getModersList($cat),
            'validation' => $validation,
        ]);
    }

    /**
     * @param int $id
     * @return ResponseInterface
     * @throws NotFoundException
     */
    function deleteAction(int $id): ResponseInterface
    {
        /**
         * @var User $user
         */
        $user = $this->session->get('user-ob');
        $cat = false;
        try {
            $cat = Category::findFirst(['id= ?0', 'bind' => [$id]]);
        } catch (Exception $exception) {
            $this->log->debug($exception->getMessage());
        }

        if (!$cat || !$this->acl->isAllowed($user->role, Category::class, 'delete')) throw new NotFoundException;

        if (!$cat->delete()) {
            foreach ($cat->getMessages() as $message) {
                $this->log->warning($message->getMessage());
            }
        }
        return $this->response->redirect($this->url->get(['for' => 'admin.category.index']));
    }

    function adminIndexAction()
    {
        $cats = Category::find(['order' => 'id desc']);
        $this->view
            ->pick('category/admin/index')
            ->setVars([
                'catlist' => $cats,
            ]);
    }

    /**
     * @param Category $cat
     * @return array|array[]
     */
    protected function getModersList(Category $cat): array
    {
        $modersres = User::find(['role=?0', 'bind' => [User::ROLE_MODER]]);
        $currentModers = array_map(function ($moder) {
            return $moder['id'];
        }, $cat->moder->toArray());
        return array_map(function ($moder) use ($currentModers) {
            return [
                'id' => $moder['id'],
                'username' => $moder['username'],
                'selected' => in_array($moder['id'], $currentModers),
            ];
        }, $modersres->toArray());
    }

    /**
     * @param Category $cat
     */
    protected function saveModers(Category $cat)
    {
        $moderz = $this->request->getPost('moders');
        /**
         * @var User $moder
         */
        foreach ($cat->moder as $i => $moder) {
            if (in_array($moder->id, $moderz)) {
                $j = array_search($moder->id, $moderz);
                if ($j !== false) unset($moderz[$j]);
                continue;
            }
            $mc = new ModerCategory();
            $mc->moder = $moder->id;
            $mc->category = $cat->id;
            $mc->delete();
        }

        if (empty($moderz)) return;
        foreach ($moderz as $moderId) {
            $mc = new ModerCategory;
            $mc->moder = $moderId;
            $mc->category = $cat->id;
            if (!$mc->save()) {
                foreach ($mc->getMessages() as $message) {
                    $this->log->warning($message->getMessage());
                }
            }
        }
    }
}