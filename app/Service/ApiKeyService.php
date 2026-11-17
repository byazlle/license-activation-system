<?php
namespace App\Service;

use App\Core\Util;
use App\Repository\ApiKeyRepo;

/**
 * API 密钥服务
 * 密钥格式：lsk_ + 40 位大小写字母数字；数据库只保存 SHA256。
 */
class ApiKeyService
{
    const ALL_SCOPES = array(
        '*',
        'software:read', 'software:write',
        'card:read', 'card:write',
        'key:read', 'key:write',
        'log:read', 'log:write',
        'admin:read', 'admin:write',
        'system:read', 'system:write',
        'license:call',
    );

    /**
     * 创建新密钥
     * @param string $name 名称
     * @param array $scopes 权限数组；空表示全部
     * @param int $days 有效天数，0 永久
     * @param int $createdBy 创建人 ID
     * @return array ['key'=>明文, 'row'=>数据库记录]
     */
    public static function create($name, $scopes, $days, $createdBy = 0)
    {
        $name = trim((string)$name);
        if ($name === '') {
            throw new \RuntimeException('密钥名称不能为空');
        }
        $scopes = self::normalizeScopes($scopes);
        $plain = 'lsk_' . Util::random(40);
        $id = ApiKeyRepo::create(array(
            'name' => $name,
            'key_prefix' => substr($plain, 0, 12),
            'key_hash' => hash('sha256', $plain),
            'scopes' => json_encode($scopes, JSON_UNESCAPED_UNICODE),
            'status' => 1,
            'expires_at' => $days > 0 ? time() + (int)$days * 86400 : 0,
            'created_by' => (int)$createdBy,
        ));
        return array('key' => $plain, 'row' => ApiKeyRepo::find($id));
    }

    /**
     * 校验密钥
     * @param string $plain 明文
     * @return array ['ok'=>bool,'code'=>int,'msg'=>string,'key'=>array|null]
     */
    public static function verify($plain)
    {
        $plain = trim((string)$plain);
        if ($plain === '') {
            return array('ok' => false, 'code' => 4001, 'msg' => '缺少 API 密钥，请使用 Authorization: Bearer <key> 或 X-API-Key 传递', 'key' => null);
        }
        $row = ApiKeyRepo::findByHash(hash('sha256', $plain));
        if (!$row) {
            return array('ok' => false, 'code' => 4002, 'msg' => 'API 密钥无效', 'key' => null);
        }
        if ((int)$row['status'] !== 1) {
            return array('ok' => false, 'code' => 4003, 'msg' => 'API 密钥已撤销', 'key' => null);
        }
        if ((int)$row['expires_at'] > 0 && (int)$row['expires_at'] < time()) {
            return array('ok' => false, 'code' => 4004, 'msg' => 'API 密钥已过期', 'key' => null);
        }
        ApiKeyRepo::touch($row['id'], Util::ip());
        return array('ok' => true, 'code' => 0, 'msg' => 'ok', 'key' => $row);
    }

    /**
     * 判断密钥是否拥有指定权限
     * 支持 "*" 全权限、"software:*" 前缀通配、精确匹配
     */
    public static function allows($keyRow, $need)
    {
        $scopes = json_decode(isset($keyRow['scopes']) ? $keyRow['scopes'] : '[]', true);
        if (!is_array($scopes)) {
            return false;
        }
        foreach ($scopes as $s) {
            if ($s === '*') {
                return true;
            }
            if ($s === $need) {
                return true;
            }
            if (substr((string)$s, -2) === ':*' && strpos($need, substr($s, 0, -1)) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * 规整权限数组：过滤非法值、去重；空则给全权限
     */
    public static function normalizeScopes($scopes)
    {
        if (!is_array($scopes)) {
            $scopes = array();
        }
        $out = array();
        foreach ($scopes as $s) {
            $s = trim((string)$s);
            if (in_array($s, self::ALL_SCOPES, true) && !in_array($s, $out, true)) {
                $out[] = $s;
            }
        }
        if (!$out) {
            $out = array('*');
        }
        return $out;
    }

    /**
     * 从请求中提取密钥：Header > Query/Body
     */
    public static function requestKey()
    {
        $auth = isset($_SERVER['HTTP_AUTHORIZATION']) ? trim($_SERVER['HTTP_AUTHORIZATION']) : '';
        if ($auth !== '' && preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            return trim($m[1]);
        }
        if (!empty($_SERVER['HTTP_X_API_KEY'])) {
            return trim($_SERVER['HTTP_X_API_KEY']);
        }
        $k = Util::param('api_key', '');
        return is_string($k) ? trim($k) : '';
    }
}
