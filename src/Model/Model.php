<?php


namespace HuaWa\Model;


class Model
{
    public function __call($name, $arguments)
    {
        $name = lcfirst(str_replace("get","",$name));
        return $this->$name;
    }
}