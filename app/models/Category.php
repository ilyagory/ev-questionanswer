<?php

use App\Util\Strings;
use Phalcon\Mvc\Model;
use Phalcon\Validation;
use Phalcon\Validation\Validator\PresenceOf;
use Phalcon\Validation\Validator\StringLength;

/**
 * Class Category
 *
 * @property Topic[] last5Topics
 * @property Topic[] topic
 * @property Model\Resultset\Simple moder
 */
class Category extends Model
{
    public $id;
    public $title;

    function initialize()
    {
        $this->keepSnapshots(true);
        $this->setup([
            'notNullValidations' => true,
        ]);
        $this->hasMany('id', Topic::class, 'category', [
            'foreignKey' => [
                'action' => Model\Relation::ACTION_CASCADE
            ],
        ]);
        $this->hasMany('id', Topic::class, 'category', [
            'alias' => 'last5Topics',
            'reusable' => true,
            'params' => [
                'limit' => 5,
                'order' => 'created DESC',
            ],
        ]);
        $this->hasManyToMany(
            'id',
            ModerCategory::class,
            'category', 'moder',
            User::class,
            'id',
            ['alias' => 'moder', 'reusable' => true],
        );
    }

    function validation(): bool
    {
        $validator = new Validation;
        $cfg = $this->getDI()->get('config');
        $lengths = [
            'mintitle' => $cfg->path('app.min_title_length'),
            'maxtitle' => $cfg->path('app.max_title_length_category'),
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

        $oldTitle = $this->title;
        $this->title = preg_replace("/\s{2,}/u", " ", $this->title);
        
        $isValid = $this->validate($validator);
        $this->title = $oldTitle;

        return $isValid;
    }
}