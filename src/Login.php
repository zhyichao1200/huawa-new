<?php


namespace HuaWa;


use HuaWa\Facade\Action;
use HuaWa\Facade\LoginAction;
use GuzzleHttp\Client;
use HuaWa\Model\Result;
use HuaWa\Tool\EnumUri;
use QL\QueryList;
use HuaWa\Facade\CacheAction;
use HuaWa\Facade\CaptchaAction;
use GuzzleHttp\Cookie\CookieJar;
class Login implements Action,LoginAction
{
    private $username;
    private $password;
    private $client;
    private $captchaCache;
    private $cookieCache;
    private $captcha;
    public function __construct(string $username,string $password)
    {
        $this->username = $username;
        $this->password = $password;
        $this->client = new Client(["verify"=>false,"http_errors"=>false]);
    }

    public function setCaptchaCacheDriver(CacheAction $cache){
        $this->captchaCache = $cache;
        return $this;
    }

    public function setCookieCacheDriver(CacheAction $cache){
        $this->cookieCache = $cache;
        return $this;
    }

    public function setCaptchaDriver(CaptchaAction $captcha){
        $this->captcha = $captcha;
        return $this;
    }

    private function getLoginViewToken(){
        $query = [
            "c"=>"login"
        ];
        $url = EnumUri::getUri($query);
        $res = $this->client->get($url);
        if ($res->getStatusCode() != 200) return new Result(false,"登录页面获取失败");
        $query = QueryList::html($res->getBody()->getContents());
        $formhash = $query->find('input[name="formhash"]')->val();
        if (empty($formhash)) return new Result(false,"formhash获取失败");
        $formSubmit = $query->find('input[name="form_submit"]')->val();
        if (empty($formSubmit)) return new Result(false,"form_submit获取失败");
        $nchash = $query->find('input[name="nchash"]')->val();
        if (empty($nchash)) return new Result(false,"nchash获取失败");
        $cookie = $res->getHeader("Set-Cookie");
        if (empty($cookie)) return new Result(false,"登录session获取失败");
        $cookie = explode(";",$cookie[0]);
        $cookie = explode("=",$cookie[0]);
        $session[$cookie[0]] = $cookie[1];
        $cookie = $session;
        return new Result(true,"登录token获取成功",compact("formhash","formSubmit","nchash","cookie"));
    }

    private function checkCaptcha($nchash,$session){
        $query = [
            "c"=>"seccode",
            "a"=>"makecode",
            "nchash"=>$nchash,
        ];
        $url = EnumUri::getUri($query);
        $res = $this->client->get($url);
        if ($res->getStatusCode() != 200) return new Result(false,"验证码获取失败");
        $rt = $this->captchaCache->put($this->username.".png",$res->getBody()->getContents());
        if (!$rt) return new Result(false,"储存失败");
        $code = $this->captcha->run($this->captchaCache->fullDir($this->username.".png"));
        if (!$code) return new Result(false,"验证码识别失败");
        $cookie = $res->getHeader("Set-Cookie");
        $cookie = explode(";",$cookie[1]);
        $cookie = explode("=",$cookie[0]);
        $session[$cookie[0]] = $cookie[1];
        $captchaCookie[] = $cookie[0];
        list($msec, $sec) = explode(' ', microtime());
        $msectime = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        $query = [
            "c"=>"seccode",
            "a"=>"check",
            "nchash"=>$nchash,
            "rand"=>$msectime,
            "captcha"=>$code,
        ];
        $url = EnumUri::getUri($query);
        $cookieJar = CookieJar::fromArray($session, EnumUri::MAIN);
        $res = $this->client->get($url,["cookies"=>$cookieJar,]);
        if ($res->getBody()->getContents() == "true"){
            return new Result(true,"验证码校验成功",["cookie"=>$session,"code"=>$code]);
        }else{
            return new Result(true,"验证码校验失败");
        }

    }

    private function cookieParser($cookie){
        $cookie = explode(";",$cookie[0]);
        $cookie = explode("=",$cookie[0]);
        return $cookie;
    }

    private function postData($data,$session){
        $query = [
            "inajax"=>"1"
        ];
        $url = EnumUri::getPathUri("login",$query);
        $res = $this->client->post($url,[
            "form_params"=>$data,
            "cookies"=>CookieJar::fromArray($session, EnumUri::MAIN)
        ]);
        $obj = (array) simplexml_load_string($res->getBody()->getContents(), 'SimpleXMLElement', LIBXML_NOCDATA);
        $obj = $obj[0];
        $preg = preg_replace('/<script type="text\/javascript" reload="1">.+<\/script>/is', '', $obj);
        if($preg != "登录成功") return new Result(true,$preg);
        $cookie = $res->getHeader("Set-Cookie");
        $cookie = $this->cookieParser($cookie);
        $session[$cookie[0]] = $cookie[1];
        return new Result(true,"用户cookie获取成功",$session);;

    }

    private function store($data){
        $data["expire"] = time() + 3600;
        if ($this->cookieCache->put($this->username,$data)){
            return new Result(true,"用户cookie储存成功",$data);
        }else{
            return new Result(false,"用户cookie储存成功");
        }
    }

    public function run()
    {
        $loginToken = $this->getLoginViewToken();
        if (!$loginToken->getOk()) return $loginToken;
        $token = $loginToken->getData();
        $ret = $this->checkCaptcha($token["nchash"],$token["cookie"]);
        if (!$ret->getOk()) return $ret;
        $captchaInfo = $ret->getData();
        $data = [
            "formhash"=>$token["formhash"],
            "form_submit"=>$token["formSubmit"],
            "nchash"=>$token["nchash"],
            "ref_url"=>"",
            "user_name"=>$this->username,
            "password"=>$this->password,
            "captcha"=>$captchaInfo["code"],
            "remember_me"=>1,
            "dosubmit"=>"登  录",
        ];
        $res = $this->postData($data,$captchaInfo["cookie"]);
        if (!$res->getOk()) return $res;
        $res = $this->store($ret->getData());
        return $res;
    }
}



























