<?php
namespace App\Service;

use App\Core\DB;
use App\Core\Crypto;

/**
 * 安装服务
 * 负责：测试连接、建表、生成全局密钥 A、写配置、创建管理员、锁定安装。
 */
class InstallService
{
    /**
     * 测试数据库连接
     * @param array $cfg db 配置
     * @return array ['ok'=>bool, 'msg'=>string]
     */
    public static function testDb($cfg)
    {
        try {
            $pdo = DB::connect($cfg);
            $pdo->query('SELECT 1');
            // 确认库存在
            $pdo->query('USE `' . str_replace('`', '', $cfg['name']) . '`');
            return array('ok' => true, 'msg' => '连接成功');
        } catch (\Exception $e) {
            return array('ok' => false, 'msg' => $e->getMessage());
        }
    }

    /**
     * 执行安装
     * @param array $cfg 数据库配置
     * @param string $adminUser 管理员用户名
     * @param string $adminPass 管理员密码
     * @return array ['ok'=>bool,'msg'=>string]
     */
    public static function install($cfg, $adminUser, $adminPass)
    {
        try {
            // 1. 连接并建立表结构
            $pdo = DB::connect($cfg);
            $schema = file_get_contents(\App\Core\Config::root() . '/install/schema.sql');
            $prefix = isset($cfg['prefix']) ? $cfg['prefix'] : 'lc_';
            $schema = str_replace('{{PREFIX}}', $prefix, $schema);
            $statements = self::splitSql($schema);
            foreach ($statements as $sql) {
                $sql = trim($sql);
                if ($sql !== '') {
                    $pdo->exec($sql);
                }
            }

            // 2. 生成全局密钥对 A，写入 keys/ 目录（Web 根之外）
            $keys = Crypto::generateKeyPair();
            $keyDir = \App\Core\Config::root() . '/keys';
            if (!is_dir($keyDir)) {
                @mkdir($keyDir, 0700, true);
            }
            file_put_contents($keyDir . '/private_a.pem', $keys['private']);
            file_put_contents($keyDir . '/public_a.pem', $keys['public']);
            @chmod($keyDir . '/private_a.pem', 0600);

            // 3. 写配置文件 config/config.php
            $configContent = self::buildConfig($cfg);
            file_put_contents(\App\Core\Config::root() . '/config/config.php', $configContent);

            // 4. 重新加载配置，创建管理员
            \App\Core\Config::load(\App\Core\Config::root() . '/config/config.php');
            \App\Core\DB::pdo(); // 重置为配置连接（新 PDO）
            \App\Repository\AdminRepo::create($adminUser, password_hash($adminPass, PASSWORD_BCRYPT), 1);

            // 5. 初始化系统设置
            \App\Core\DB::execute(
                'INSERT INTO {settings} (skey, svalue, remark) VALUES (?,?,?)',
                array('license_url', '', '授权服务器地址（K码内写入）')
            );
            \App\Core\DB::execute(
                'INSERT INTO {settings} (skey, svalue, remark) VALUES (?,?,?)',
                array('installed_at', (string)time(), '安装时间')
            );

            // 6. 写安装锁
            file_put_contents(\App\Core\Config::root() . '/install/install.lock', date('Y-m-d H:i:s'));

            return array('ok' => true, 'msg' => '安装完成');
        } catch (\Exception $e) {
            return array('ok' => false, 'msg' => $e->getMessage());
        }
    }

    /**
     * 按分号拆分 SQL（简单可靠，schema 中无存储过程）
     * @param string $sql 完整 SQL
     * @return array
     */
    private static function splitSql($sql)
    {
        // 去掉整行注释
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        return explode(';', $sql);
    }

    /**
     * 生成 config.php 内容
     * @param array $cfg 数据库配置
     * @return string
     */
    private static function buildConfig($cfg)
    {
        $export = function ($v) {
            return var_export($v, true);
        };
        $host = $export($cfg['host']);
        $port = $export(isset($cfg['port']) ? $cfg['port'] : '3306');
        $name = $export($cfg['name']);
        $user = $export($cfg['user']);
        $pass = $export($cfg['pass']);
        $prefix = $export($cfg['prefix']);
        return <<<PHP
<?php
/**
 * 系统配置（安装程序自动生成）
 * 私钥文件路径见 keys/ 目录，请勿把私钥写入 Web 可访问位置。
 */
return array(
    'db' => array(
        'host'   => {$host},
        'port'   => {$port},
        'name'   => {$name},
        'user'   => {$user},
        'pass'   => {$pass},
        'charset'=> 'utf8mb4',
        'prefix' => {$prefix},
    ),
    'app' => array(
        'name' => '软件激活与授权管理系统',
        'ticket_ttl' => 1800,
        'request_window' => 300,
    ),
);
PHP;
    }
}
