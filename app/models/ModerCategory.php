<?php

use Phalcon\Mvc\Model;

class ModerCategory extends Model
{
    function getSource(): string
    {
        return 'moder_category';
    }

    function initialize()
    {
        $this->belongsTo(
            'moder',
            User::class,
            'id'
        );
        $this->belongsTo(
            'category',
            Category::class,
            'id'
        );
    }
}