<?php


namespace HuaWa\Facade;

interface LoginAction
{
    public function setCaptchaCacheDriver(CacheAction $cache);
    public function setCookieCacheDriver(CacheAction $cache);
    public function setCaptchaDriver(CaptchaAction $cache);
}