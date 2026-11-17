<?php
namespace App;

use App\Core\Config;
use App\Core\Util;
use App\Core\Response;

/**
 * 路由器
 * 根据 ?r=模块/方法 分发到对应控制器。
 * 未安装时强制进入安装控制器。
 */
class Router
{
    /** 允许的模块名（用于路径式路由识别） */
    private static $modules = array('install', 'admin', 'dashboard', 'software', 'card', 'log', 'convert', 'api');

    /**
     * 解析路由：优先取 ?r=模块/方法，其次从 PATH_INFO 推导（如 /api/activate）
     * @return string 形如 api/activate
     */
    private function resolveRoute($default)
    {
        $route = Util::param('r', '');
        if ($route !== '') {
            return preg_replace('/[^a-zA-Z0-9_\/]/', '', $route);
        }
        // 路径式：/api/activate、/admin/login
        $path = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
        $path = trim((string)$path, '/');
        if ($path === '' || strpos($path, 'index.php') === 0) {
            return $default;
        }
        $segments = explode('/', $path);
        if (!in_array($segments[0], self::$modules, true)) {
            return $default;
        }
        $action = isset($segments[1]) && $segments[1] !== '' ? $segments[1] : 'index';
        return $segments[0] . '/' . $action;
    }

    /**
     * 分发请求
     */
    public function dispatch()
    {
        // 未安装：只允许访问安装流程
        if (!Config::isInstalled()) {
            $route = $this->resolveRoute('install/index');
            if (strpos($route, 'install') !== 0) {
                $route = 'install/index';
            }
            $this->call($route);
            return;
        }

        $this->call($this->resolveRoute('admin/login'));
    }

    /**
     * 调用控制器方法
     * @param string $route 形如 card/index
     */
    private function call($route)
    {
        $parts = explode('/', $route, 2);
        $module = isset($parts[0]) ? preg_replace('/[^a-zA-Z0-9_]/', '', $parts[0]) : '';
        $action = isset($parts[1]) ? preg_replace('/[^a-zA-Z0-9_]/', '', $parts[1]) : 'index';
        if ($module === '' || $action === '') {
            Response::json(1001, '请求路径不合法');
        }

        // 客户端 API 统一输出跨域头，并直接应答浏览器的 OPTIONS 预检
        if ($module === 'api') {
            Response::cors();
            $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
            if ($method === 'OPTIONS') {
                http_response_code(204);
                exit;
            }
        }

        $class = 'App\\Controller\\' . ucfirst($module) . 'Controller';
        if (!class_exists($class)) {
            // 页面请求给 404 文本，API 请求给 JSON
            if ($module === 'api') {
                Response::json(1001, '接口不存在');
            }
            http_response_code(404);
            echo '页面不存在';
            return;
        }

        $controller = new $class();
        if (!method_exists($controller, $action)) {
            if ($module === 'api') {
                Response::json(1001, '接口方法不存在');
            }
            http_response_code(404);
            echo '页面不存在';
            return;
        }
        $controller->{$action}();
    }
}
