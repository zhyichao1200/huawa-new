<?php


namespace HuaWa;


use HuaWa\Facade\Action;
use HuaWa\Facade\MyOrderAction;
use QL\QueryList;
use GuzzleHttp\Client;
use HuaWa\Tool\EnumUri;
use GuzzleHttp\Cookie\CookieJar;
use HuaWa\Model\Result;
class MyOrder implements MyOrderAction,Action
{
    private $cookie;
    private $startTime="";
    private $endTime="";
    private $client;
    public function __construct($cookie)
    {
        $this->cookie = $cookie;
        $this->client = new Client(["verify"=>false,"http_errors"=>false]);
    }

    private function pageView($page){
        $query = [
            "account"=>"",
            "c"=>"myorder",
            "a"=>"index",
            "search"=>"ok",
            "addtime_start"=>$this->startTime,
            "addtime_end"=>$this->endTime,
            "curpage"=>$page
        ];
        $url = EnumUri::getUri($query);
        $res = $this->client->get($url,[
            "cookies"=>CookieJar::fromArray($this->cookie, EnumUri::MAIN)
        ]);
        if($res->getStatusCode() != 200) return new Result(false,"第".$page."页我的订单请求失败");
        return new Result(true,"请求成功",$res->getBody()->getContents());
    }

    private function totalPage($html){
        $total = 0;
        $ql = QueryList::html($html);
        $list = $ql->rules([
            "page"=>["span","text"]
        ])->range(".pagination ul li")->query()->getData();
        if (empty($list)) return $total;
        foreach($list as $key=>$value){
            if(is_numeric($value["page"])){
                $total += 1;
            }
        }
        return $total;
    }

    private function htmlParser($html,$preg){
        $list = [];
        foreach($preg as $index=>$value){
            preg_match($value,$html,$out);
            $res = empty($out) ? "" : strip_tags($out[1]);
            $list[$index] = $res;
        }
        return $list;
    }

    private function itemList($html){
        $data = QueryList::html($html)->rules([
            "order_id"=>["tr span:eq(0) font","text"],
            "member_name"=>["tr a:eq(0) font","text"],
            "image"=>[".table_n div:eq(0) img","src"],
            "detail"=>[".table_n div:eq(0) a","href"],
            "card"=>[".table_n div","html"],
            "payment"=>[".table_n td:eq(1)","text"],
            "status"=>[".table_n td:eq(2)","text"],
        ])->range(".mt10")->queryData(function($item){
            if (empty($item["order_id"])) return $item;
            $ql = QueryList::html($item["card"]);
            $item["arrivalTime"] = $ql->find("p:eq(0) font")->text();
            $item["attachment"] = $ql->find('a[style="color: #f37030;"]')->attr("href");
            $item["address"] = "";
            $item["remark"] = "";
            $item["payment"] = trim(trim($item["payment"]),"￥");
            $item["status"] = trim($item["status"]);
            $receiver = [
                "receive_name"=>"",
                "item_num"=>"",
                "receive_phone"=>"",
            ];
            $orderedBy = [
                "orderedPhone"=>""
            ];
            $itemInfo = [
                "itemType"=>"",
                "itemName"=>"",
            ];
            if (empty($item["attachment"])){
                $item["address"] = $ql->find("p:eq(1)")->text();
                $receiver = $ql->find("p:eq(2)")->text();
                $preg=[
                    "receiveName"=>'/收货姓名：(\S+)?/is',
                    "itemNum"=>'/产品数量：(\d)?/is',
                    "receivePhone"=>'/收货人电话：(\d+)?/is',
                ];
                $receiver = $this->htmlParser($receiver,$preg);
                $preg = [
                    "orderedPhone"=>'/订货人电话：(\d+)?/is',
                ];
                $orderedBy = $ql->find("p:eq(3)")->text();
                $orderedBy = $this->htmlParser($orderedBy,$preg);
                $preg=[
                    "item_type"=>'/类型：\s+(\S+)/is',
                    "item_name"=>'/商品：(.+)?/is',
                ];
                $itemInfo = $ql->find("p:eq(4)")->text();
                $itemInfo = $this->htmlParser($itemInfo,$preg);
                $item["remark"] = $ql->find("p:eq(5) a")->attr("title");
            }else{
                $item["remark"] = $ql->find("p:eq(3) a")->attr("title");
            }
            $item["card"] = "";
            $preg=[
                "member_phone"=>'/手机：(\d+)?/is',
            ];
            $memberPhone = $this->htmlParser($item["member_name"],$preg);

            $item["member_phone"] = $memberPhone["member_phone"];
            $item = array_merge($item,$receiver,$orderedBy,$itemInfo);
            return $item;
        });
        foreach($data as $index=>$item){
            if (empty($item["order_id"])){
                unset($data[$index]);
            }
        }
        return $data;
    }

    public function run(){
        $page = 1;
        $totalPage = 1;
        $list = [];
        do {
            $pageHtml = $this->pageView($page);
            $page += 1;
            if (!$pageHtml->getOk()) return $pageHtml;
            $totalPage == 1 and $totalPage = $this->totalPage($pageHtml->getData());
            $item = $this->itemList($pageHtml->getData());
            $list = array_merge($list,$item);
        } while ($page <= $totalPage);
        return new Result(true,"请求成功",$list);
    }

    public function setEndTime($time)
    {
        $this->endTime = $time;
        return $this;
    }

    public function setStartTime($time)
    {
        $this->startTime = $time;
        return $this;
    }
}
