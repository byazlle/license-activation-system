<?php
namespace App\Core;

/**
 * 通用工具类
 * 包含随机串、请求读取、IP、时间等常用函数，规避高版本 PHP 专有函数。
 */
class Util
{
    /**
     * 生成随机字符串
     * @param int $length 长度
     * @return string
     */
    public static function random($length = 16)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $result = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[random_int(0, $max)];
        }
        return $result;
    }

    /**
     * 生成卡号（形如 CARD-20261117-XXXX-XXXX）
     * @return string
     */
    public static function cardNo()
    {
        return 'CARD-' . date('Ymd') . '-' . strtoupper(self::random(4)) . '-' . strtoupper(self::random(4));
    }

    /**
     * 获取请求参数（GET/POST 合并，POST 优先）
     * @param string $key 参数名
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function param($key, $default = null)
    {
        if (isset($_POST[$key])) {
            return is_string($_POST[$key]) ? trim($_POST[$key]) : $_POST[$key];
        }
        if (isset($_GET[$key])) {
            return is_string($_GET[$key]) ? trim($_GET[$key]) : $_GET[$key];
        }
        return $default;
    }

    /**
     * 获取客户端 IP
     * @return string
     */
    public static function ip()
    {
        foreach (array('HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR') as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = explode(',', $_SERVER[$key]);
                return trim($ip[0]);
            }
        }
        return '';
    }

    /**
     * 当前 Unix 时间戳（秒）
     * @return int
     */
    public static function now()
    {
        return time();
    }

    /**
     * 格式化时间戳
     * @param int $ts 时间戳
     * @return string
     */
    public static function date($ts)
    {
        if (!$ts) {
            return '-';
        }
        return date('Y-m-d H:i:s', $ts);
    }

    /**
     * 兼容 PHP 8 的 str_starts_with
     */
    public static function startsWith($haystack, $needle)
    {
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }

    /**
     * 兼容 PHP 8 的 str_contains
     */
    public static function contains($haystack, $needle)
    {
        return strpos($haystack, $needle) !== false;
    }

    /**
     * 重定向
     * @param string $url 目标地址（相对或绝对）
     */
    public static function redirect($url)
    {
        header('Location: ' . $url);
        exit;
    }

    /**
     * 转义输出，防 XSS
     * @param mixed $value 值
     * @return string
     */
    public static function e($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
