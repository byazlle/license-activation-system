<?php
namespace App\Controller;

use App\Core\Auth;
use App\Core\Util;
use App\Core\View;
use App\Service\LogService;
use App\Repository\SoftwareRepo;

/**
 * 日志控制器
 */
class LogController
{
    public function requests()
    {
        Auth::requireLogin();
        $filter = array(
            'card_id' => Util::param('card_id', ''),
            'software_id' => Util::param('software_id', ''),
            'result' => Util::param('result', ''),
            'start' => Util::param('start', ''),
            'end' => Util::param('end', ''),
        );
        $data = LogService::requests($filter, (int)Util::param('page', 1));
        $data['softwares'] = SoftwareRepo::all();
        View::page('log/requests', $data, '请求日志');
    }

    public function stats()
    {
        Auth::requireLogin();
        View::page('log/stats', array(
            'card' => \App\Repository\CardRepo::stats(),
        ), '数据统计');
    }

    public function adminLogs()
    {
        Auth::requireLogin();
        View::page('log/admin', array(
            'list' => \App\Repository\LogRepo::adminLogs(200),
        ), '操作日志');
    }
}
