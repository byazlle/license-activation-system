<?php
namespace App\Core;

/**
 * 配置读取器
 * 配置来自 config/config.php（安装时生成），提供点号分隔的键值读取。
 */
class Config
{
    /** @var array 配置项 */
    private static $items = array();

    /**
     * 加载配置文件
     * @param string $file 配置文件绝对路径
     */
    public static function load($file)
    {
        if (is_file($file)) {
            self::$items = require $file;
            if (!is_array(self::$items)) {
                self::$items = array();
            }
        }
    }

    /**
     * 读取配置（支持 a.b.c 形式）
     * @param string $key 键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        $value = self::$items;
        foreach (explode('.', $key) as $part) {
            if (is_array($value) && array_key_exists($part, $value)) {
                $value = $value[$part];
            } else {
                return $default;
            }
        }
        return $value;
    }

    /**
     * 项目根目录
     * @return string
     */
    public static function root()
    {
        return defined('ROOT') ? ROOT : dirname(__DIR__, 2);
    }

    /**
     * 是否已安装
     * @return bool
     */
    public static function isInstalled()
    {
        return is_file(self::root() . '/install/install.lock');
    }
}
