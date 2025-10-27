<?php
namespace WOO_MYGLS;

class Plugin {
    public static function init(){
        Settings::init();
        Admin::init();
        Frontend::init();
        Orders::init();
        REST::init();
        StatusSync::init();
    }
}
