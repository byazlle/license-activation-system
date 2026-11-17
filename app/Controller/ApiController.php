<?php
namespace App\Controller;

use App\Core\Response;
use App\Core\Util;
use App\Service\LicenseService;

/**
 * 客户端 API 控制器
 * 对接端点：
 *   index.php?r=api/activate  时效型激活 / 时效校验（不扣次）
 *   index.php?r=api/status    只读查询授权与配置（不扣次，两种模式通用）
 *   index.php?r=api/ticket    次数型消费（原子扣 1）
 *   index.php?r=api/refund    次数退回（AI 失败/取消时用票据退 1 次）
 *   index.php?r=api/ping      连通性测试
 */
class ApiController
{
    /**
     * 收集公共请求参数（兼容 JSON body 与表单）
     * @return array
     */
    private function params()
    {
        $body = file_get_contents('php://input');
        if ($body !== '' && strpos($body, '{') === 0) {
            $json = json_decode($body, true);
            if (is_array($json)) {
                return $json;
            }
        }
        return array(
            'card_id' => Util::param('card_id', ''),
            'software_id' => Util::param('software_id', ''),
            'device_id' => Util::param('device_id', ''),
            'nonce' => Util::param('nonce', ''),
            'timestamp' => Util::param('timestamp', 0),
            'ticket' => Util::param('ticket', ''),
        );
    }

    public function activate()
    {
        $result = LicenseService::activate($this->params());
        Response::json($result['code'], isset($result['msg']) ? $result['msg'] : 'ok', $result['data']);
    }

    public function status()
    {
        $result = LicenseService::status($this->params());
        Response::json($result['code'], isset($result['msg']) ? $result['msg'] : 'ok', $result['data']);
    }

    public function ticket()
    {
        $result = LicenseService::ticket($this->params());
        Response::json($result['code'], isset($result['msg']) ? $result['msg'] : 'ok', $result['data']);
    }

    public function refund()
    {
        $result = LicenseService::refund($this->params());
        Response::json($result['code'], isset($result['msg']) ? $result['msg'] : 'ok', $result['data']);
    }

    public function ping()
    {
        Response::success(array('time' => time(), 'service' => 'license-api'));
    }
}
