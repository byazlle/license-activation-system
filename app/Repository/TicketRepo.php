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
     * 清理过期票据
     * @return int
     */
    public static function cleanExpired()
    {
        return DB::execute('DELETE FROM {tickets} WHERE expire_at < ?', array(time() - 86400));
    }
}
