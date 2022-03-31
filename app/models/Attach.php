<?php

use Phalcon\Http\Request\FileInterface;
use Phalcon\Mvc\Model;

/**
 * Class Attach
 *
 * @property string filepath
 * @property bool isImage
 */
class Attach extends Model
{
    public $filename;
    public $topic;
    public $origname;
    public $mime;
    public $id;

    /**
     * @var FileInterface
     */
    private $_file;

    function initialize()
    {
        $this->belongsTo(
            'topic',
            Topic::class,
            'id'
        );
    }

    /**
     * @param FileInterface $file
     * @param Topic $topic
     * @return Attach
     */
    static function fromFile(FileInterface $file, Topic $topic): Attach
    {
        $attach = new self;
        $attach->_file = $file;
        $attach->topic = $topic->id;
        $attach->origname = $file->getName();
        $attach->mime = $file->getRealType();
        return $attach;
    }

    /**
     * @throws Exception
     */
    function beforeValidationOnCreate()
    {
        $this->filename = $this->getDI()->get('random')->hex(20);
    }

    function getFilepath()
    {
        $dir = $this->getDI()->get('config')->path('app.attach_storage_dir');
        return BASE_PATH . '/' . $dir . '/' . $this->filename;
    }

    function beforeCreate()
    {
        if ($this->_file->isUploadedFile()) {
            if (!$this->_file->moveTo($this->filepath)) {
                $this->appendMessage(new Model\Message("Cannot upload file " . $this->_file->getName()));
                return false;
            }
        } else {
            rename($this->_file->getTempName(), $this->filepath);
        }

        return true;
    }

    function afterDelete()
    {
        unlink($this->filepath);
    }

    function getIsImage()
    {
        return strpos($this->mime, 'image/') === 0;
    }
}