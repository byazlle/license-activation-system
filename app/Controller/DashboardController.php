<?php
namespace App\Controller;

use App\Core\Auth;
use App\Core\Util;
use App\Core\View;
use App\Core\Response;
use App\Service\LogService;
use App\Repository\LogRepo;

/**
 * 首页概览控制器
 */
class DashboardController
{
    public function index()
    {
        Auth::requireLogin();
        $data = LogService::overview();
        View::page('dashboard/index', array('data' => $data), '概览');
    }

    /**
     * 清理日志（AJAX）
     */
    public function clean()
    {
        Auth::requireSuper();
        if (!Auth::checkCsrf(Util::param('csrf', ''))) {
            Response::json(1001, '表单已过期');
        }
        $days = max(1, (int)Util::param('days', 180));
        LogRepo::clean($days);
        \App\Repository\TicketRepo::cleanExpired();
        Response::json(0, '清理完成');
    }
}
