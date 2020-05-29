<?php


namespace HuaWa\Model;


class Result extends Model
{
    protected $ok;
    protected $message;
    protected $data;
    protected $can;
    public function __construct($ok=false,$message="",$data=null,$can=false)
    {
        $this->ok = $ok;
        $this->message = $message;
        $this->data = $data;
        $this->can = $can;
    }
}