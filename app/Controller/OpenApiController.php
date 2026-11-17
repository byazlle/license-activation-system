<?php
namespace App\Controller;

use App\Core\Response;
use App\Core\Util;
use App\Repository\ApiKeyRepo;
use App\Repository\CardRepo;
use App\Repository\LogRepo;
use App\Repository\SoftwareRepo;
use App\Service\ApiKeyService;
use App\Service\CardService;
use App\Service\LicenseService;
use App\Service\LogService;
use App\Service\SoftwareService;

/**
 * 开放 API 控制器（供 AI / 第三方系统直接调用）
 *
 * 全部接口：POST JSON，统一返回 {code,msg,request_id,data}
 * 鉴权：Authorization: Bearer lsk_xxx  /  X-API-Key: lsk_xxx  /  api_key 参数
 * 路径：/openapi/<资源>/<操作>，同时兼容 ?r=openapi/<资源>/<操作>
 *
 * 每个方法只做三件事：鉴权 → 参数整理 → 调用已有 Service/Repo。
 * 这样开放 API 与后台页面共用同一套业务逻辑，不会出现两套行为。
 */
class OpenApiController
{
    /** 调用日志上下文（shutdown 时写入） */
    private static $ctx = array();

    /** 当前密钥记录 */
    private $key = null;

    /**
     * 读取请求参数：优先 JSON body，其次 GET/POST
     * @return array
     */
    private function params()
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $body = file_get_contents('php://input');
        if ($body !== '' && ltrim($body)[0] === '{') {
            $json = json_decode($body, true);
            if (is_array($json)) {
                $cache = $json;
                return $cache;
            }
        }
        $cache = array_merge($_GET, $_POST);
        unset($cache['r']);
        return $cache;
    }

    private function getp($p, $key, $default = '')
    {
        return array_key_exists($key, $p) ? $p[$key] : $default;
    }

    private function intp($p, $key, $default = 0)
    {
        return (int)$this->getp($p, $key, $default);
    }

    /**
     * 鉴权。$scope 为 null 时只要求密钥有效，不校验具体权限。
     * @param string|null $scope
     * @return array 当前密钥记录
     */
    private function auth($scope)
    {
        if (!self::$ctx) {
            $path = isset($_SERVER['REQUEST_URI'])
                ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
            self::$ctx = array(
                'key_id' => 0,
                'key_prefix' => '',
                'endpoint' => (string)$path,
                'ip' => Util::ip(),
                'start' => microtime(true),
                'code' => 0,
            );
            register_shutdown_function(function () {
                ApiKeyRepo::logCall(self::$ctx);
            });
        }

        $result = ApiKeyService::verify(ApiKeyService::requestKey());
        if (!$result['ok']) {
            self::$ctx['code'] = $result['code'];
            Response::json($result['code'], $result['msg']);
        }
        if ($scope !== null && !ApiKeyService::allows($result['key'], $scope)) {
            self::$ctx['code'] = 4005;
            self::$ctx['key_id'] = (int)$result['key']['id'];
            self::$ctx['key_prefix'] = $result['key']['key_prefix'];
            Response::json(4005, '密钥权限不足，需要 ' . $scope . ' 权限');
        }
        $this->key = $result['key'];
        self::$ctx['key_id'] = (int)$result['key']['id'];
        self::$ctx['key_prefix'] = $result['key']['key_prefix'];
        return $this->key;
    }

    private function ok($data = array(), $msg = 'ok')
    {
        self::$ctx['code'] = 0;
        Response::success($data, $msg);
    }

    private function fail($code, $msg)
    {
        self::$ctx['code'] = $code;
        Response::json($code, $msg);
    }

    /** 把 list/create 等动作的连字符去掉 */
    private function norm($op)
    {
        return str_replace('-', '', strtolower((string)$op));
    }

    /* ==================== 连通性 ==================== */

    public function ping($op = 'index')
    {
        $key = $this->auth(null);
        $this->ok(array(
            'service' => 'license-openapi',
            'time' => time(),
            'key_name' => $key['name'],
            'key_prefix' => $key['key_prefix'],
            'scopes' => json_decode($key['scopes'], true),
        ));
    }

    /* ==================== 密钥管理 ==================== */

    public function keys($op = 'list')
    {
        $p = $this->params();
        switch ($this->norm($op)) {
            case 'list':
                $this->auth('key:read');
                $rows = ApiKeyRepo::all();
                foreach ($rows as &$row) {
                    unset($row['key_hash']);
                    $row['scopes'] = json_decode($row['scopes'], true);
                }
                $this->ok(array('list' => $rows, 'total' => count($rows)));
                break;

            case 'create':
                $key = $this->auth('key:write');
                $scopes = $this->getp($p, 'scopes', array());
                if (is_string($scopes)) {
                    $scopes = json_decode($scopes, true);
                    if (!is_array($scopes)) {
                        $scopes = array_filter(array_map('trim', explode(',', $scopes)));
                    }
                }
                try {
                    $created = ApiKeyService::create(
                        $this->getp($p, 'name', ''),
                        $scopes,
                        $this->intp($p, 'days', 0),
                        (int)$key['created_by']
                    );
                } catch (\Exception $e) {
                    $this->fail(1001, $e->getMessage());
                }
                // 明文只在这里出现一次
                $this->ok(array('key' => $created['key'], 'id' => $created['row']['id']), '密钥已创建，请立即保存明文');
                break;

            case 'revoke':
                $this->auth('key:write');
                $id = $this->intp($p, 'id', 0);
                if ($id <= 0) {
                    $this->fail(1001, '请提供 id');
                }
                $row = ApiKeyRepo::find($id);
                if (!$row) {
                    $this->fail(2001, '密钥不存在');
                }
                if ((int)$row['id'] === (int)$this->key['id']) {
                    $this->fail(1001, '不能撤销当前正在使用的密钥');
                }
                ApiKeyRepo::revoke($id);
                $this->ok(array('id' => $id), '已撤销');
                break;

            case 'self':
                $key = $this->auth('key:read');
                $out = $key;
                unset($out['key_hash']);
                $out['scopes'] = json_decode($key['scopes'], true);
                $this->ok($out);
                break;

            case 'scopes':
                $this->auth('key:read');
                $this->ok(array('scopes' => ApiKeyService::ALL_SCOPES));
                break;

            default:
                $this->auth('key:read');
                $this->fail(1001, '未知操作：keys/' . $op);
        }
    }

    /* ==================== 软件管理 ==================== */

    public function software($op = 'list')
    {
        $p = $this->params();
        switch ($this->norm($op)) {
            case 'list':
                $this->auth('software:read');
                $rows = SoftwareRepo::all();
                $keyword = trim((string)$this->getp($p, 'keyword', ''));
                if ($keyword !== '') {
                    $rows = array_values(array_filter($rows, function ($r) use ($keyword) {
                        return strpos($r['name'], $keyword) !== false
                            || strpos($r['identifier'], $keyword) !== false;
                    }));
                }
                if ($this->getp($p, 'status', '') !== '') {
                    $st = (string)$this->getp($p, 'status');
                    $rows = array_values(array_filter($rows, function ($r) use ($st) {
                        return (string)$r['status'] === $st;
                    }));
                }
                $total = count($rows);
                $page = max(1, $this->intp($p, 'page', 1));
                $per = max(1, min(200, $this->intp($p, 'per_page', 50)));
                $rows = array_slice($rows, ($page - 1) * $per, $per);
                foreach ($rows as &$r) {
                    $r['default_config'] = json_decode($r['default_config'], true);
                }
                $this->ok(array('list' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $per));
                break;

            case 'get':
                $this->auth('software:read');
                $row = $this->findSoftware($p);
                if (!$row) {
                    $this->fail(2001, '软件不存在');
                }
                $row['default_config'] = json_decode($row['default_config'], true);
                $this->ok($row);
                break;

            case 'create':
                $this->auth('software:write');
                $name = trim((string)$this->getp($p, 'name', ''));
                $identifier = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$this->getp($p, 'identifier', ''));
                if ($name === '' || $identifier === '') {
                    $this->fail(1001, 'name 与 identifier 不能为空');
                }
                if (SoftwareRepo::findByIdentifier($identifier)) {
                    $this->fail(1001, '软件标识已存在');
                }
                $config = $this->configValue($p);
                try {
                    $id = SoftwareService::create(array(
                        'name' => $name,
                        'identifier' => $identifier,
                        'default_mode' => $this->getp($p, 'mode', 'time') === 'times' ? 'times' : 'time',
                        'default_days' => max(1, $this->intp($p, 'days', 30)),
                        'default_times' => max(1, $this->intp($p, 'times', 100)),
                        'default_config' => $config,
                    ));
                } catch (\Exception $e) {
                    $this->fail(9001, '创建失败：' . $e->getMessage());
                }
                LogRepo::admin(0, 'openapi', 'software.create', $identifier, array('api_key' => $this->key['key_prefix']));
                $this->ok(array('id' => $id, 'identifier' => $identifier), '软件已创建，已自动生成独立密钥对 B');
                break;

            case 'update':
                $this->auth('software:write');
                $id = $this->intp($p, 'id', 0);
                $row = SoftwareRepo::find($id);
                if (!$row) {
                    $this->fail(2001, '软件不存在');
                }
                $data = array();
                if ($this->getp($p, 'name', '') !== '') {
                    $data['name'] = trim((string)$this->getp($p, 'name'));
                }
                if ($this->getp($p, 'mode', '') !== '') {
                    $data['default_mode'] = $this->getp($p, 'mode') === 'times' ? 'times' : 'time';
                }
                if ($this->getp($p, 'days', 0)) {
                    $data['default_days'] = max(1, $this->intp($p, 'days'));
                }
                if ($this->getp($p, 'times', 0)) {
                    $data['default_times'] = max(1, $this->intp($p, 'times'));
                }
                if (array_key_exists('config', $p) || array_key_exists('ai', $p)
                    || array_key_exists('text', $p) || array_key_exists('json', $p)) {
                    $data['default_config'] = $this->configValue($p);
                }
                if ($data) {
                    SoftwareRepo::update($id, $data);
                }
                LogRepo::admin(0, 'openapi', 'software.update', (string)$id, array('api_key' => $this->key['key_prefix']));
                $this->ok(array('id' => $id), '已保存；软件标识不可通过 API 修改');
                break;

            case 'status':
                $this->auth('software:write');
                $id = $this->intp($p, 'id', 0);
                if (!SoftwareRepo::find($id)) {
                    $this->fail(2001, '软件不存在');
                }
                SoftwareRepo::setStatus($id, $this->intp($p, 'status', 1) === 1 ? 1 : 0);
                $this->ok(array('id' => $id));
                break;

            case 'publickey':
                $this->auth('software:read');
                $row = $this->findSoftware($p);
                if (!$row) {
                    $this->fail(2001, '软件不存在');
                }
                $this->ok(array(
                    'software_id' => $row['id'],
                    'identifier' => $row['identifier'],
                    'key_id' => $row['b_key_id'],
                    'pem' => $row['public_b'],
                ));
                break;

            case 'publica':
                $this->auth('software:read');
                $this->ok(array('pem' => SoftwareService::publicA()));
                break;

            case 'rotatekey':
                $this->auth('software:write');
                $id = $this->intp($p, 'id', 0);
                if (!SoftwareRepo::find($id)) {
                    $this->fail(2001, '软件不存在');
                }
                SoftwareService::rotateKey($id);
                LogRepo::admin(0, 'openapi', 'software.rotate_key', (string)$id, array('api_key' => $this->key['key_prefix']));
                $this->ok(array('id' => $id), '密钥已轮换，旧 K 码全部作废，请重新生成');
                break;

            case 'delete':
                $this->auth('software:write');
                $id = $this->intp($p, 'id', 0);
                $row = $id > 0 ? SoftwareRepo::find($id) : null;
                if (!$row) {
                    $this->fail(2001, '软件不存在');
                }
                $used = (int)\App\Core\DB::value('SELECT COUNT(*) FROM {cards} WHERE software_id = ?', array($id));
                if ($used > 0) {
                    $this->fail(1001, '该软件下还有 ' . $used . ' 张卡密，请先处理卡密');
                }
                \App\Core\DB::execute('DELETE FROM {softwares} WHERE id = ?', array($id));
                if (!empty($row['private_b_path'])) {
                    $path = \App\Core\Config::root() . '/keys/' . basename($row['private_b_path']);
                    if (is_file($path)) {
                        @unlink($path);
                    }
                }
                LogRepo::admin(0, 'openapi', 'software.delete', $row['identifier'], array('api_key' => $this->key['key_prefix']));
                $this->ok(array('id' => $id), '软件已删除');
                break;

            case 'models':
                $this->auth('software:read');
                $models = $this->fetchModels(
                    trim((string)$this->getp($p, 'endpoint', '')),
                    trim((string)$this->getp($p, 'key', ''))
                );
                if (isset($models['error'])) {
                    $this->fail(1001, $models['error']);
                }
                $this->ok($models);
                break;

            default:
                $this->auth('software:read');
                $this->fail(1001, '未知操作：software/' . $op);
        }
    }

    /**
     * 从请求里取配置：config 为数组优先；否则由 ai/text/json/script 拼装
     */
    private function configValue($p)
    {
        $cfg = $this->getp($p, 'config', null);
        if (is_array($cfg)) {
            // 过滤：配置结构由服务端定义，只保留已开放的键
            $allowed = array('ai', 'text', 'json', 'script');
            $out = array();
            foreach ($cfg as $k => $v) {
                if (in_array($k, $allowed, true)) {
                    $out[$k] = $v;
                }
            }
            if (isset($out['ai']) && is_array($out['ai'])) {
                $out['ai'] = $this->pickAiFields($out['ai']);
            }
            return json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if (is_string($cfg) && trim($cfg) !== '') {
            $decoded = json_decode($cfg, true);
            if (is_array($decoded)) {
                return $this->configValue(array('config' => $decoded));
            }
        }
        $out = array();
        if ($this->getp($p, 'ai', null) !== null) {
            $ai = $this->getp($p, 'ai');
            if (is_array($ai)) {
                $out['ai'] = $this->pickAiFields($ai);
            }
        }
        foreach (array('text', 'script') as $k) {
            if ($this->getp($p, $k, null) !== null) {
                $out[$k] = (string)$this->getp($p, $k);
            }
        }
        if ($this->getp($p, 'json', null) !== null) {
            $j = $this->getp($p, 'json');
            if (is_string($j)) {
                $j = json_decode($j, true);
            }
            if (is_array($j)) {
                $out['json'] = $j;
            }
        }
        return json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** AI 配置仅保留 endpoint/key/model 三个开放字段 */
    private function pickAiFields($ai)
    {
        $out = array();
        foreach (array('endpoint', 'key', 'model') as $f) {
            if (isset($ai[$f]) && $ai[$f] !== '') {
                $out[$f] = $ai[$f];
            }
        }
        return $out;
    }

    /**
     * 拉取模型列表（复用后台逻辑）
     */
    private function fetchModels($endpoint, $key)
    {
        if ($endpoint === '' || $key === '') {
            return array('error' => '请提供 endpoint 与 key');
        }
        $u = rtrim($endpoint, '/');
        foreach (array('/chat/completions', '/completions', '/messages', '/responses') as $suffix) {
            if (substr($u, -strlen($suffix)) === $suffix) {
                $u = substr($u, 0, -strlen($suffix));
                break;
            }
        }
        if (substr($u, -7) !== '/models') {
            $u .= '/models';
        }
        $scheme = strtolower((string)parse_url($u, PHP_URL_SCHEME));
        if ($scheme !== 'http' && $scheme !== 'https') {
            return array('error' => 'endpoint 必须以 http:// 或 https:// 开头');
        }
        if (!function_exists('curl_init')) {
            return array('error' => '服务器未启用 curl 扩展');
        }
        $ch = curl_init($u);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $key, 'Accept: application/json'),
        ));
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false) {
            return array('error' => '请求失败：' . $err);
        }
        $json = json_decode($body, true);
        if (!is_array($json)) {
            return array('error' => '接口返回不是 JSON（HTTP ' . $status . '）');
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
        return array('url' => $u, 'models' => $list, 'total' => count($list));
    }

    /* ==================== 卡密管理 ==================== */

    public function card($op = 'list')
    {
        $p = $this->params();
        switch ($this->norm($op)) {
            case 'list':
                $this->auth('card:read');
                $filter = array(
                    'software_id' => $this->getp($p, 'software_id', ''),
                    'status' => $this->getp($p, 'status', ''),
                    'keyword' => $this->getp($p, 'keyword', ''),
                );
                $page = max(1, $this->intp($p, 'page', 1));
                $per = max(1, min(200, $this->intp($p, 'per_page', 50)));
                $list = CardRepo::paginate($filter, ($page - 1) * $per, $per);
                $this->ok(array(
                    'list' => $list,
                    'total' => CardRepo::count($filter),
                    'page' => $page,
                    'per_page' => $per,
                ));
                break;

            case 'get':
                $this->auth('card:read');
                $row = $this->findCard($p);
                if (!$row) {
                    $this->fail(2001, '卡密不存在');
                }
                $sw = SoftwareRepo::find($row['software_id']);
                $row['software_name'] = $sw ? $sw['name'] : '';
                $row['software_identifier'] = $sw ? $sw['identifier'] : '';
                $row['custom_config'] = $row['custom_config'] ? json_decode($row['custom_config'], true) : null;
                $row['stats'] = LogRepo::cardStats($row['card_id']);
                $this->ok($row);
                break;

            case 'generate':
                $this->auth('card:write');
                $softwareId = $this->intp($p, 'software_id', 0);
                try {
                    $result = CardService::generate(array(
                        'software_id' => $softwareId,
                        'mode' => $this->getp($p, 'mode', 'time') === 'times' ? 'times' : 'time',
                        'days' => $this->getp($p, 'days', 30),
                        'times' => $this->getp($p, 'times', 100),
                        'count' => $this->getp($p, 'count', 1),
                        'remark' => $this->getp($p, 'remark', ''),
                        'custom_config' => $this->buildCardConfig($p),
                    ));
                } catch (\Exception $e) {
                    $this->fail(1001, $e->getMessage());
                }
                LogRepo::admin(0, 'openapi', 'card.generate', $result['batch'],
                    array('count' => count($result['cards']), 'api_key' => $this->key['key_prefix']));
                $this->ok($result, '发卡成功');
                break;

            case 'update':
                $this->auth('card:write');
                $row = $this->findCard($p);
                if (!$row) {
                    $this->fail(2001, '卡密不存在');
                }
                $data = array();
                if ($this->getp($p, 'remark', null) !== null) {
                    $data['remark'] = (string)$this->getp($p, 'remark');
                }
                if (array_key_exists('custom_config', $p) || array_key_exists('ai', $p)
                    || array_key_exists('extra', $p)) {
                    $data['custom_config'] = $this->buildCardConfig($p, $row['custom_config']);
                }
                if ($data) {
                    \App\Core\DB::update('cards', $data, 'id = :id', array(':id' => $row['id']));
                }
                $this->ok(array('id' => $row['id']), '已更新');
                break;

            case 'extend':
                $this->auth('card:write');
                $row = $this->findCard($p);
                $days = $this->intp($p, 'days', 0);
                if (!$row || $days <= 0) {
                    $this->fail(1001, '参数错误');
                }
                CardRepo::extend($row['id'], max((int)$row['expire_at'], time()) + $days * 86400);
                $this->ok(array('id' => $row['id']), '已延期');
                break;

            case 'settimes':
                $this->auth('card:write');
                $row = $this->findCard($p);
                $remain = $this->intp($p, 'remain', -1);
                if (!$row || $remain < 0) {
                    $this->fail(1001, '参数错误');
                }
                CardRepo::setTimes($row['id'], $remain, max((int)$row['total_times'], $remain));
                $this->ok(array('id' => $row['id']), '已更新');
                break;

            case 'status':
                $this->auth('card:write');
                $row = $this->findCard($p);
                if (!$row) {
                    $this->fail(2001, '卡密不存在');
                }
                CardRepo::setStatus($row['id'], $this->intp($p, 'status', 1) === 1 ? 1 : 0);
                $this->ok(array('id' => $row['id']));
                break;

            case 'delete':
                $this->auth('card:write');
                $row = $this->findCard($p);
                if (!$row) {
                    $this->fail(2001, '卡密不存在');
                }
                CardRepo::delete($row['id']);
                $this->ok(array('id' => $row['id']), '已删除');
                break;

            case 'linkstats':
                $this->auth('card:read');
                $this->ok(CardRepo::linkStats());
                break;

            case 'parsekcode':
                $this->auth('card:read');
                $parsed = CardService::parseKcode((string)$this->getp($p, 'kcode', ''));
                if (!$parsed) {
                    $this->fail(3001, 'K 码格式错误或校验失败');
                }
                $this->ok($parsed);
                break;

            case 'convertkcode':
                $this->auth('card:write');
                try {
                    $new = CardService::convertKcode(
                        (string)$this->getp($p, 'kcode', ''),
                        trim((string)$this->getp($p, 'new_url', ''))
                    );
                } catch (\Exception $e) {
                    $this->fail(3001, $e->getMessage());
                }
                $this->ok(array('kcode' => $new), '地址已替换并重新签名');
                break;

            default:
                $this->auth('card:read');
                $this->fail(1001, '未知操作：card/' . $op);
        }
    }

    /**
     * 构造卡专属配置。
     * AI 只覆盖 endpoint/key/model 三个开放字段，结构继承软件默认配置；
     * 额外配置支持 text/json/script 三选一。
     */
    private function buildCardConfig($p, $existing = '')
    {
        $base = array();
        if ($existing) {
            $decoded = json_decode($existing, true);
            if (is_array($decoded)) {
                $base = $decoded;
            }
        }
        if (!$base) {
            $sw = SoftwareRepo::find((int)$this->getp($p, 'software_id', 0));
            if ($sw) {
                $decoded = json_decode($sw['default_config'], true);
                if (is_array($decoded)) {
                    $base = $decoded;
                }
            }
        }

        $cfg = array();
        $ai = $this->getp($p, 'ai', null);
        if (is_array($ai) && !empty($ai['enabled'])) {
            $merged = isset($base['ai']) && is_array($base['ai']) ? $base['ai'] : array();
            foreach (array('endpoint', 'key', 'model') as $f) {
                if (isset($ai[$f]) && $ai[$f] !== '') {
                    $merged[$f] = $ai[$f];
                }
            }
            if (!$merged) {
                throw new \RuntimeException('AI 配置不完整');
            }
            $cfg['ai'] = $merged;
        }

        $extra = $this->getp($p, 'extra', null);
        if (is_array($extra) && !empty($extra['enabled'])) {
            $type = isset($extra['type']) ? (string)$extra['type'] : 'text';
            $content = isset($extra['content']) ? (string)$extra['content'] : '';
            if ($type === 'json') {
                $decoded = json_decode($content, true);
                if (!is_array($decoded)) {
                    throw new \RuntimeException('额外 JSON 内容格式不正确');
                }
                $cfg['json'] = $decoded;
            } elseif ($type === 'script') {
                if (trim($content) === '') {
                    throw new \RuntimeException('脚本内容不能为空');
                }
                $cfg['script'] = $content;
            } else {
                if (trim($content) === '') {
                    throw new \RuntimeException('自定义文本不能为空');
                }
                $cfg['text'] = $content;
            }
        }
        if (!$cfg) {
            return '';
        }
        $json = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json === false ? '' : $json;
    }

    /* ==================== 统计与日志 ==================== */

    public function stats($op = 'overview')
    {
        switch ($this->norm($op)) {
            case 'overview':
                $this->auth('log:read');
                $this->ok(LogService::overview());
                break;
            case 'card':
                $this->auth('log:read');
                $this->ok(CardRepo::stats());
                break;
            case 'link':
                $this->auth('log:read');
                $this->ok(CardRepo::linkStats());
                break;
            default:
                $this->auth('log:read');
                $this->fail(1001, '未知操作：stats/' . $op);
        }
    }

    public function log($op = 'requests')
    {
        $p = $this->params();
        switch ($this->norm($op)) {
            case 'requests':
                $this->auth('log:read');
                $filter = array(
                    'card_id' => $this->getp($p, 'card_id', ''),
                    'software_id' => $this->getp($p, 'software_id', ''),
                    'result' => $this->getp($p, 'result', ''),
                    'start' => $this->getp($p, 'start', ''),
                    'end' => $this->getp($p, 'end', ''),
                );
                $per = max(1, min(200, $this->intp($p, 'per_page', 50)));
                $this->ok(LogService::requests($filter, $this->intp($p, 'page', 1), $per));
                break;

            case 'admin':
                $this->auth('log:read');
                $this->ok(array('list' => LogRepo::adminLogs($this->intp($p, 'limit', 100))));
                break;

            case 'calls':
                $this->auth('log:read');
                $this->ok(array('list' => ApiKeyRepo::callLogs($this->intp($p, 'limit', 100))));
                break;

            case 'clean':
                $this->auth('log:write');
                $days = max(1, $this->intp($p, 'days', 180));
                LogRepo::clean($days);
                \App\Repository\TicketRepo::cleanExpired();
                $this->ok(array('days' => $days), '清理完成');
                break;

            default:
                $this->auth('log:read');
                $this->fail(1001, '未知操作：log/' . $op);
        }
    }

    /* ==================== 管理员账号 ==================== */

    public function admin($op = 'list')
    {
        $p = $this->params();
        switch ($this->norm($op)) {
            case 'list':
                $this->auth('admin:read');
                $rows = \App\Service\AdminService::all();
                foreach ($rows as &$r) {
                    unset($r['password_hash']);
                }
                $this->ok(array('list' => $rows, 'total' => count($rows)));
                break;

            case 'create':
                $this->auth('admin:write');
                $username = trim((string)$this->getp($p, 'username', ''));
                $password = (string)$this->getp($p, 'password', '');
                if ($username === '' || strlen($password) < 6) {
                    $this->fail(1001, '用户名不能为空且密码至少 6 位');
                }
                try {
                    $id = \App\Service\AdminService::create(
                        $username,
                        $password,
                        $this->intp($p, 'role', 2) === 1 ? 1 : 2
                    );
                } catch (\Exception $e) {
                    $this->fail(1001, $e->getMessage());
                }
                LogRepo::admin(0, 'openapi', 'admin.create', $username,
                    array('api_key' => $this->key['key_prefix']));
                $this->ok(array('id' => $id), '管理员已创建');
                break;

            case 'delete':
                $this->auth('admin:write');
                $id = $this->intp($p, 'id', 0);
                $row = \App\Repository\AdminRepo::find($id);
                if (!$row) {
                    $this->fail(2001, '管理员不存在');
                }
                if ((int)$row['id'] === 1) {
                    $this->fail(1001, '不能删除初始超级管理员');
                }
                \App\Repository\AdminRepo::delete($id);
                LogRepo::admin(0, 'openapi', 'admin.delete', $row['username'],
                    array('api_key' => $this->key['key_prefix']));
                $this->ok(array('id' => $id), '已删除');
                break;

            case 'status':
                $this->auth('admin:write');
                $id = $this->intp($p, 'id', 0);
                if (!\App\Repository\AdminRepo::find($id)) {
                    $this->fail(2001, '管理员不存在');
                }
                if ($id === 1) {
                    $this->fail(1001, '不能停用初始超级管理员');
                }
                \App\Repository\AdminRepo::setStatus($id, $this->intp($p, 'status', 1) === 1 ? 1 : 0);
                $this->ok(array('id' => $id));
                break;

            case 'resetpassword':
                $this->auth('admin:write');
                $id = $this->intp($p, 'id', 0);
                $password = (string)$this->getp($p, 'password', '');
                if (!\App\Repository\AdminRepo::find($id) || strlen($password) < 6) {
                    $this->fail(1001, '管理员不存在或密码不足 6 位');
                }
                \App\Service\AdminService::resetPassword($id, $password);
                LogRepo::admin(0, 'openapi', 'admin.reset_password', (string)$id,
                    array('api_key' => $this->key['key_prefix']));
                $this->ok(array('id' => $id), '密码已重置');
                break;

            default:
                $this->auth('admin:read');
                $this->fail(1001, '未知操作：admin/' . $op);
        }
    }

    /* ==================== 系统设置 ==================== */

    public function system($op = 'info')
    {
        $p = $this->params();
        switch ($this->norm($op)) {
            case 'info':
                $this->auth('system:read');
                $this->ok(array(
                    'app' => '软件激活与授权管理系统',
                    'openapi_version' => '1.0',
                    'php' => PHP_VERSION,
                    'time' => time(),
                    'installed_at' => \App\Core\DB::value('SELECT svalue FROM {settings} WHERE skey = ?', array('installed_at')),
                    'license_url' => CardService::licenseUrl(),
                    'softwares' => (int)\App\Core\DB::value('SELECT COUNT(*) FROM {softwares}'),
                    'cards' => (int)\App\Core\DB::value('SELECT COUNT(*) FROM {cards}'),
                ));
                break;

            case 'licenseurlget':
                $this->auth('system:read');
                $this->ok(array('url' => CardService::licenseUrl()));
                break;

            case 'licenseurlset':
                $this->auth('system:write');
                $url = trim((string)$this->getp($p, 'url', ''));
                if ($url === '') {
                    $this->fail(1001, 'url 不能为空');
                }
                CardService::setLicenseUrl($url);
                $this->ok(array('url' => $url), '已保存；已有 K 码需通过 card/convert-kcode 转换');
                break;

            default:
                $this->auth('system:read');
                $this->fail(1001, '未知操作：system/' . $op);
        }
    }

    /* ==================== 客户端授权接口（便于 AI 一次性联调） ==================== */

    public function license($op = 'status')
    {
        $p = $this->params();
        $this->auth('license:call');
        $params = array(
            'card_id' => $this->getp($p, 'card_id', ''),
            'software_id' => $this->getp($p, 'software_id', $this->getp($p, 'identifier', '')),
            'device_id' => $this->getp($p, 'device_id', ''),
            'nonce' => $this->getp($p, 'nonce', ''),
            'timestamp' => $this->getp($p, 'timestamp', time()),
            'ticket' => $this->getp($p, 'ticket', ''),
        );
        switch ($this->norm($op)) {
            case 'status':
                $result = LicenseService::status($params);
                break;
            case 'activate':
                $result = LicenseService::activate($params);
                break;
            case 'ticket':
                $result = LicenseService::ticket($params);
                break;
            case 'refund':
                $result = LicenseService::refund($params);
                break;
            default:
                $this->fail(1001, '未知操作：license/' . $op);
                return;
        }
        self::$ctx['code'] = $result['code'];
        Response::json($result['code'], isset($result['msg']) ? $result['msg'] : 'ok', $result['data']);
    }

    /* ==================== 内部查找辅助 ==================== */

    private function findSoftware($p)
    {
        $id = $this->intp($p, 'id', 0);
        if ($id > 0) {
            return SoftwareRepo::find($id);
        }
        $identifier = trim((string)$this->getp($p, 'identifier', ''));
        return $identifier !== '' ? SoftwareRepo::findByIdentifier($identifier) : null;
    }

    private function findCard($p)
    {
        $id = $this->intp($p, 'id', 0);
        if ($id > 0) {
            return CardRepo::find($id);
        }
        $cardId = trim((string)$this->getp($p, 'card_id', ''));
        return $cardId !== '' ? CardRepo::findByCardId($cardId) : null;
    }
}
