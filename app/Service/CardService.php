<?php
namespace App\Service;

use App\Core\Crypto;
use App\Core\DB;
use App\Core\Util;
use App\Repository\CardRepo;
use App\Repository\SoftwareRepo;

/**
 * 卡密服务
 * 负责：生成卡密 + K 码、延期、改次数、启停、查询。
 */
class CardService
{
    /**
     * 批量生成卡密与 K 码
     * @param array $p 参数：software_id, mode, days/times, count, remark
     * @return array ['cards'=>卡号数组, 'kcodes'=>K码数组]
     */
    public static function generate($p)
    {
        $software = SoftwareRepo::find((int)$p['software_id']);
        if (!$software) {
            throw new \RuntimeException('软件不存在');
        }
        $mode = $p['mode'] === 'times' ? 'times' : 'time';
        $count = max(1, min(500, (int)$p['count']));
        $privateA = SoftwareService::privateA();
        if ($privateA === '') {
            throw new \RuntimeException('全局私钥 A 不存在，请检查安装');
        }
        $licenseUrl = self::licenseUrl();
        $batch = 'B' . date('YmdHis') . strtoupper(Util::random(3));

        $cards = array();
        $kcodes = array();
        DB::begin();
        try {
            for ($i = 0; $i < $count; $i++) {
                $cardId = Util::cardNo();
                // 避免极小概率重复
                while (CardRepo::findByCardId($cardId)) {
                    $cardId = Util::cardNo();
                }
                $expireAt = 0;
                $totalTimes = 0;
                $remain = 0;
                if ($mode === 'time') {
                    $days = max(1, (int)$p['days']);
                    $expireAt = time() + $days * 86400;
                } else {
                    $totalTimes = max(1, (int)$p['times']);
                    $remain = $totalTimes;
                }

                CardRepo::create(array(
                    'card_id' => $cardId,
                    'software_id' => $software['id'],
                    'mode' => $mode,
                    'expire_at' => $expireAt,
                    'total_times' => $totalTimes,
                    'remain' => $remain,
                    'used' => 0,
                    'status' => 1,
                    'remark' => isset($p['remark']) && $p['remark'] !== '' ? $p['remark'] : null,
                    'custom_config' => isset($p['custom_config']) && $p['custom_config'] !== '' ? $p['custom_config'] : null,
                    'batch_no' => $batch,
                    'created_at' => time(),
                    'updated_at' => time(),
                ));

                // 组装第一层 K 码载荷：card_id + 地址 + 该软件公钥 B_i + 校验串
                $payload = array(
                    'v' => 1,
                    'c' => $cardId,
                    'u' => $licenseUrl,
                    'b' => $software['public_b'], // 该软件公钥 B_i（PEM）
                    's' => $software['identifier'],
                    'x' => Util::random(16),
                );
                $kcodes[] = Crypto::sealJson($payload, $privateA);
                $cards[] = $cardId;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
        return array('cards' => $cards, 'kcodes' => $kcodes, 'batch' => $batch);
    }

    /**
     * 从 K 码解出载荷（后台预览/地址转换用）
     * @param string $kcode K 码
     * @return array|null
     */
    public static function parseKcode($kcode)
    {
        $publicA = SoftwareService::publicA();
        if ($publicA === '') {
            return null;
        }
        return Crypto::openJson(trim($kcode), $publicA);
    }

    /**
     * 地址转换：解 K 码 → 只替换地址 → 私钥 A 重新加密
     * @param string $kcode 旧 K 码
     * @param string $newUrl 新服务器地址
     * @return string 新 K 码
     */
    public static function convertKcode($kcode, $newUrl)
    {
        $payload = self::parseKcode($kcode);
        if (!$payload || !isset($payload['c'])) {
            throw new \RuntimeException('K 码格式错误或校验失败');
        }
        $privateA = SoftwareService::privateA();
        if ($privateA === '') {
            throw new \RuntimeException('全局私钥 A 不存在');
        }
        $payload['u'] = $newUrl; // 只改地址，保留 c / b / s
        return Crypto::sealJson($payload, $privateA);
    }

    /**
     * 读取当前授权服务器地址
     * @return string
     */
    public static function licenseUrl()
    {
        $url = DB::value('SELECT svalue FROM {settings} WHERE skey = ?', array('license_url'));
        return $url ? $url : '';
    }

    public static function setLicenseUrl($url)
    {
        return DB::execute(
            'UPDATE {settings} SET svalue = ? WHERE skey = ?',
            array($url, 'license_url')
        );
    }
}
