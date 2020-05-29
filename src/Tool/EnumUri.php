<?php


namespace HuaWa\Tool;

/**
 * 格式化URL
 * Class EnumUri
 * @package HuaWa
 */
class EnumUri
{
    /**
     * 请求DOMAIN
     */
    const DOMAIN = "https://www.huawa.com/";

    const MAIN = "www.huawa.com";

    /**
     * 拼接index.php后缀路由
     * @param array $query
     * @return string
     */
    static public function getUri(array $query) :string{
        $queryStr = http_build_query($query);
        return self::DOMAIN."index.php?".$queryStr;
    }

    /**
     * 拼接path路由
     * @param string $path
     * @param array $query
     * @return string
     */
    static public function getPathUri(string $path,array $query) :string {
        $queryStr = http_build_query($query);
        return self::DOMAIN.$path."?".$queryStr;
    }
}