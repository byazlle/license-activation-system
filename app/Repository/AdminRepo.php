<?php
namespace App\Repository;

use App\Core\DB;

class AdminRepo
{
    /**
     * 按用户名查找管理员
     * @param string $username 用户名
     * @return array|null
     */
    public static function findByUsername($username)
    {
        return DB::fetch('SELECT * FROM {admins} WHERE username = ?', array($username));
    }

    public static function find($id)
    {
        return DB::fetch('SELECT * FROM {admins} WHERE id = ?', array($id));
    }

    public static function all()
    {
        return DB::fetchAll('SELECT id, username, role, status, last_login_at, last_login_ip, created_at FROM {admins} ORDER BY id');
    }

    public static function create($username, $passwordHash, $role)
    {
        return DB::insert('admins', array(
            'username' => $username,
            'password_hash' => $passwordHash,
            'role' => $role,
            'status' => 1,
            'created_at' => time(),
            'updated_at' => time(),
        ));
    }

    public static function updatePassword($id, $hash)
    {
        return DB::update('admins', array('password_hash' => $hash, 'updated_at' => time()), 'id = :id', array(':id' => $id));
    }

    public static function setStatus($id, $status)
    {
        return DB::update('admins', array('status' => $status, 'updated_at' => time()), 'id = :id', array(':id' => $id));
    }

    public static function updateRole($id, $role)
    {
        return DB::update('admins', array('role' => $role, 'updated_at' => time()), 'id = :id', array(':id' => $id));
    }

    public static function updateLogin($id, $ip)
    {
        return DB::update('admins', array('last_login_at' => time(), 'last_login_ip' => $ip), 'id = :id', array(':id' => $id));
    }

    public static function delete($id)
    {
        return DB::execute('DELETE FROM {admins} WHERE id = ?', array($id));
    }
}
