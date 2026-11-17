<?php
namespace App\Repository;

use App\Core\DB;

class LogRepo
{
    /**
     * 记录一次客户端请求
     * @param array $d 日志字段
     * @return string
     */
    public static function request($d)
    {
        $data = array(
            'request_id' => isset($d['request_id']) ? $d['request_id'] : \App\Core\Response::requestId(),
            'card_id' => isset($d['card_id']) ? $d['card_id'] : null,
            'software_id' => isset($d['software_id']) ? $d['software_id'] : 0,
            'software_identifier' => isset($d['software_identifier']) ? $d['software_identifier'] : null,
            'device_id' => isset($d['device_id']) ? $d['device_id'] : null,
            'ip' => isset($d['ip']) ? $d['ip'] : \App\Core\Util::ip(),
            'action' => isset($d['action']) ? $d['action'] : '',
            'result' => isset($d['result']) ? $d['result'] : 0,
            'error_code' => isset($d['error_code']) ? $d['error_code'] : 0,
            'remain_before' => isset($d['remain_before']) ? $d['remain_before'] : 0,
            'remain_after' => isset($d['remain_after']) ? $d['remain_after'] : 0,
            'nonce' => isset($d['nonce']) ? $d['nonce'] : null,
            'detail' => isset($d['detail']) ? $d['detail'] : null,
            'created_at' => time(),
        );
        try {
            return DB::insert('request_logs', $data);
        } catch (\Exception $e) {
            // 日志写入失败不影响主流程
            \App\Core\Log::error('LOG_INSERT_FAIL', array('message' => $e->getMessage()));
            return 0;
        }
    }

    /**
     * nonce 是否已用过（防重放）
     * @param string $nonceHash nonce 的哈希
     * @return bool
     */
    public static function nonceUsed($nonceHash)
    {
        return DB::value('SELECT COUNT(*) FROM {request_logs} WHERE nonce = ?', array($nonceHash)) > 0;
    }

    /**
     * 请求日志分页查询
     */
    public static function paginate($filter, $offset, $limit)
    {
        $where = array('1=1');
        $params = array();
        if (!empty($filter['card_id'])) {
            $where[] = 'card_id = :card_id';
            $params[':card_id'] = $filter['card_id'];
        }
        if (!empty($filter['software_id'])) {
            $where[] = 'software_id = :software_id';
            $params[':software_id'] = $filter['software_id'];
        }
        if (isset($filter['result']) && $filter['result'] !== '') {
            $where[] = 'result = :result';
            $params[':result'] = $filter['result'];
        }
        if (!empty($filter['start'])) {
            $where[] = 'created_at >= :start';
            $params[':start'] = strtotime($filter['start']);
        }
        if (!empty($filter['end'])) {
            $where[] = 'created_at <= :end';
            $params[':end'] = strtotime($filter['end'] . ' 23:59:59');
        }
        $sql = 'SELECT * FROM {request_logs} WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT :offset,:limit';
        $params[':offset'] = $offset;
        $params[':limit'] = $limit;
        $stmt = DB::pdo()->prepare(str_replace('{request_logs}', DB::table('request_logs'), $sql));
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function count($filter)
    {
        $where = array('1=1');
        $params = array();
        if (!empty($filter['card_id'])) {
            $where[] = 'card_id = :card_id';
            $params[':card_id'] = $filter['card_id'];
        }
        if (!empty($filter['software_id'])) {
            $where[] = 'software_id = :software_id';
            $params[':software_id'] = $filter['software_id'];
        }
        if (isset($filter['result']) && $filter['result'] !== '') {
            $where[] = 'result = :result';
            $params[':result'] = $filter['result'];
        }
        $sql = 'SELECT COUNT(*) AS c FROM {request_logs} WHERE ' . implode(' AND ', $where);
        $sql = str_replace('{request_logs}', DB::table('request_logs'), $sql);
        $stmt = DB::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ? (int)$row['c'] : 0;
    }

    /**
     * 单卡统计
     * @param string $cardId 卡号
     * @return array
     */
    public static function cardStats($cardId)
    {
        return array(
            'total' => (int)DB::value('SELECT COUNT(*) FROM {request_logs} WHERE card_id = ?', array($cardId)),
            'success' => (int)DB::value('SELECT COUNT(*) FROM {request_logs} WHERE card_id = ? AND result = 1', array($cardId)),
            'activate' => (int)DB::value('SELECT COUNT(*) FROM {request_logs} WHERE card_id = ? AND action = \'activate\'', array($cardId)),
            'ticket' => (int)DB::value('SELECT COUNT(*) FROM {request_logs} WHERE card_id = ? AND action = \'ticket\'', array($cardId)),
            'last' => DB::value('SELECT MAX(created_at) FROM {request_logs} WHERE card_id = ?', array($cardId)),
        );
    }

    /**
     * 概览统计
     * @return array
     */
    public static function overview()
    {
        $today = strtotime('today');
        return array(
            'today_total' => (int)DB::value('SELECT COUNT(*) FROM {request_logs} WHERE created_at >= ?', array($today)),
            'today_success' => (int)DB::value('SELECT COUNT(*) FROM {request_logs} WHERE created_at >= ? AND result = 1', array($today)),
            'today_fail' => (int)DB::value('SELECT COUNT(*) FROM {request_logs} WHERE created_at >= ? AND result = 0', array($today)),
            'total_deduct' => (int)DB::value('SELECT COALESCE(SUM(1),0) FROM {request_logs} WHERE action = \'ticket\' AND result = 1'),
            'all_total' => (int)DB::value('SELECT COUNT(*) FROM {request_logs}'),
        );
    }

    /**
     * 清理旧日志
     * @param int $days 保留天数
     * @return int
     */
    public static function clean($days)
    {
        return DB::execute('DELETE FROM {request_logs} WHERE created_at < ?', array(time() - $days * 86400));
    }

    /**
     * 管理操作日志
     */
    public static function admin($adminId, $adminName, $action, $target, $detail)
    {
        return DB::insert('admin_logs', array(
            'admin_id' => $adminId,
            'admin_name' => $adminName,
            'action' => $action,
            'target' => $target,
            'detail' => $detail === null ? null : json_encode($detail, JSON_UNESCAPED_UNICODE),
            'ip' => \App\Core\Util::ip(),
            'created_at' => time(),
        ));
    }

    public static function adminLogs($limit = 100)
    {
        $limit = (int)$limit;
        return DB::fetchAll('SELECT * FROM {admin_logs} ORDER BY id DESC LIMIT ' . $limit);
    }
}
