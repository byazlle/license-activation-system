<?php
namespace App\Core;

/**
 * 加密引擎（系统核心）
 *
 * 采用「RSA 混合加密」实现用户要求的「私钥加密 / 公钥解密」：
 *   1. 随机生成 32 字节 AES-256 密钥；
 *   2. 用【私钥】加密这个 AES 密钥（RSA，openssl_private_encrypt）；
 *   3. 用 AES-256-GCM 加密实际数据（长度不限、带完整性校验）；
 *   4. 拼接并用 base64url 编码输出。
 * 解密方用【公钥】解出 AES 密钥，再解开数据。
 *
 * 之所以不直接用 RSA 加密数据：2048 位 RSA 在 PKCS#1 v1.5 填充下
 * 单次最多加密 245 字节，业务包含 API Key/JSON，常超过该长度。
 *
 * 二进制布局（拼接后整体 base64url）：
 *   [2字节长度][RSA加密的AES密钥][12字节IV][16字节GCM标签][AES密文]
 *
 * 兼容：PHP 7.2 openssl 扩展；aes-256-gcm 在少数老版本不可用时，
 * 自动降级为 aes-256-cbc + 前置 IV（详见 encryptRaw/decryptRaw）。
 */
class Crypto
{
    const AES_METHOD = 'aes-256-gcm';
    const IV_LEN = 12;
    const TAG_LEN = 16;
    const KEY_LEN = 32;

    /**
     * 生成 RSA 密钥对（2048 位）
     * @return array ['private'=>PEM字符串, 'public'=>PEM字符串]
     */
    public static function generateKeyPair()
    {
        $res = openssl_pkey_new(array(
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ));
        if ($res === false) {
            throw new \RuntimeException('无法生成 RSA 密钥对，请检查 openssl 扩展');
        }
        $private = '';
        openssl_pkey_export($res, $private);
        $details = openssl_pkey_get_details($res);
        $public = $details['key'];
        return array('private' => $private, 'public' => $public);
    }

    /**
     * 【私钥加密】把数据加密为可对外分发的密文串（K 码 / 业务包）
     * @param string $data 明文（JSON 字符串等）
     * @param string $privateKeyPem 私钥 PEM
     * @return string base64url 密文
     */
    public static function seal($data, $privateKeyPem)
    {
        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if ($privateKey === false) {
            throw new \RuntimeException('私钥格式错误');
        }

        // 1. 随机 AES 密钥与 IV
        $aesKey = random_bytes(self::KEY_LEN);
        $iv = random_bytes(self::IV_LEN);

        // 2. AES-GCM 加密数据（不可用时降级 CBC）
        $tag = '';
        if (in_array(self::AES_METHOD, openssl_get_cipher_methods(), true)) {
            $cipher = openssl_encrypt($data, self::AES_METHOD, $aesKey, OPENSSL_RAW_DATA, $iv, $tag);
            $algo = 1; // GCM
        } else {
            // 降级：CBC，IV 取 16 字节
            $iv = random_bytes(16);
            $cipher = openssl_encrypt($data, 'aes-256-cbc', $aesKey, OPENSSL_RAW_DATA, $iv);
            $tag = '';
            $algo = 2; // CBC
        }
        if ($cipher === false) {
            throw new \RuntimeException('AES 加密失败');
        }

        // 3. 用私钥加密 AES 密钥
        $encKey = '';
        if (!openssl_private_encrypt($aesKey, $encKey, $privateKey, OPENSSL_PKCS1_PADDING)) {
            throw new \RuntimeException('RSA 私钥加密失败：' . openssl_error_string());
        }

        // 4. 拼装二进制：算法标识(1) + 密钥长度(2) + 密钥 + IV长度(1) + IV + 标签长度(1) + 标签 + 密文
        $bin = chr($algo)
            . pack('n', strlen($encKey)) . $encKey
            . chr(strlen($iv)) . $iv
            . chr(strlen($tag)) . $tag
            . $cipher;

        return self::base64urlEncode($bin);
    }

    /**
     * 【公钥解密】解开 seal() 生成的密文
     * @param string $payload base64url 密文
     * @param string $publicKeyPem 公钥 PEM
     * @return string 明文，失败返回空串
     */
    public static function open($payload, $publicKeyPem)
    {
        $bin = self::base64urlDecode($payload);
        if ($bin === '' || strlen($bin) < 7) {
            return '';
        }
        $pos = 0;
        $algo = ord($bin[$pos]); $pos += 1;

        $keyLen = unpack('n', substr($bin, $pos, 2))[1]; $pos += 2;
        $encKey = substr($bin, $pos, $keyLen); $pos += $keyLen;

        $ivLen = ord($bin[$pos]); $pos += 1;
        $iv = substr($bin, $pos, $ivLen); $pos += $ivLen;

        $tagLen = ord($bin[$pos]); $pos += 1;
        $tag = substr($bin, $pos, $tagLen); $pos += $tagLen;

        $cipher = substr($bin, $pos);

        $publicKey = openssl_pkey_get_public($publicKeyPem);
        if ($publicKey === false) {
            return '';
        }
        $aesKey = '';
        if (!openssl_public_decrypt($encKey, $aesKey, $publicKey, OPENSSL_PKCS1_PADDING)) {
            return '';
        }

        if ($algo === 1) {
            $plain = openssl_decrypt($cipher, self::AES_METHOD, $aesKey, OPENSSL_RAW_DATA, $iv, $tag);
        } elseif ($algo === 2) {
            $plain = openssl_decrypt($cipher, 'aes-256-cbc', $aesKey, OPENSSL_RAW_DATA, $iv);
        } else {
            return '';
        }
        return $plain === false ? '' : $plain;
    }

    /**
     * 便捷方法：加密并 JSON 序列化数组
     * @param array $data 数据
     * @param string $privateKeyPem 私钥 PEM
     * @return string
     */
    public static function sealJson($data, $privateKeyPem)
    {
        return self::seal(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $privateKeyPem);
    }

    /**
     * 便捷方法：解密 JSON 字符串为数组
     * @param string $payload 密文
     * @param string $publicKeyPem 公钥 PEM
     * @return array|null
     */
    public static function openJson($payload, $publicKeyPem)
    {
        $json = self::open($payload, $publicKeyPem);
        if ($json === '') {
            return null;
        }
        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    /**
     * base64url 编码（URL 安全，无填充）
     */
    public static function base64urlEncode($bin)
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    /**
     * base64url 解码
     */
    public static function base64urlDecode($str)
    {
        $str = strtr($str, '-_', '+/');
        $pad = strlen($str) % 4;
        if ($pad) {
            $str .= str_repeat('=', 4 - $pad);
        }
        $bin = base64_decode($str, true);
        return $bin === false ? '' : $bin;
    }
}
