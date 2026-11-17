<?php
namespace App\Controller;

use App\Core\Auth;
use App\Core\Util;
use App\Core\Response;
use App\Core\View;
use App\Service\CardService;
use App\Repository\LogRepo;

/**
 * 地址转换页控制器
 * 换服务器 / 域名时，解旧 K 码 → 换地址 → 私钥 A 重签。
 */
class ConvertController
{
    public function index()
    {
        Auth::requireLogin();
        $result = null;
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Auth::checkCsrf(Util::param('csrf', ''))) {
                $error = '表单已过期';
            } else {
                $url = trim(Util::param('new_url', ''));
                $kcode = trim(Util::param('kcode', ''));
                if ($url === '' || $kcode === '') {
                    $error = '请填写新地址和旧 K 码';
                } else {
                    // 保存新地址到系统设置
                    CardService::setLicenseUrl($url);
                    try {
                        $newKcode = CardService::convertKcode($kcode, $url);
                        $parsed = CardService::parseKcode($kcode);
                        $result = $newKcode;
                        LogRepo::admin(Auth::id(), Auth::name(), 'convert',
                            isset($parsed['c']) ? $parsed['c'] : '',
                            array('new_url' => $url));
                    } catch (\Exception $e) {
                        $error = $e->getMessage();
                    }
                }
            }
        }
        View::page('convert/index', array(
            'currentUrl' => CardService::licenseUrl(),
            'result' => $result,
            'error' => $error,
            'csrf' => Auth::csrfToken(),
        ), '地址转换');
    }
}
