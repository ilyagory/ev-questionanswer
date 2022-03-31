<?php

use App\Util\HttpException as UtilHttpException;
use App\Util\NotFoundException;
use Phalcon\Http\ResponseInterface;
use Phalcon\Mvc\Controller;
use Phalcon\Mvc\Dispatcher;

/**
 * Class IndexController
 *
 * @property Phalcon\Logger\AdapterInterface log
 */
class IndexController extends Controller
{
    /**
     * @param Exception $exception
     */
    public function errorAction(Exception $exception)
    {
        $this->log->error(
            $exception->getMessage() .
            "\n" .
            $exception->getTraceAsString()
        );
        $status = 500;
        $text = UtilHttpException::TXT_INTERNAL_SERVER;
        if ($exception instanceof UtilHttpException) {
            $status = $exception->getCode();
            $text = $exception->getMessage();
        }
        if ($exception instanceof \Phalcon\Mvc\Dispatcher\Exception) {
            switch ($exception->getCode()) {
                case Dispatcher::EXCEPTION_HANDLER_NOT_FOUND:
                case Dispatcher::EXCEPTION_ACTION_NOT_FOUND:
                    $status = 404;
                    $text = UtilHttpException::TXT_NOT_FOUND;
            }
        }
        $this->response->setStatusCode($status);
        $this->view->setVar('error', $text);
    }

    /**
     * @param int $id
     * @return ResponseInterface
     * @throws NotFoundException
     */
    public function attachmentAction(int $id): ResponseInterface
    {
        /**
         * @var Attach $attach
         */
        $attach = false;

        try {
            $attach = Attach::findFirst(['id=?0', 'bind' => [$id]]);
        } catch (Exception $exception) {
            $this->log->debug($exception->getMessage());
        }

        if (!$attach) throw new NotFoundException;

        $contentAttachment = strpos($attach->mime, 'image/') === false;

        return $this->response
            ->setFileToSend($attach->filepath, $attach->origname, $contentAttachment)
            ->setContentType($attach->mime)
            ->setContentLength(filesize($attach->filepath));
    }
}