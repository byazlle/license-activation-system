<?php
/**
 * 单一入口文件
 * 所有请求（客户端 API 与后台管理）都通过本文件进入。
 * 用法：
 *   - 后台页面： index.php?r=card/index
 *   - 客户端接口： index.php?r=api/activate
 * 部署时把站点文档根指向 public/ 目录。
 */
define('ROOT', dirname(__DIR__));
define('PUBLIC_PATH', __DIR__);

require_once ROOT . '/app/bootstrap.php';

use App\Router;

$router = new Router();
$router->dispatch();
