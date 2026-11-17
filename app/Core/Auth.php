<?php
namespace App\Core;

/**
 * 管理员认证与会话
 */
class Auth
{
    /**
     * 是否已登录
     * @return bool
     */
    public static function check()
    {
        return !empty($_SESSION['admin_id']);
    }

    /**
     * 当前管理员 ID
     * @return int
     */
    public static function id()
    {
        return isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : 0;
    }

    /**
     * 当前管理员用户名
     * @return string
     */
    public static function name()
    {
        return isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : '';
    }

    /**
     * 当前管理员角色（1 超管 / 2 普管）
     * @return int
     */
    public static function role()
    {
        return isset($_SESSION['admin_role']) ? (int)$_SESSION['admin_role'] : 0;
    }

    /**
     * 是否超级管理员
     * @return bool
     */
    public static function isSuper()
    {
        return self::role() === 1;
    }

    /**
     * 登录，写入会话
     * @param array $admin 管理员记录
     */
    public static function login($admin)
    {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$admin['id'];
        $_SESSION['admin_name'] = $admin['username'];
        $_SESSION['admin_role'] = (int)$admin['role'];
    }

    /**
     * 退出登录
     */
    public static function logout()
    {
        $_SESSION = array();
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    /**
     * 生成/获取 CSRF 令牌
     * @return string
     */
    public static function csrfToken()
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = Util::random(32);
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * 校验 CSRF 令牌
     * @param string $token 提交的令牌
     * @return bool
     */
    public static function checkCsrf($token)
    {
        return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
    }

    /**
     * 要求登录，否则跳转登录页
     */
    public static function requireLogin()
    {
        if (!self::check()) {
            Util::redirect('index.php?r=admin/login');
        }
    }

    /**
     * 要求超级管理员，否则跳转
     */
    public static function requireSuper()
    {
        self::requireLogin();
        if (!self::isSuper()) {
            Util::redirect('index.php?r=admin/login');
        }
    }
}
