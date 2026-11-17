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
     * 根据接口地址与 API Key 拉取可选模型列表（服务端代请求 /models）
     * 兼容 OpenAI 风格 {data:[{id}]} 与部分厂商的 {models:[...]}
     */
    public function models()
    {
        Auth::requireLogin();
        if (!Auth::checkCsrf(Util::param('csrf', ''))) {
            Response::json(1001, '表单已过期');
        }
        $endpoint = trim(Util::param('endpoint', ''));
        $key = trim(Util::param('key', ''));
        if ($endpoint === '' || $key === '') {
            Response::json(1001, '请先填写接口地址与 API Key');
        }
        $url = self::modelsUrl($endpoint);
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        if ($scheme !== 'http' && $scheme !== 'https') {
            Response::json(1001, '接口地址必须以 http:// 或 https:// 开头');
        }
        if (!function_exists('curl_init')) {
            Response::json(1001, '服务器未启用 curl 扩展，请手动填写模型名称');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $key,
                'Accept: application/json',
            ),
        ));
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            Response::json(1001, '请求失败：' . $err);
        }
        $json = json_decode($body, true);
        if (!is_array($json)) {
            Response::json(1001, '接口返回不是 JSON（HTTP ' . $status . '），可手动填写模型名称');
        }
        if (isset($json['error'])) {
            $msg = is_array($json['error']) && isset($json['error']['message'])
                ? $json['error']['message'] : '接口返回错误';
            Response::json(1001, '接口错误：' . $msg);
        }

        $list = array();
        if (isset($json['data']) && is_array($json['data'])) {
            foreach ($json['data'] as $item) {
                if (is_array($item) && isset($item['id'])) {
                    $list[] = (string)$item['id'];
                } elseif (is_string($item)) {
                    $list[] = $item;
                }
            }
        } elseif (isset($json['models']) && is_array($json['models'])) {
            foreach ($json['models'] as $item) {
                if (is_array($item)) {
                    if (isset($item['name'])) {
                        $list[] = (string)$item['name'];
                    } elseif (isset($item['model'])) {
                        $list[] = (string)$item['model'];
                    }
                } elseif (is_string($item)) {
                    $list[] = $item;
                }
            }
        }
        $list = array_values(array_unique(array_filter($list)));
        sort($list);
        if (!$list) {
            Response::json(1001, '未获取到模型列表，请手动填写');
        }
        Response::json(0, '已获取 ' . count($list) . ' 个模型', array('models' => $list, 'url' => $url));
    }

    /**
     * 把对话接口地址归一化为模型列表地址
     * https://api.x.com/v1/chat/completions -> https://api.x.com/v1/models
     * @param string $endpoint 接口地址
     * @return string
     */
    private static function modelsUrl($endpoint)
    {
        $u = rtrim($endpoint, '/');
        foreach (array('/chat/completions', '/completions', '/messages', '/responses') as $suffix) {
            if (substr($u, -strlen($suffix)) === $suffix) {
                $u = substr($u, 0, -strlen($suffix));
                break;
            }
        }
        if (substr($u, -7) === '/models') {
            return $u;
        }
        return $u . '/models';
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
