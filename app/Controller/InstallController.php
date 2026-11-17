<?php
namespace App\Controller;

use App\Core\Config;
use App\Core\Util;
use App\Core\View;
use App\Service\InstallService;

/**
 * 安装控制器
 */
class InstallController
{
    public function index()
    {
        if (Config::isInstalled()) {
            Util::redirect('index.php?r=admin/login');
        }
        echo View::make('app/views/install.php', array())->render();
    }

    /**
     * 测试数据库连接（AJAX）
     */
    public function test()
    {
        $cfg = array(
            'host' => Util::param('host', '127.0.0.1'),
            'port' => Util::param('port', '3306'),
            'name' => Util::param('name', ''),
            'user' => Util::param('user', ''),
            'pass' => Util::param('pass', ''),
        );
        $result = InstallService::testDb($cfg);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * 执行安装
     */
    public function run()
    {
        if (Config::isInstalled()) {
            Util::redirect('index.php?r=admin/login');
        }
        $cfg = array(
            'host' => Util::param('host', '127.0.0.1'),
            'port' => Util::param('port', '3306'),
            'name' => Util::param('name', ''),
            'user' => Util::param('user', ''),
            'pass' => Util::param('pass', ''),
            'prefix' => Util::param('prefix', 'lc_'),
        );
        $adminUser = Util::param('admin_user', '');
        $adminPass = Util::param('admin_pass', '');
        if ($cfg['name'] === '' || $adminUser === '' || $adminPass === '') {
            echo View::make('app/views/install.php', array('error' => '请填写完整信息'))->render();
            return;
        }
        $result = InstallService::install($cfg, $adminUser, $adminPass);
        if (!$result['ok']) {
            echo View::make('app/views/install.php', array('error' => '安装失败：' . $result['msg']))->render();
            return;
        }
        Util::redirect('index.php?r=admin/login&installed=1');
    }
}
