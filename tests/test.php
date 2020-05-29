<?php
require '../vendor/autoload.php';
use HuaWa\Login;
use HuaWa\Captcha\JianJiaoCheck;
use HuaWa\Cache\FileStore;

$username = "18698562829";
$password = "hn123qy";
$appcode = "A9DBE17C6ED5DDC8CE4F61DF31AAFA31";
$appkey = "AKID8875752beb1d2ef86cba5c57318caeea";
$appsecret = "02b1d3c5ed688a25f4d222fc5bb5076d";
$login = new Login($username,$password);
$captchaPath = "./captcha";
$cookiePath = "./user";
$login->setCaptchaDriver(new JianJiaoCheck($appcode,$appkey,$appsecret))
    ->setCaptchaCacheDriver(new FileStore($captchaPath))
    ->setCookieCacheDriver(new FileStore($cookiePath))
    ->run();