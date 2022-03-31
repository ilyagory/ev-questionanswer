<?php

use App\Util\Strings;
use Phalcon\Mvc\Model;
use Phalcon\Validation;
use Phalcon\Validation\Validator\PresenceOf;
use Phalcon\Validation\Validator\StringLength;

/**
 * Class Topic
 *
 * @property Category category
 */
class Topic extends Model
{
    public $id;
    public $title;
    public $text;
    public $created;
    protected $category;

    function initialize()
    {
        $this->keepSnapshots(true);
        $this->belongsTo(
            'category',
            Category::class,
            'id',
            [
                'reusable' => true,
                'alias' => 'category',
            ]
        );
        $this->hasMany(
            'id',
            Comment::class,
            'topic'
        );
        $this->hasMany(
            'id',
            Attach::class,
            'topic',
            [
                'alias' => 'attaches',
                'foreignKey' => [
                    'action' => Model\Relation::ACTION_CASCADE
                ],
            ]
        );
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
            'mintitle' => $cfg->path('app.min_title_length'),
            'maxtitle' => $cfg->path('app.max_title_length'),
            //--
            'mintext' => $cfg->path('app.min_content_length'),
            'maxtext' => $cfg->path('app.max_content_length'),
        ];

        $validator->rules(
            'title',
            [
                new PresenceOf([
                    'message' => 'Title should not be empty.'
                ]),
                new StringLength([
                    'min' => $lengths['mintitle'],
                    'max' => $lengths['maxtitle'],
                    'messageMinimum' => "Minimum length: {$lengths['mintitle']}",
                    'messageMaximum' => "Maximum length: {$lengths['maxtitle']}",
                ]),
                new Validation\Validator\Regex([
                    'pattern' => "/[\w\s\d.,?!=+\(\)-]+/u",
                    'message' => 'Title should not contain speacial chars'
                ]),
                new Validation\Validator\Uniqueness([
                    'message' => 'Title should be unique',
                ])
            ]
        );
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
        $oldTitle = $this->title;

        $this->text = strip_tags($this->text, '<img>');
        $this->text = trim($this->text, " \t\n\r\0\x0B\xC2\xA0");
        $this->text = preg_replace("/\s{2,}/u", " ", $this->text);
        $this->title = preg_replace("/\s{2,}/u", " ", $this->title);

        $isValid = $this->validate($validator);

        $this->text = $oldText;
        $this->title = $oldTitle;

        return $isValid;
    }
}