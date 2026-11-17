<?php
namespace App\Repository;

use App\Core\DB;

class SoftwareRepo
{
    public static function find($id)
    {
        return DB::fetch('SELECT * FROM {softwares} WHERE id = ?', array($id));
    }

    /**
     * 按软件标识查找（客户端请求时用）
     * @param string $identifier 软件标识
     * @return array|null
     */
    public static function findByIdentifier($identifier)
    {
        return DB::fetch('SELECT * FROM {softwares} WHERE identifier = ? AND status = 1', array($identifier));
    }

    public static function all()
    {
        return DB::fetchAll('SELECT * FROM {softwares} ORDER BY id DESC');
    }

    public static function enabled()
    {
        return DB::fetchAll('SELECT * FROM {softwares} WHERE status = 1 ORDER BY id');
    }

    public static function create($data)
    {
        $data['created_at'] = time();
        $data['updated_at'] = time();
        return DB::insert('softwares', $data);
    }

    public static function update($id, $data)
    {
        $data['updated_at'] = time();
        return DB::update('softwares', $data, 'id = :id', array(':id' => $id));
    }

    public static function setStatus($id, $status)
    {
        return DB::update('softwares', array('status' => $status, 'updated_at' => time()), 'id = :id', array(':id' => $id));
    }

    public static function updateBKey($id, $publicB, $privateBPath, $keyId)
    {
        return DB::update('softwares', array(
            'public_b' => $publicB,
            'private_b_path' => $privateBPath,
            'b_key_id' => $keyId,
            'updated_at' => time(),
        ), 'id = :id', array(':id' => $id));
    }
}
