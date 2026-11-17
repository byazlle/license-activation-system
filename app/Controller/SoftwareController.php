<?php
namespace App\Controller;

use App\Core\Auth;
use App\Core\Util;
use App\Core\Response;
use App\Core\View;
use App\Repository\SoftwareRepo;
use App\Service\SoftwareService;
use App\Repository\LogRepo;

/**
 * 软件管理控制器
 */
class SoftwareController
{
    public function index()
    {
        Auth::requireLogin();
        View::page('software/index', array(
            'list' => SoftwareRepo::all(),
            'csrf' => Auth::csrfToken(),
        ), '软件管理');
    }

    public function create()
    {
        Auth::requireLogin();
        View::page('software/form', array(
            'software' => null,
            'csrf' => Auth::csrfToken(),
        ), '新增软件');
    }

    public function edit()
    {
        Auth::requireLogin();
        $id = (int)Util::param('id', 0);
        $software = SoftwareRepo::find($id);
        if (!$software) {
            Util::redirect('index.php?r=software/index');
        }
        View::page('software/form', array(
            'software' => $software,
            'csrf' => Auth::csrfToken(),
        ), '编辑软件');
    }

    /**
     * 保存软件（新增/编辑）
     */
    public function save()
    {
        Auth::requireLogin();
        if (!Auth::checkCsrf(Util::param('csrf', ''))) {
            Response::json(1001, '表单已过期');
        }
        $id = (int)Util::param('id', 0);
        $data = array(
            'name' => Util::param('name', ''),
            'identifier' => Util::param('identifier', ''),
            'default_mode' => Util::param('default_mode', 'time') === 'times' ? 'times' : 'time',
            'default_days' => (int)Util::param('default_days', 30),
            'default_times' => (int)Util::param('default_times', 100),
            'default_config' => Util::param('default_config', ''),
        );
        if ($data['name'] === '' || $data['identifier'] === '') {
            Response::json(1001, '名称和标识不能为空');
        }
        // 编辑时不允许改标识（K码绑定了标识）
        if ($id > 0) {
            unset($data['identifier']);
            SoftwareRepo::update($id, $data);
            LogRepo::admin(Auth::id(), Auth::name(), 'software.update', (string)$id, null);
        } else {
            $data['identifier'] = preg_replace('/[^a-zA-Z0-9_\-]/', '', $data['identifier']);
            if (SoftwareRepo::findByIdentifier($data['identifier'])) {
                Response::json(1001, '软件标识已存在');
            }
            $id = SoftwareService::create($data);
            LogRepo::admin(Auth::id(), Auth::name(), 'software.create', $data['identifier'], null);
        }
        Response::json(0, '已保存', array('id' => $id));
    }

    public function status()
    {
        Auth::requireLogin();
        if (!Auth::checkCsrf(Util::param('csrf', ''))) {
            Response::json(1001, '表单已过期');
        }
        SoftwareRepo::setStatus((int)Util::param('id', 0), (int)Util::param('status', 0) === 1 ? 1 : 0);
        Response::json(0, '已更新');
    }

    /**
     * 下载该软件公钥 B_i（供软件内置/核对）
     */
    public function publicKey()
    {
        Auth::requireLogin();
        $software = SoftwareRepo::find((int)Util::param('id', 0));
        if (!$software) {
            echo '软件不存在';
            exit;
        }
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="public_b_' . $software['identifier'] . '.pem"');
        echo $software['public_b'];
        exit;
    }

    /**
     * 轮换该软件 B_i 密钥对
     */
    public function rotate()
    {
        Auth::requireLogin();
        if (!Auth::checkCsrf(Util::param('csrf', ''))) {
            Response::json(1001, '表单已过期');
        }
        $id = (int)Util::param('id', 0);
        SoftwareService::rotateKey($id);
        LogRepo::admin(Auth::id(), Auth::name(), 'software.rotate_key', (string)$id, null);
        Response::json(0, '密钥已轮换，请重新生成该软件的 K 码');
    }

    /**
     * 下载全局公钥 A（所有软件内置）
     */
    public function publicA()
    {
        Auth::requireLogin();
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="public_a.pem"');
        echo SoftwareService::publicA();
        exit;
    }
}
