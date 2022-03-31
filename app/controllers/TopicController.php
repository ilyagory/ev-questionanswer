<?php

use App\Util\Arr;
use App\Util\NotFoundException;
use Phalcon\Acl\Adapter;
use Phalcon\Config\Adapter\Ini;
use Phalcon\Http\Request\File;
use Phalcon\Http\Request\FileInterface;
use Phalcon\Http\ResponseInterface;
use Phalcon\Mvc\Controller;
use Phalcon\Mvc\View;
use Phalcon\Paginator\Adapter\QueryBuilder;
use Phalcon\Security\Random;

/**
 * Class TopicController
 *
 * @property Ini config
 * @property Adapter acl
 * @property \Phalcon\Logger\Adapter log
 * @property Random random
 */
class TopicController extends Controller
{

    /**
     * @param int $category
     * @return ResponseInterface|View
     * @throws NotFoundException|\Phalcon\Security\Exception
     */
    function createAction(int $category)
    {
        $topic = new Topic;
        $validation = new Arr;
        $tmpAttaches = [];
        $cat = false;

        try {
            $cat = Category::findFirst(['id=?0', 'bind' => [$category]]);
        } catch (Exception $exception) {
            $this->log->debug($exception->getMessage());
        }

        if (!$cat) throw new NotFoundException;

        $topic->category = $cat;

        if ($this->request->isPost()) {
            $attaches = $this->request->getUploadedFiles(true);
            $rawText = $this->request->getPost('text');
            $topic->title = $this->request->getPost('title', ['trim', 'string']);
            $topic->text = $this->request->getPost('text', ['text', 'trim']);
            $tmpAttaches = $this->request->getPost('tmp') ?: [];
            if (!$topic->save()) {
                if (!empty($attaches)) {
                    $newTmpAttaches = $this->saveTmpFiles($attaches) ?: [];
                    if (!empty($newTmpAttaches)) {
                        $tmpAttaches = array_merge($tmpAttaches, $newTmpAttaches);
                    }
                }

                $_tmp = array_reduce($tmpAttaches, function ($carry, $item) {
                    $carry[$item['name']] = $item['url'];
                    return $carry;
                }, []);
                $topic->text = $this->replaceImgOnCreate($rawText, $_tmp);

                foreach ($topic->getMessages() as $vm) {
                    $validation[$vm->getField()] = $vm->getMessage();
                }
            } else {
                $error = false;
                $imagesAttached = [];
                $doc = $this->request->getServer('DOCUMENT_ROOT');

                foreach ($tmpAttaches as $i => $tmp) {
                    $finf = new SplFileInfo($doc . $tmp['url']);
                    if ($finf->isFile() && $finf->isWritable()) {
                        $f = new File([
                            'tmp_name' => $finf->getRealPath(),
                            'size' => $finf->getSize(),
                            'name' => $tmp['name'],
                            'type' => $finf->getType(),
                            'error' => UPLOAD_ERR_OK,
                        ]);
                        $attaches[] = $f;
                    }
                }

                foreach ($attaches as $file) {
                    $attach = Attach::fromFile($file, $topic);
                    $k = $file->getName();
                    if (!$file->isUploadedFile()) {
                        $k = str_replace($this->request->getServer('DOCUMENT_ROOT'), '', $file->getTempName());
                    }
                    if (!$attach->create()) {
                        foreach ($attach->getMessages() as $message) {
                            $this->log->error($message->getMessage());
                        }
                        $error = true;
                        continue;
                    }
                    if (!$attach->isImage) continue;
                    $imagesAttached[$k] = $this->url->get(['for' => 'attach.get', 'id' => $attach->id]);
                }

                if ($error) {
                    $this->flashSession->warning('Some attachments not loaded due to uploading error.');
                } else {
                    $topic->text = $this->replaceImgOnCreate($topic->text, $imagesAttached);
                    if ($topic->hasChanged('text') && !$topic->save()) {
                        foreach ($topic->getMessages() as $vm) {
                            $this->log->error($vm->getMessage());
                        }
                    }
                }

                $pgidx = "pager:topic.show.{$topic->id}";
                $urlArgs = [];
                if ($this->session->has($pgidx)) {
                    $pg = (int)$this->session->get($pgidx, 1);
                    if ($pg > 1) $urlArgs['page'] = $pg;
                }

                return $this->response->redirect(
                    $this->url->get(['for' => 'topic.show', 'id' => $topic->id], $urlArgs),
                    false,
                    303
                );
            }
        }
        
        $hx = \App\Util\Html::humanFilesize(ini_get('upload_max_filesize'));
        return $this->view->setVars([
            'topic' => $topic,
            'validation' => $validation,
            'category' => $cat,
            'tmpAttaches' => $tmpAttaches,
            'maxFilesize' => $hx,
        ]);
    }

    /**
     * @param int $id
     * @return ResponseInterface|View
     * @throws NotFoundException
     */
    function showAction(int $id)
    {
        $topic = false;
        try {
            $topic = Topic::findFirst(['id=?0', 'bind' => [$id]]);
        } catch (Exception $exception) {
            $this->log->debug($exception->getMessage());
        }

        if (!$topic) throw new NotFoundException;

        $page = (int)$this->request->getQuery('page', 'int', 1);
        if ($page <= 0) $page = 1;

        $qBuilder = $this->modelsManager->createBuilder()
            ->from(Comment::class)
            ->where('topic=?0', [$id])
            ->orderBy('created');

        $pager = new QueryBuilder([
            'builder' => $qBuilder,
            'limit' => $this->config->path('app.comments_perpage'),
            'page' => $page,
        ]);
        $comments = $pager->paginate();

        if ($comments->last > 0) {
            if ($page < $comments->first) throw new NotFoundException;
            if ($page > 1 && $page > $comments->last) {
                $page--;
                return $this->response->redirect(
                    $this->url->get(['for' => 'topic.show', 'id' => $id], ['page' => $page > 1 ? $page : null])
                );
            }
        }

        return $this->view->setVars([
            'comments' => $comments,
            'topic' => $topic,
            'category' => $topic->getRelated('category'),
        ]);
    }

    /**
     * @param int $id
     * @throws NotFoundException
     */
    function deleteAction(int $id)
    {
        $topic = false;

        try {
            $topic = Topic::findFirst(['id=?0', 'bind' => [$id]]);
        } catch (Exception $exception) {
            $this->log->debug($exception->getMessage());
        }

        if (!$topic) throw new NotFoundException;
        $u = $this->session->get('user-ob');
        /**
         * @var Category $cat
         */
        $cat = $topic->category;

        if ($this->acl->isAllowed($u->role, Topic::class, 'delete')) {
            if (!$topic->delete()) {
                foreach ($topic->getMessages() as $message) {
                    $this->log->warning($message->getMessage());
                }
            }
        }

        $pgidx = "pager:category.show.{$cat->id}";
        $urlArgs = [];
        if ($this->session->has($pgidx)) {
            $pg = (int)$this->session->get($pgidx, 1);
            if ($pg > 1) $urlArgs['page'] = $pg;
        }

        $this->response->redirect(
            $this->url->get(['for' => 'category.show', 'id' => $cat->id], $urlArgs),
            false,
            301
        );
    }

    protected function replaceImgOnCreate(string $txt, array $attaches): string
    {
        if (strpos($txt, '<img') === false) return $txt;
        return preg_replace_callback("/(<img[^>]*src *= *[\"']?)([^\"']*)/i", function ($matches) use ($attaches) {
            if (!isset($attaches[$matches[2]])) return $matches[0];
            return $matches[1] . $attaches[$matches[2]];
        }, $txt);
    }


    /**
     * @param FileInterface[] $files
     * @return array
     * @throws \Phalcon\Security\Exception
     */
    protected function saveTmpFiles(array $files): array
    {
        $pth = $this->config->path('app.attach_storage_tmp');
        $doc = $this->request->getServer('DOCUMENT_ROOT');

        $res = [];
        foreach ($files as $file) {
            $tmp = Path::join_paths($pth, $this->random->hex(20) . '.' . pathinfo($file->getName(), PATHINFO_EXTENSION));
            if (!$file->moveTo(Path::join_paths($doc, $tmp))) {
                $this->log->error("Cannot save file");
                continue;
            }
            $res[] = [
                'url' => Path::join_paths('/', $tmp),
                'name' => $file->getName(),
            ];
        }
        return $res;
    }

}