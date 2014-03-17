<?php

class Media extends \Phalcon\Mvc\Model
{
    public function initialize()
    {
        $this->belongsTo('category_id', 'Category', 'id', array(
                        'reusable' => true
        ));
    }

    public function getMovieName()
    {
        return '未実装';
    }
}