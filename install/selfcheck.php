<?php
/**
 * 环境自检脚本（浏览器或 CLI 均可运行）
 * 用途：在未正式安装前，检查 PHP 版本与扩展是否满足要求，并验证加密往返。
 * 用法：php install/selfcheck.php  或 浏览器访问 install/selfcheck.php
 */
$isCli = (PHP_SAPI === 'cli');
function out($s) { echo $s . ($GLOBALS['isCli'] ? "\n" : "<br>"); }

if (!$isCli) header('Content-Type: text/html; charset=utf-8');
out('=== 软件激活系统 环境自检 ===');
out('PHP 版本: ' . PHP_VERSION . '  ' . (version_compare(PHP_VERSION, '7.2', '>=') ? '[OK]' : '[过低，需 >=7.2]'));

$required = array('openssl', 'pdo_mysql', 'json', 'session', 'mbstring');
foreach ($required as $ext) {
    out('扩展 ' . $ext . ': ' . (extension_loaded($ext) ? '[OK]' : '[缺失]'));
}

out('--- 加密往返测试 ---');
if (extension_loaded('openssl')) {
    require_once dirname(__DIR__) . '/app/Core/Crypto.php';

    try {
        $pair = \App\Core\Crypto::generateKeyPair();
        out('RSA 2048 密钥对生成: [OK]');

        $payload = array(
            'v' => 1,
            'c' => 'CARD-TEST-0001',
            'u' => 'http://example.com/api',
            'b' => substr($pair['public'], 0, 40) . '...',
        );
        $sealed = \App\Core\Crypto::sealJson($payload, $pair['private']);
        out('私钥加密（seal）: [OK] 密文长度 ' . strlen($sealed));

        $opened = \App\Core\Crypto::openJson($sealed, $pair['public']);
        out('公钥解密（open）: ' . ($opened && $opened['c'] === 'CARD-TEST-0001' ? '[OK]' : '[失败]'));

        // GCM 支持情况
        out('aes-256-gcm 支持: ' . (in_array('aes-256-gcm', openssl_get_cipher_methods(), true) ? '[是]' : '[否，将降级 CBC]'));

        // 篡改检测
        $tampered = substr($sealed, 0, -4) . 'AAAA';
        $bad = \App\Core\Crypto::openJson($tampered, $pair['public']);
        out('篡改密文解密: ' . ($bad === null ? '[正确拒绝]' : '[异常：未拒绝]'));
    } catch (\Exception $e) {
        out('加密测试异常: ' . $e->getMessage());
    }
} else {
    out('openssl 未加载，跳过加密测试');
}

out('--- MySQL 连接测试（可选）---');
out('安装向导内提供数据库连接测试，本脚本仅检查环境。');
out('=== 自检结束 ===');
