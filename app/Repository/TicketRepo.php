<?php
namespace App\Repository;

use App\Core\DB;

class TicketRepo
{
    public static function create($ticket, $cardId, $deviceId, $expireAt)
    {
        return DB::insert('tickets', array(
            'ticket' => $ticket,
            'card_id' => $cardId,
            'device_id' => $deviceId,
            'expire_at' => $expireAt,
            'used_at' => 0,
            'created_at' => time(),
        ));
    }

    /**
     * 核销票据（一次性）：仅未使用且未过期的票据能被核销
     * @param string $ticket 票据令牌
     * @param string $cardId 卡号
     * @param int $now 当前时间戳
     * @return int 受影响行数，1 表示核销成功
     */
    public static function consume($ticket, $cardId, $now)
    {
        $sql = 'UPDATE {tickets} SET used_at = :used_at
                WHERE ticket = :ticket AND card_id = :card_id
                  AND used_at = 0 AND expire_at >= :now';
        return DB::execute($sql, array(
            ':used_at' => time(),
            ':ticket' => $ticket,
            ':card_id' => $cardId,
            ':now' => $now,
        ));
    }

    /**
     * 清理过期票据
     * @return int
     */
    public static function cleanExpired()
    {
        return DB::execute('DELETE FROM {tickets} WHERE expire_at < ?', array(time() - 86400));
    }
}
