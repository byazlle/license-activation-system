<?php
namespace App\Repository;

use App\Core\DB;

/**
 * API 密钥仓库
 * 密钥只存 SHA256 哈希，明文仅在创建时返回一次。
 * 表结构在首次访问时自动补建，兼容已安装的老站点（免手动迁移）。
 */
class ApiKeyRepo
{
    /** 避免每个请求重复执行建表检查 */
    private static $checked = false;

    /**
     * 自动补建 api_keys / api_call_logs 表
     */
    public static function ensureTables()
    {
        if (self::$checked) {
            return;
        }
        $pdo = DB::pdo();
        $keys = DB::table('api_keys');
        $calls = DB::table('api_call_logs');
        $pdo->exec('CREATE TABLE IF NOT EXISTS `' . $keys . '` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(128) NOT NULL COMMENT "密钥名称",
            `key_prefix` VARCHAR(16) NOT NULL COMMENT "明文前 12 位，便于识别",
            `key_hash` CHAR(64) NOT NULL COMMENT "SHA256 哈希",
            `scopes` TEXT NULL COMMENT "权限范围 JSON 数组，* 表示全部",
            `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT "1启用 0撤销",
            `expires_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT "0永不过期",
            `last_used_at` INT UNSIGNED NOT NULL DEFAULT 0,
            `last_used_ip` VARCHAR(45) DEFAULT NULL,
            `created_by` BIGINT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_key_hash` (`key_hash`),
            KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT="API 密钥表"');
        $pdo->exec('CREATE TABLE IF NOT EXISTS `' . $calls . '` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `key_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
            `key_prefix` VARCHAR(16) DEFAULT NULL,
            `endpoint` VARCHAR(128) DEFAULT NULL,
            `ip` VARCHAR(45) DEFAULT NULL,
            `code` INT UNSIGNED NOT NULL DEFAULT 0,
            `cost_ms` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `idx_key_time` (`key_id`,`created_at`),
            KEY `idx_time` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT="开放 API 调用日志"');
        self::$checked = true;
    }

    public static function find($id)
    {
        self::ensureTables();
        return DB::fetch('SELECT * FROM {api_keys} WHERE id = ?', array($id));
    }

    public static function findByHash($hash)
    {
        self::ensureTables();
        return DB::fetch('SELECT * FROM {api_keys} WHERE key_hash = ?', array($hash));
    }

    public static function all()
    {
        self::ensureTables();
        return DB::fetchAll('SELECT * FROM {api_keys} ORDER BY id DESC');
    }

    public static function create($data)
    {
        self::ensureTables();
        $data['created_at'] = time();
        return DB::insert('api_keys', $data);
    }

    public static function revoke($id)
    {
        self::ensureTables();
        return DB::update('api_keys', array('status' => 0), 'id = :id', array(':id' => $id));
    }

    public static function touch($id, $ip)
    {
        self::ensureTables();
        return DB::update('api_keys', array(
            'last_used_at' => time(),
            'last_used_ip' => $ip,
        ), 'id = :id', array(':id' => $id));
    }

    /**
     * 记录一次开放 API 调用（失败也要记）
     */
    public static function logCall($d)
    {
        self::ensureTables();
        try {
            DB::insert('api_call_logs', array(
                'key_id' => isset($d['key_id']) ? (int)$d['key_id'] : 0,
                'key_prefix' => isset($d['key_prefix']) ? $d['key_prefix'] : null,
                'endpoint' => isset($d['endpoint']) ? $d['endpoint'] : '',
                'ip' => isset($d['ip']) ? $d['ip'] : \App\Core\Util::ip(),
                'code' => isset($d['code']) ? (int)$d['code'] : 0,
                'cost_ms' => isset($d['start'])
                    ? (int)round((microtime(true) - $d['start']) * 1000) : 0,
                'created_at' => time(),
            ));
        } catch (\Exception $e) {
            // 日志失败不影响接口
        }
    }

    public static function callLogs($limit = 100)
    {
        self::ensureTables();
        $limit = max(1, min(500, (int)$limit));
        return DB::fetchAll('SELECT * FROM {api_call_logs} ORDER BY id DESC LIMIT ' . $limit);
    }
}
