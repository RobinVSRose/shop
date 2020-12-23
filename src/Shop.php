<?php

namespace Ejoy\Shop;

use Encore\Admin\Extension;

class Shop extends Extension
{
    public $name = 'shop';

    public $views = __DIR__.'/../resources/views';

    public $assets = __DIR__.'/../resources/assets';

    public $menu = [
        'title' => 'Shop',
        'path'  => 'shop',
        'icon'  => 'fa-gears',
    ];
}