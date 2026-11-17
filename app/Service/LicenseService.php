<?php
namespace App\Service;

use App\Core\Crypto;
use App\Core\DB;
use App\Core\Util;
use App\Repository\CardRepo;
use App\Repository\SoftwareRepo;
use App\Repository\TicketRepo;
use App\Repository\LogRepo;

/**
 * 授权服务（客户端接口核心）
 * 职责：参数校验、防重放、时效/次数校验、原子扣次、组装并加密业务包。
 */
class LicenseService
{
    /** 错误码：见 D2 §7.1 */
    const E_PARAM = 1001;
    const E_REPLAY = 1002;
    const E_NO_CARD = 2001;
    const E_DISABLED = 2002;
    const E_MODE = 2003;
    const E_EXPIRED = 2004;
    const E_NO_TIMES = 2005;
    const E_NO_SOFTWARE = 3002;

    /**
     * 校验公共请求参数 + 防重放
     * @param array $p 请求参数
     * @return array ['ok'=>bool,'code'=>int,'software'=>array|null,'nonceHash'=>string]
     */
    public static function validate($p)
    {
        $cardId = isset($p['card_id']) ? $p['card_id'] : '';
        $identifier = isset($p['software_id']) ? $p['software_id'] : '';
        $deviceId = isset($p['device_id']) ? $p['device_id'] : '';
        $nonce = isset($p['nonce']) ? $p['nonce'] : '';
        $timestamp = isset($p['timestamp']) ? (int)$p['timestamp'] : 0;

        if ($cardId === '' || $identifier === '' || $deviceId === '' || $nonce === '') {
            return array('ok' => false, 'code' => self::E_PARAM);
        }
        $window = (int)\App\Core\Config::get('app.request_window', 300);
        if (abs(time() - $timestamp) > $window) {
            return array('ok' => false, 'code' => self::E_REPLAY);
        }
        $nonceHash = hash('sha256', $cardId . '|' . $nonce);
        if (LogRepo::nonceUsed($nonceHash)) {
            return array('ok' => false, 'code' => self::E_REPLAY);
        }
        $software = SoftwareRepo::findByIdentifier($identifier);
        if (!$software) {
            return array('ok' => false, 'code' => self::E_NO_SOFTWARE);
        }
        return array('ok' => true, 'code' => 0, 'software' => $software, 'nonceHash' => $nonceHash,
            'card_id' => $cardId, 'device_id' => $deviceId);
    }

    /**
     * 激活 / 时效校验（不扣次）
     * @param array $p 请求参数
     * @return array ['code'=>int,'data'=>array]
     */
    public static function activate($p)
    {
        $v = self::validate($p);
        if (!$v['ok']) {
            self::logFail($p, 'activate', $v['code']);
            return self::fail($v['code']);
        }
        $software = $v['software'];
        $card = CardRepo::findForSoftware($v['card_id'], $software['id']);
        if (!$card) {
            self::logFail($p, 'activate', self::E_NO_CARD, $software);
            return self::fail(self::E_NO_CARD);
        }
        if ((int)$card['status'] !== 1) {
            self::logFail($p, 'activate', self::E_DISABLED, $software, $card);
            return self::fail(self::E_DISABLED);
        }
        if ($card['mode'] !== 'time') {
            self::logFail($p, 'activate', self::E_MODE, $software, $card);
            return self::fail(self::E_MODE);
        }
        if ($card['expire_at'] > 0 && time() > (int)$card['expire_at']) {
            self::logFail($p, 'activate', self::E_EXPIRED, $software, $card);
            return self::fail(self::E_EXPIRED);
        }

        // 记录首次激活设备
        if ((int)$card['activated_at'] === 0) {
            CardRepo::setActivated($card['id'], $v['device_id']);
        }

        $package = self::buildPackage($card, $software, $v['nonceHash']);
        self::logOk($p, 'activate', $software, $card, $v['nonceHash']);
        return array('code' => 0, 'data' => array('package' => $package));
    }

    /**
     * 次数型消费：原子扣 1 + 签发票据 + 下发业务包
     * @param array $p 请求参数
     * @return array ['code'=>int,'data'=>array]
     */
    public static function ticket($p)
    {
        $v = self::validate($p);
        if (!$v['ok']) {
            self::logFail($p, 'ticket', $v['code']);
            return self::fail($v['code']);
        }
        $software = $v['software'];
        $card = CardRepo::findForSoftware($v['card_id'], $software['id']);
        if (!$card) {
            self::logFail($p, 'ticket', self::E_NO_CARD, $software);
            return self::fail(self::E_NO_CARD);
        }
        if ((int)$card['status'] !== 1) {
            self::logFail($p, 'ticket', self::E_DISABLED, $software, $card);
            return self::fail(self::E_DISABLED);
        }
        if ($card['mode'] !== 'times') {
            self::logFail($p, 'ticket', self::E_MODE, $software, $card);
            return self::fail(self::E_MODE);
        }

        $remainBefore = (int)$card['remain'];
        $privateB = SoftwareService::privateB($software);
        if ($privateB === '') {
            return self::fail(self::E_PARAM);
        }

        DB::begin();
        try {
            // 原子扣次（remain>0 才更新），并发安全
            $affected = CardRepo::deductAtomic($v['card_id'], $software['id']);
            if ($affected !== 1) {
                DB::rollback();
                self::logFail($p, 'ticket', self::E_NO_TIMES, $software, $card, $remainBefore, 0);
                return self::fail(self::E_NO_TIMES);
            }
            $remainAfter = $remainBefore - 1;

            // 签发票据
            $ticket = \App\Core\Util::random(48);
            $ttl = (int)\App\Core\Config::get('app.ticket_ttl', 60);
            TicketRepo::create($ticket, $v['card_id'], $v['device_id'], time() + $ttl);

            // 组装业务包（用最新剩余次数）
            $card['remain'] = $remainAfter;
            $card['used'] = (int)$card['used'] + 1;
            $package = self::buildPackage($card, $software, $v['nonceHash']);

            // 记录成功日志（含扣次前后）
            LogRepo::request(array(
                'request_id' => \App\Core\Response::requestId(),
                'card_id' => $v['card_id'],
                'software_id' => $software['id'],
                'software_identifier' => $software['identifier'],
                'device_id' => $v['device_id'],
                'action' => 'ticket',
                'result' => 1,
                'remain_before' => $remainBefore,
                'remain_after' => $remainAfter,
                'nonce' => $v['nonceHash'],
            ));
            DB::commit();

            return array('code' => 0, 'data' => array(
                'ticket' => $ticket,
                'expire_at' => time() + $ttl,
                'package' => $package,
            ));
        } catch (\Exception $e) {
            DB::rollback();
            \App\Core\Log::error('TICKET_EXCEPTION', array('message' => $e->getMessage()));
            return self::fail(9001);
        }
    }

    /**
     * 组装并加密业务包
     * 用该软件的私钥 B_i 加密，软件用 K 码中的公钥 B_i 解密。
     * @param array $card 卡记录
     * @param array $software 软件记录
     * @param string $nonceHash 本次请求 nonce 哈希
     * @return string base64url 密文
     */
    private static function buildPackage($card, $software, $nonceHash)
    {
        // 配置：卡专属优先，否则用软件默认
        $configRaw = !empty($card['custom_config']) ? $card['custom_config'] : $software['default_config'];
        $config = array();
        if ($configRaw) {
            $decoded = json_decode($configRaw, true);
            $config = is_array($decoded) ? $decoded : array();
        }
        $payload = array(
            'v' => 1,
            'c' => $card['card_id'],
            'auth' => array(
                'type' => $card['mode'],
                'remain' => (int)$card['remain'],
                'total' => (int)$card['total_times'],
                'expire_at' => (int)$card['expire_at'],
            ),
            'config' => $config,
            'ts' => time(),
            'n' => $nonceHash,
        );
        $privateB = SoftwareService::privateB($software);
        return Crypto::sealJson($payload, $privateB);
    }

    /** 成功日志（activate） */
    private static function logOk($p, $action, $software, $card, $nonceHash)
    {
        LogRepo::request(array(
            'card_id' => $card['card_id'],
            'software_id' => $software['id'],
            'software_identifier' => $software['identifier'],
            'device_id' => isset($p['device_id']) ? $p['device_id'] : '',
            'action' => $action,
            'result' => 1,
            'remain_before' => (int)$card['remain'],
            'remain_after' => (int)$card['remain'],
            'nonce' => $nonceHash,
        ));
    }

    /** 失败日志 */
    private static function logFail($p, $action, $code, $software = null, $card = null, $before = 0, $after = 0)
    {
        LogRepo::request(array(
            'card_id' => isset($p['card_id']) ? $p['card_id'] : '',
            'software_id' => $software ? $software['id'] : 0,
            'software_identifier' => $software ? $software['identifier'] : '',
            'device_id' => isset($p['device_id']) ? $p['device_id'] : '',
            'action' => $action,
            'result' => 0,
            'error_code' => $code,
            'remain_before' => $before ? $before : ($card ? (int)$card['remain'] : 0),
            'remain_after' => $after,
        ));
    }

    /** 失败响应（含友好文案） */
    private static function fail($code)
    {
        $msgs = array(
            self::E_PARAM => '请求参数不合法',
            self::E_REPLAY => '请求已过期，请重试',
            self::E_NO_CARD => '激活码无效',
            self::E_DISABLED => '激活码已被停用',
            self::E_MODE => '授权类型不符',
            self::E_EXPIRED => '激活码已过期',
            self::E_NO_TIMES => '可用次数已用完',
            self::E_NO_SOFTWARE => '软件未授权',
            9001 => '服务暂时不可用',
        );
        return array('code' => $code, 'msg' => isset($msgs[$code]) ? $msgs[$code] : '请求失败', 'data' => array());
    }
}
