<?php
/**
 * 应用引导文件
 * 职责：加载配置、注册极简自动加载器、注册全局异常/错误处理、设置运行时。
 * 兼容 PHP 7.2，不依赖 Composer。
 */

// ---------- 运行时设置 ----------
date_default_timezone_set('Asia/Shanghai');
error_reporting(E_ALL);
ini_set('display_errors', '0'); // 生产环境不向浏览器输出错误，错误写入日志
mb_internal_encoding('UTF-8');

// ---------- 极简 PSR-4 风格自动加载 ----------
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = ROOT . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

// ---------- 加载配置 ----------
use App\Core\Config;

Config::load(ROOT . '/config/config.php');

// ---------- 全局异常处理 ----------
use App\Core\Response;
use App\Core\Log;

set_exception_handler(function ($e) {
    // 记录真实异常到日志，不向客户端暴露堆栈
    if (class_exists('App\\Core\\Log')) {
        Log::error('UNCAUGHT_EXCEPTION', array(
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ));
    }
    if (ob_get_level() > 0) {
        ob_clean();
    }
    Response::json(9001, '服务暂时不可用');
});

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    if (class_exists('App\\Core\\Log')) {
        Log::error('PHP_ERROR', array(
            'severity' => $severity,
            'message' => $message,
            'file' => $file,
            'line' => $line,
        ));
    }
    return true;
});

// ---------- 会话 ----------
if (session_status() === PHP_SESSION_NONE) {
    $sessionPath = ROOT . '/storage/sessions';
    if (is_dir($sessionPath) && is_writable($sessionPath)) {
        session_save_path($sessionPath);
    }
    session_start();
}
