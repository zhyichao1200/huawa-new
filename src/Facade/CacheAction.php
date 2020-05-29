<?php


namespace HuaWa\Facade;


interface CacheAction
{
    public function get($key);
    public function put($key,$data);
    public function fullDir($key);
}