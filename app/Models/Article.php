<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    protected $table = 'article';

    protected $dates = ['updated_at', 'created_at'];

    protected $appends = [];

    public static $types = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        self::$types = ['notice' => __('公告'), 'article' => __('文章'), 'page' => __('单页')];
    }
}
