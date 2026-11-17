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
    /**
     * 分发请求
     */
    public function dispatch()
    {
        // 未安装：只允许访问安装流程
        if (!Config::isInstalled()) {
            $route = Util::param('r', 'install/index');
            if (strpos($route, 'install') !== 0) {
                $route = 'install/index';
            }
            $this->call($route);
            return;
        }

        $route = Util::param('r', 'admin/login');
        $this->call($route);
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
