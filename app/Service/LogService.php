<?php
namespace App\Service;

use App\Repository\LogRepo;
use App\Repository\CardRepo;

/**
 * 日志与统计服务
 */
class LogService
{
    public static function requests($filter, $page, $perPage = 20)
    {
        $page = max(1, (int)$page);
        $offset = ($page - 1) * $perPage;
        return array(
            'list' => LogRepo::paginate($filter, $offset, $perPage),
            'total' => LogRepo::count($filter),
            'page' => $page,
            'perPage' => $perPage,
            'pages' => max(1, ceil(LogRepo::count($filter) / $perPage)),
            'filter' => $filter,
        );
    }

    public static function overview()
    {
        return array(
            'req' => LogRepo::overview(),
            'card' => CardRepo::stats(),
            'adminLogs' => LogRepo::adminLogs(20),
        );
    }

    public static function cardStats($cardId)
    {
        return LogRepo::cardStats($cardId);
    }
}
