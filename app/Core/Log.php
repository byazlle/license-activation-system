<?php
namespace App\Core;

/**
 * 结构化日志（写入文件）
 * 每条日志：时间 | 级别 | request_id | 事件 | 上下文字段
 */
class Log
{
    /**
     * 写日志
     * @param string $level 级别：INFO/WARN/ERROR
     * @param string $event 事件名
     * @param array $context 上下文
     */
    public static function write($level, $event, $context = array())
    {
        $dir = Config::root() . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $file = $dir . '/' . date('Y-m-d') . '.log';
        $context['request_id'] = Response::requestId();
        $context['ip'] = Util::ip();
        $line = date('Y-m-d H:i:s')
            . ' | ' . $level
            . ' | ' . $context['request_id']
            . ' | ' . $event
            . ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . PHP_EOL;
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    public static function info($event, $context = array())
    {
        self::write('INFO', $event, $context);
    }

    public static function warn($event, $context = array())
    {
        self::write('WARN', $event, $context);
    }

    public static function error($event, $context = array())
    {
        self::write('ERROR', $event, $context);
    }
}
