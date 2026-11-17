<?php
namespace App\Core;

/**
 * 数据库访问层（PDO 单例）
 * 全部使用预处理语句，防 SQL 注入。兼容 MySQL 5.6。
 */
class DB
{
    /** @var \PDO|null */
    private static $pdo = null;

    /** @var string 表前缀 */
    private static $prefix = '';

    /**
     * 获取 PDO 实例
     * @return \PDO
     */
    public static function pdo()
    {
        if (self::$pdo === null) {
            $host = Config::get('db.host', '127.0.0.1');
            $port = Config::get('db.port', '3306');
            $name = Config::get('db.name', '');
            $user = Config::get('db.user', 'root');
            $pass = Config::get('db.pass', '');
            $charset = Config::get('db.charset', 'utf8mb4');
            self::$prefix = Config::get('db.prefix', 'lc_');
            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
            self::$pdo = new \PDO($dsn, $user, $pass, array(
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ));
        }
        return self::$pdo;
    }

    /**
     * 供安装阶段使用自定义参数测试连接
     * @param array $cfg db 配置
     * @return \PDO
     */
    public static function connect($cfg)
    {
        $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['name']};charset=utf8mb4";
        return new \PDO($dsn, $cfg['user'], $cfg['pass'], array(
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ));
    }

    /**
     * 带前缀的表名
     * @param string $name 表名（不含前缀）
     * @return string
     */
    public static function table($name)
    {
        if (self::$prefix === '' && self::$pdo === null) {
            self::$prefix = Config::get('db.prefix', 'lc_');
        }
        return self::$prefix . $name;
    }

    /**
     * 执行 SQL，返回受影响行数
     * @param string $sql SQL（表名用 {table} 占位）
     * @param array $params 绑定参数
     * @return int
     */
    public static function execute($sql, $params = array())
    {
        $sql = self::replaceTable($sql);
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * 查询多条
     * @param string $sql SQL
     * @param array $params 绑定参数
     * @return array
     */
    public static function fetchAll($sql, $params = array())
    {
        $sql = self::replaceTable($sql);
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * 查询单条
     * @param string $sql SQL
     * @param array $params 绑定参数
     * @return array|null
     */
    public static function fetch($sql, $params = array())
    {
        $sql = self::replaceTable($sql);
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * 查询单值
     * @param string $sql SQL
     * @param array $params 绑定参数
     * @return mixed
     */
    public static function value($sql, $params = array())
    {
        $row = self::fetch($sql, $params);
        return $row ? reset($row) : null;
    }

    /**
     * 插入并返回自增 ID
     * @param string $table 表名（不含前缀）
     * @param array $data 字段=>值
     * @return string
     */
    public static function insert($table, $data)
    {
        $columns = array_keys($data);
        $placeholders = array();
        foreach ($columns as $col) {
            $placeholders[] = ':' . $col;
        }
        $sql = 'INSERT INTO ' . self::table($table)
            . ' (`' . implode('`,`', $columns) . '`) VALUES (' . implode(',', $placeholders) . ')';
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($data);
        return self::pdo()->lastInsertId();
    }

    /**
     * 更新
     * @param string $table 表名（不含前缀）
     * @param array $data 字段=>值
     * @param string $where 条件（已限定，安全）
     * @param array $whereParams 条件参数
     * @return int
     */
    public static function update($table, $data, $where, $whereParams = array())
    {
        $sets = array();
        foreach (array_keys($data) as $col) {
            $sets[] = '`' . $col . '`=:' . $col;
        }
        $sql = 'UPDATE ' . self::table($table) . ' SET ' . implode(',', $sets) . ' WHERE ' . $where;
        $params = array_merge($data, $whereParams);
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * 开启事务
     */
    public static function begin()
    {
        self::pdo()->beginTransaction();
    }

    public static function commit()
    {
        self::pdo()->commit();
    }

    public static function rollback()
    {
        if (self::pdo()->inTransaction()) {
            self::pdo()->rollBack();
        }
    }

    /**
     * 替换 SQL 中的 {table} 为带前缀的真实表名
     * @param string $sql SQL
     * @return string
     */
    private static function replaceTable($sql)
    {
        return preg_replace_callback('/\{(\w+)\}/', function ($m) {
            return self::table($m[1]);
        }, $sql);
    }
}
