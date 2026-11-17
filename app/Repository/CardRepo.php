<?php
namespace App\Repository;

use App\Core\DB;

class CardRepo
{
    public static function find($id)
    {
        return DB::fetch('SELECT * FROM {cards} WHERE id = ?', array($id));
    }

    /**
     * 按卡号查找
     * @param string $cardId 卡号
     * @return array|null
     */
    public static function findByCardId($cardId)
    {
        return DB::fetch('SELECT * FROM {cards} WHERE card_id = ?', array($cardId));
    }

    /**
     * 客户端校验用：卡号 + 软件标识联合校验
     * @param string $cardId 卡号
     * @param int $softwareId 软件 ID
     * @return array|null
     */
    public static function findForSoftware($cardId, $softwareId)
    {
        return DB::fetch('SELECT * FROM {cards} WHERE card_id = ? AND software_id = ?', array($cardId, $softwareId));
    }

    /**
     * 分页查询卡密
     * @param array $filter 筛选条件
     * @param int $offset 偏移
     * @param int $limit 每页条数
     * @return array
     */
    public static function paginate($filter, $offset, $limit)
    {
        $where = array('1=1');
        $params = array();
        if (!empty($filter['software_id'])) {
            $where[] = 'software_id = :software_id';
            $params[':software_id'] = $filter['software_id'];
        }
        if (isset($filter['status']) && $filter['status'] !== '') {
            $where[] = 'status = :status';
            $params[':status'] = $filter['status'];
        }
        if (!empty($filter['keyword'])) {
            $where[] = '(card_id LIKE :kw OR remark LIKE :kw)';
            $params[':kw'] = '%' . $filter['keyword'] . '%';
        }
        $sql = 'SELECT * FROM {cards} WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT :offset,:limit';
        $params[':offset'] = $offset;
        $params[':limit'] = $limit;
        // PDO 的 LIMIT 参数需绑定为整数
        $stmt = \App\Core\DB::pdo()->prepare(str_replace('{cards}', \App\Core\DB::table('cards'), $sql));
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
        if (!empty($filter['software_id'])) {
            $where[] = 'software_id = :software_id';
            $params[':software_id'] = $filter['software_id'];
        }
        if (isset($filter['status']) && $filter['status'] !== '') {
            $where[] = 'status = :status';
            $params[':status'] = $filter['status'];
        }
        if (!empty($filter['keyword'])) {
            $where[] = '(card_id LIKE :kw OR remark LIKE :kw)';
            $params[':kw'] = '%' . $filter['keyword'] . '%';
        }
        $sql = 'SELECT COUNT(*) AS c FROM {cards} WHERE ' . implode(' AND ', $where);
        $sql = str_replace('{cards}', \App\Core\DB::table('cards'), $sql);
        $stmt = \App\Core\DB::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ? (int)$row['c'] : 0;
    }

    public static function create($data)
    {
        return DB::insert('cards', $data);
    }

    /**
     * 原子扣次：remain>0 时减 1，used 加 1。返回受影响行数，
     * 用单条 UPDATE 天然防超扣（并发安全）。
     * @param string $cardId 卡号
     * @param int $softwareId 软件 ID
     * @return int 受影响行数
     */
    public static function deductAtomic($cardId, $softwareId)
    {
        $sql = 'UPDATE {cards} SET remain = remain - 1, used = used + 1, last_used_at = :now
                WHERE card_id = :card_id AND software_id = :software_id
                  AND status = 1 AND mode = \'times\' AND remain > 0';
        return DB::execute($sql, array(
            ':now' => time(),
            ':card_id' => $cardId,
            ':software_id' => $softwareId,
        ));
    }

    public static function extend($id, $expireAt)
    {
        return DB::update('cards', array('expire_at' => $expireAt, 'updated_at' => time()), 'id = :id', array(':id' => $id));
    }

    public static function setTimes($id, $remain, $total)
    {
        return DB::update('cards', array('remain' => $remain, 'total_times' => $total, 'updated_at' => time()), 'id = :id', array(':id' => $id));
    }

    public static function setStatus($id, $status)
    {
        return DB::update('cards', array('status' => $status, 'updated_at' => time()), 'id = :id', array(':id' => $id));
    }

    public static function setActivated($id, $deviceId)
    {
        return DB::update('cards', array('activated_at' => time(), 'device_id' => $deviceId), 'id = :id AND activated_at = 0', array(':id' => $id));
    }

    public static function delete($id)
    {
        return DB::execute('DELETE FROM {cards} WHERE id = ?', array($id));
    }

    /**
     * 统计概览数据
     * @return array
     */
    public static function stats()
    {
        return array(
            'total' => (int)DB::value('SELECT COUNT(*) FROM {cards}'),
            'active' => (int)DB::value('SELECT COUNT(*) FROM {cards} WHERE status = 1'),
            'time_cards' => (int)DB::value('SELECT COUNT(*) FROM {cards} WHERE mode = \'time\''),
            'times_cards' => (int)DB::value('SELECT COUNT(*) FROM {cards} WHERE mode = \'times\''),
            'expired' => (int)DB::value('SELECT COUNT(*) FROM {cards} WHERE mode = \'time\' AND expire_at > 0 AND expire_at < ?', array(time())),
            'exhausted' => (int)DB::value('SELECT COUNT(*) FROM {cards} WHERE mode = \'times\' AND remain <= 0'),
        );
    }
}
