<?php
/**
 * 命令行静默安装器（供一键部署脚本调用）
 * 用法：
 *   php install/cli_install.php \
 *     --host=127.0.0.1 --port=3306 --db=license --user=license \
 *     --pass='DbPass123' --prefix=lc_ --admin=admin --admin-pass='AdminPass123'
 *
 * 输出 JSON：{"ok":true,"msg":"安装完成","api_key_file":"/.../initial_api_key.txt"}
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('仅允许命令行执行');
}

define('ROOT', dirname(__DIR__));
require ROOT . '/app/bootstrap.php';

$o = getopt('', array(
    'host:', 'port::', 'db:', 'user:', 'pass::', 'prefix::', 'admin:', 'admin-pass:',
));

$required = array('host', 'db', 'user', 'admin', 'admin-pass');
foreach ($required as $k) {
    if (!isset($o[$k]) || $o[$k] === '') {
        fwrite(STDERR, '缺少参数 --' . $k . PHP_EOL);
        exit(1);
    }
}

if (is_file(ROOT . '/install/install.lock')) {
    echo json_encode(array('ok' => false, 'msg' => '系统已安装；如需重装请先删除 install/install.lock'), JSON_UNESCAPED_UNICODE);
    exit(1);
}

$cfg = array(
    'host' => $o['host'],
    'port' => isset($o['port']) && $o['port'] !== '' ? $o['port'] : '3306',
    'name' => $o['db'],
    'user' => $o['user'],
    'pass' => isset($o['pass']) ? $o['pass'] : '',
    'prefix' => isset($o['prefix']) && $o['prefix'] !== '' ? $o['prefix'] : 'lc_',
);

$test = \App\Service\InstallService::testDb($cfg);
if (!$test['ok']) {
    echo json_encode(array('ok' => false, 'msg' => '数据库连接失败：' . $test['msg']), JSON_UNESCAPED_UNICODE);
    exit(1);
}

$result = \App\Service\InstallService::install($cfg, $o['admin'], $o['admin-pass']);
$result['api_key_file'] = ROOT . '/install/initial_api_key.txt';
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($result['ok'] ? 0 : 1);
