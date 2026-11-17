<?php
namespace App\Controller;

use App\Core\Auth;
use App\Core\Util;
use App\Core\View;
use App\Core\Response;
use App\Service\AdminService;
use App\Repository\LogRepo;

/**
 * 管理员控制器：登录、退出、账号管理、改密
 */
class AdminController
{
    public function login()
    {
        if (Auth::check()) {
            Util::redirect('index.php?r=dashboard/index');
        }
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Auth::checkCsrf(Util::param('csrf', ''))) {
                $error = '表单已过期，请重试';
            } else {
                $admin = AdminService::attempt(Util::param('username', ''), Util::param('password', ''));
                if ($admin) {
                    Auth::login($admin);
                    LogRepo::admin($admin['id'], $admin['username'], 'login', '', null);
                    Util::redirect('index.php?r=dashboard/index');
                }
                $error = '用户名或密码错误';
            }
        }
        echo View::make('app/views/login.php', array(
            'error' => $error,
            'installed' => Util::param('installed', '') === '1',
            'csrf' => Auth::csrfToken(),
        ))->render();
    }

    public function logout()
    {
        Auth::logout();
        Util::redirect('index.php?r=admin/login');
    }

    /**
     * 修改自身密码
     */
    public function password()
    {
        Auth::requireLogin();
        $msg = '';
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Auth::checkCsrf(Util::param('csrf', ''))) {
                $error = '表单已过期';
            } elseif (AdminService::changePassword(Auth::id(), Util::param('old', ''), Util::param('new', ''))) {
                $msg = '密码已修改';
            } else {
                $error = '旧密码不正确';
            }
        }
        View::page('admin/password', array('msg' => $msg, 'error' => $error, 'csrf' => Auth::csrfToken()), '修改密码');
    }

    /**
     * 管理员列表
     */
    public function index()
    {
        Auth::requireSuper();
        View::page('admin/index', array(
            'list' => AdminService::all(),
            'csrf' => Auth::csrfToken(),
        ), '管理员');
    }

    /**
     * 新增管理员（AJAX）
     */
    public function add()
    {
        Auth::requireSuper();
        if (!Auth::checkCsrf(Util::param('csrf', ''))) {
            Response::json(1001, '表单已过期');
        }
        try {
            AdminService::create(
                Util::param('username', ''),
                Util::param('password', ''),
                (int)Util::param('role', 2)
            );
            LogRepo::admin(Auth::id(), Auth::name(), 'admin.create', Util::param('username', ''), null);
            Response::json(0, '已添加');
        } catch (\Exception $e) {
            Response::json(1001, $e->getMessage());
        }
    }

    /**
     * 删除管理员（AJAX）
     */
    public function remove()
    {
        Auth::requireSuper();
        if (!Auth::checkCsrf(Util::param('csrf', ''))) {
            Response::json(1001, '表单已过期');
        }
        $id = (int)Util::param('id', 0);
        if ($id === Auth::id()) {
            Response::json(1001, '不能删除自己');
        }
        \App\Repository\AdminRepo::delete($id);
        LogRepo::admin(Auth::id(), Auth::name(), 'admin.delete', (string)$id, null);
        Response::json(0, '已删除');
    }

    /**
     * API 密钥管理页
     */
    public function apiKeys()
    {
        Auth::requireSuper();
        View::page('admin/api-keys', array(
            'list' => \App\Repository\ApiKeyRepo::all(),
            'calls' => \App\Repository\ApiKeyRepo::callLogs(50),
            'scopes' => \App\Service\ApiKeyService::ALL_SCOPES,
            'csrf' => Auth::csrfToken(),
        ), 'API 密钥');
    }

    /**
     * 创建 API 密钥（AJAX），明文只返回一次
     */
    public function apiKeyCreate()
    {
        Auth::requireSuper();
        if (!Auth::checkCsrf(Util::param('csrf', ''))) {
            Response::json(1001, '表单已过期');
        }
        $scopes = Util::param('scopes', array());
        if (!is_array($scopes)) {
            $scopes = array_filter(array_map('trim', explode(',', (string)$scopes)));
        }
        try {
            $created = \App\Service\ApiKeyService::create(
                Util::param('name', ''),
                $scopes,
                (int)Util::param('days', 0),
                Auth::id()
            );
        } catch (\Exception $e) {
            Response::json(1001, $e->getMessage());
        }
        LogRepo::admin(Auth::id(), Auth::name(), 'api_key.create',
            $created['row']['key_prefix'], array('scopes' => $created['row']['scopes']));
        Response::json(0, '密钥已创建', array(
            'id' => $created['row']['id'],
            'key' => $created['key'],
        ));
    }

    /**
     * 撤销 API 密钥（AJAX）
     */
    public function apiKeyRevoke()
    {
        Auth::requireSuper();
        if (!Auth::checkCsrf(Util::param('csrf', ''))) {
            Response::json(1001, '表单已过期');
        }
        $id = (int)Util::param('id', 0);
        $row = \App\Repository\ApiKeyRepo::find($id);
        if (!$row) {
            Response::json(2001, '密钥不存在');
        }
        \App\Repository\ApiKeyRepo::revoke($id);
        LogRepo::admin(Auth::id(), Auth::name(), 'api_key.revoke', $row['key_prefix'], null);
        Response::json(0, '已撤销');
    }
}
