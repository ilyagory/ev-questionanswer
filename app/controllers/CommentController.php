<?php

use App\Util\Arr;
use App\Util\NotFoundException;
use Phalcon\Acl\Adapter as AclAdapter;
use Phalcon\Http\ResponseInterface;
use Phalcon\Logger\Adapter as LogAdapter;
use Phalcon\Mvc\Controller;
use Phalcon\Mvc\View;

/**
 * Class CommentController
 * @property AclAdapter acl
 * @property LogAdapter log
 */
class CommentController extends Controller
{
    /**
     * @param int $topicId
     * @return ResponseInterface|View
     * @throws NotFoundException
     */
    function createAction(int $topicId)
    {
        $topic = false;
        try {
            $topic = Topic::findFirst(['id=?0', 'bind' => [$topicId]]);
        } catch (Exception $exception) {
            $this->log->debug($exception->getMessage());
        }
        if (!$topic) throw new NotFoundException;

        $comment = new Comment;
        $category = $topic->getRelated('category');
        $validation = new Arr;
        $comment->topicId = $topic->id;
        $quotComment = false;

        if ($this->request->isPost()) {
            $comment->text = $this->request->getPost('text', ['string', 'trim']);

            $uid = (int)$this->session->get('user', 0);
            if ($uid) $comment->author = $uid;

            $quoted = (int)$this->request->getPost('quoted', 'int', 0);
            if (!empty($quoted)) {
                try {
                    $quotComment = Comment::findFirst(['id=?0', 'bind' => [$quoted]]);
                } catch (Exception $exception) {
                    $this->log->debug($exception->getMessage());
                }
                if ($quotComment) $comment->quoted = $quoted;
            }

            if (!$comment->save()) {
                foreach ($comment->getMessages() as $message) {
                    $validation[$message->getField()] = $message->getMessage();
                }
            } else {
                $pgidx = "pager:topic.show.{$topic->id}";
                $urlArgs = [];
                if ($this->session->has($pgidx)) {
                    $pg = (int)$this->session->get($pgidx, 1);
                    if ($pg > 1) $urlArgs['page'] = $pg;
                }
                return $this->response->redirect(
                    $this->url->get(['for' => 'topic.show', 'id' => $topicId], $urlArgs),
                    false,
                    303
                );
            }
        } else {
            $quoted = (int)$this->request->getQuery('quoted', 'int', 0);
            if (!empty($quoted)) {
                try {
                    $quotComment = Comment::findFirst(['id=?0', 'bind' => [$quoted]]);
                } catch (Exception $exception) {
                    $this->log->debug($exception->getMessage());
                }

                if ($quotComment) $comment->quoted = $quoted;
            }
        }

        return $this->view->setVars([
            'topic' => $topic,
            'comment' => $comment,
            'category' => $category,
            'validation' => $validation,
        ]);
    }

    /**
     * @param int $commentId
     * @throws NotFoundException
     */
    function showAction(int $commentId)
    {
        $comment = false;
        $related = false;
        try {
            $comment = Comment::findFirst(['id=?0', 'bind' => [$commentId]]);
            $related = Comment::find([
                'conditions' => 'quoted=?0',
                'bind' => [$commentId],
                'order' => 'created'
            ]);
        } catch (Exception $exception) {
            $this->log->debug($exception->getMessage());
        }
        if (!$comment) throw new NotFoundException;
        /**
         * @var Topic $topic
         */
        $topic = $comment->getRelated('topic');
        $this->view->setVars([
            'comment' => $comment,
            'topic' => $topic,
            'category' => $topic->getRelated('category'),
            'related' => $related,
        ]);
    }

    /**
     * @param int $commentId
     * @throws NotFoundException
     */
    function deleteAction(int $commentId)
    {
        /**
         * @var User $user
         */
        $user = $this->session->get('user-ob');
        $comment = false;

        try {
            $comment = Comment::findFirst(['id=?0', 'bind' => [$commentId]]);
        } catch (Exception $exception) {
            $this->log->debug($exception->getMessage());
        }

        if (!$comment || !$this->acl->isAllowed($user->role, Comment::class, 'delete', ['comment' => $comment])) throw new NotFoundException;

        if (!$comment->delete()) {
            foreach ($comment->getMessages() as $message) {
                $this->log->warning($message->getMessage());
            }
        }

        $pgidx = "pager:topic.show.{$comment->topic->id}";
        $urlArgs = [];
        if ($this->session->has($pgidx)) {
            $pg = (int)$this->session->get($pgidx, 1);
            if ($pg > 1) $urlArgs['page'] = $pg;
        }

        $this->response->redirect(
            $this->url->get(['for' => 'topic.show', 'id' => $comment->topic->id], $urlArgs)
        );
    }

    /**
     * @param int $commentId
     * @return ResponseInterface|View
     * @throws NotFoundException
     */
    function editAction(int $commentId)
    {
        /**
         * @var User $user
         */
        $user = $this->session->get('user-ob');
        /**
         * @var Comment $comment
         */
        $comment = false;
        $validation = new Arr;

        try {
            $comment = Comment::findFirst(['id=?0', 'bind' => [$commentId]]);
        } catch (Exception $exception) {
            $this->log->debug($exception->getMessage());
        }

        if (!$comment || !$this->acl->isAllowed($user->role, Comment::class, 'edit', ['comment' => $comment])) throw new NotFoundException;

        if ($this->request->isPost()) {
            $comment->text = $this->request->getPost('text', ['trim', 'string']);
            if (!$comment->save()) {
                foreach ($comment->getMessages() as $message) {
                    $validation[$message->getField()] = $message->getMessage();
                }
            } else {

                $pgidx = "pager:topic.show.{$comment->topic->id}";
                $urlArgs = [];
                if ($this->session->has($pgidx)) {
                    $pg = (int)$this->session->get($pgidx, 1);
                    if ($pg > 1) $urlArgs['page'] = $pg;
                }

                return $this->response->redirect(
                    $this->url->get(['for' => 'topic.show', 'id' => $comment->topic->id], $urlArgs),
                    false,
                    303
                );
            }
        }

        $this->view->pick('comment/create');
        return $this->view->setVars([
            'validation' => $validation,
            'comment' => $comment,
        ]);
    }
}