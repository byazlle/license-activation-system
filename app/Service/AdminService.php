<?php
namespace App\Service;

use App\Repository\AdminRepo;
use App\Core\Util;

/**
 * 管理员与账号服务
 */
class AdminService
{
    /**
     * 登录校验
     * @param string $username 用户名
     * @param string $password 密码
     * @return array|null 成功返回管理员记录
     */
    public static function attempt($username, $password)
    {
        $admin = AdminRepo::findByUsername($username);
        if (!$admin || (int)$admin['status'] !== 1) {
            return null;
        }
        if (!password_verify($password, $admin['password_hash'])) {
            return null;
        }
        AdminRepo::updateLogin($admin['id'], Util::ip());
        return $admin;
    }

    public static function all()
    {
        return AdminRepo::all();
    }

    public static function create($username, $password, $role)
    {
        if (AdminRepo::findByUsername($username)) {
            throw new \RuntimeException('用户名已存在');
        }
        return AdminRepo::create($username, password_hash($password, PASSWORD_BCRYPT), $role);
    }

    public static function changePassword($id, $old, $new)
    {
        $admin = AdminRepo::find($id);
        if (!$admin || !password_verify($old, $admin['password_hash'])) {
            return false;
        }
        AdminRepo::updatePassword($id, password_hash($new, PASSWORD_BCRYPT));
        return true;
    }

    public static function resetPassword($id, $new)
    {
        return AdminRepo::updatePassword($id, password_hash($new, PASSWORD_BCRYPT)) >= 0;
    }
}
