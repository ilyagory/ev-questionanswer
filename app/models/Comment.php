<?php

use Phalcon\Mvc\Model;
use Phalcon\Validation;
use Phalcon\Validation\Validator\PresenceOf;
use Phalcon\Validation\Validator\StringLength;

/**
 * Class Comment
 *
 * @property Topic topic
 * @property User owner
 * @property Comment quotedComment
 */
class Comment extends Model
{
    public $created;
    public $text;
    protected $topic;
    public $author;
    public $id;
    /**
     * @var int
     */
    public $quoted;

    function initialize()
    {
        $this->belongsTo(
            'topic',
            Topic::class,
            'id',
            [
                'reusable' => true,
                'alias' => 'topic',
                'foreignKey' => [
                    'action' => Model\Relation::NO_ACTION
                ],
            ]
        );
        $this->belongsTo(
            'quoted',
            Comment::class,
            'id',
            [
                'alias' => 'quotedComment',
            ]
        );
        $this->belongsTo(
            'author',
            User::class,
            'id',
            [
                'reusable' => true,
                'alias' => 'owner'
            ]
        );
    }

    function setTopicId(int $id)
    {
        $this->topic = $id;
    }

    /**
     * @throws Exception
     */
    function beforeValidationOnCreate()
    {
        $dt = new DateTime('now', new DateTimeZone('UTC'));
        $this->created = $dt->format('Y-m-d H:i:s');
    }

    function validation(): bool
    {
        $validator = new Validation;
        $cfg = $this->getDI()->get('config');
        $lengths = [
            'mintext' => $cfg->path('app.min_content_length'),
            'maxtext' => $cfg->path('app.max_content_length'),
        ];

        $validator->rules(
            'text',
            [
                new PresenceOf([
                    'message' => 'Topic text should not be empty.'
                ]),
                new StringLength([
                    'min' => $lengths['mintext'],
                    'max' => $lengths['maxtext'],
                    'messageMinimum' => "Minimum length: {$lengths['mintext']}",
                    'messageMaximum' => "Maximum length: {$lengths['maxtext']}",
                ])
            ]
        );

        $oldText = $this->text;
        $this->text = trim($this->text, " \t\n\r\0\x0B\xC2\xA0");
        $this->text = preg_replace("/\s{2,}/u", " ", $this->text);
        
        $isValid = $this->validate($validator);

        $this->text = $oldText;
        
        return $isValid;
    }
}