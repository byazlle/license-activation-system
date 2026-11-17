<?php
namespace App\Core;

/**
 * 响应封装
 * 统一客户端 API 的 JSON 输出格式，以及后台的错误响应。
 */
class Response
{
    /**
     * 输出跨域头（仅客户端 API 使用）
     * 客户端 API 不使用 Cookie 鉴权，因此允许任意来源；后台页面是同源访问，无需该头。
     */
    public static function cors()
    {
        if (headers_sent()) {
            return;
        }
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization, X-API-Key');
        header('Access-Control-Max-Age: 86400');
    }

    /**
     * 输出 JSON 响应并终止
     * @param int $code 错误码，0 表示成功
     * @param string $msg 提示信息
     * @param array $data 数据
     */
    public static function json($code, $msg = '', $data = array())
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $body = array(
            'code' => $code,
            'msg' => $msg,
            'request_id' => self::requestId(),
            // 空数组会被编码成 []，这里统一成 {}，让客户端始终按对象处理 data
            'data' => (is_array($data) && count($data) === 0) ? new \stdClass() : $data,
        );
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * 成功响应
     * @param array $data 数据
     * @param string $msg 提示
     */
    public static function success($data = array(), $msg = 'ok')
    {
        self::json(0, $msg, $data);
    }

    /**
     * 输出纯文本并终止（用于 K 码导出等）
     * @param string $text 文本
     */
    public static function text($text)
    {
        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo $text;
        exit;
    }

    /**
     * 获取或生成本次请求 ID（贯穿日志，便于排查）
     * @return string
     */
    public static function requestId()
    {
        if (empty($_SERVER['REQUEST_ID'])) {
            $_SERVER['REQUEST_ID'] = substr(md5(uniqid('', true) . mt_rand()), 0, 16);
        }
        return $_SERVER['REQUEST_ID'];
    }
}
