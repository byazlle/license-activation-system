<?php
namespace App\Service;

use App\Core\Crypto;
use App\Core\Config;
use App\Repository\SoftwareRepo;

/**
 * 软件服务
 * 创建软件时自动生成该软件独立的第二层密钥对 B_i。
 */
class SoftwareService
{
    /**
     * 创建软件（含生成 B_i 密钥对）
     * @param array $data 软件数据
     * @return int 新软件 ID
     */
    public static function create($data)
    {
        // 生成该软件独立的 B_i 密钥对，私钥落盘 Web 根外
        $keys = Crypto::generateKeyPair();
        $keyDir = Config::root() . '/keys';
        if (!is_dir($keyDir)) {
            @mkdir($keyDir, 0700, true);
        }
        $keyId = 'sw_' . \App\Core\Util::random(10);
        $privatePath = $keyDir . '/' . $keyId . '_private_b.pem';
        file_put_contents($privatePath, $keys['private']);
        @chmod($privatePath, 0600);

        $data['b_key_id'] = $keyId;
        $data['public_b'] = $keys['public'];
        $data['private_b_path'] = $keyId . '_private_b.pem'; // 相对 keys/ 目录

        return SoftwareRepo::create($data);
    }

    /**
     * 轮换某软件的 B_i 密钥对
     * 注意：轮换后需重发该软件的所有 K 码（K 码内嵌旧公钥 B_i）。
     * @param int $softwareId 软件 ID
     * @return bool
     */
    public static function rotateKey($softwareId)
    {
        $software = SoftwareRepo::find($softwareId);
        if (!$software) {
            return false;
        }
        $keys = Crypto::generateKeyPair();
        $keyDir = Config::root() . '/keys';
        $keyId = 'sw_' . \App\Core\Util::random(10);
        $privatePath = $keyDir . '/' . $keyId . '_private_b.pem';
        file_put_contents($privatePath, $keys['private']);
        @chmod($privatePath, 0600);

        // 删除旧私钥文件
        if (!empty($software['private_b_path'])) {
            $old = $keyDir . '/' . basename($software['private_b_path']);
            if (is_file($old)) {
                @unlink($old);
            }
        }
        SoftwareRepo::updateBKey($softwareId, $keys['public'], $keyId . '_private_b.pem', $keyId);
        return true;
    }

    /**
     * 读取某软件的私钥 B_i 内容
     * @param array $software 软件记录
     * @return string PEM
     */
    public static function privateB($software)
    {
        $path = Config::root() . '/keys/' . basename($software['private_b_path']);
        return is_file($path) ? file_get_contents($path) : '';
    }

    /**
     * 读取全局私钥 A
     * @return string PEM
     */
    public static function privateA()
    {
        $path = Config::root() . '/keys/private_a.pem';
        return is_file($path) ? file_get_contents($path) : '';
    }

    /**
     * 读取全局公钥 A
     * @return string PEM
     */
    public static function publicA()
    {
        $path = Config::root() . '/keys/public_a.pem';
        return is_file($path) ? file_get_contents($path) : '';
    }
}
