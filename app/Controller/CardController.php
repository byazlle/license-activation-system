<?php
namespace App\Controller;

use App\Core\Auth;
use App\Core\Util;
use App\Core\Response;
use App\Core\View;
use App\Repository\CardRepo;
use App\Repository\SoftwareRepo;
use App\Service\CardService;
use App\Service\LogService;
use App\Repository\LogRepo;

/**
 * 卡密管理控制器
 */
class CardController
{
    public function index()
    {
        Auth::requireLogin();
        $filter = array(
            'software_id' => Util::param('software_id', ''),
            'status' => Util::param('status', ''),
            'keyword' => Util::param('keyword', ''),
        );
        $page = (int)Util::param('page', 1);
        $perPage = 20;
        $list = CardRepo::paginate($filter, ($page - 1) * $perPage, $perPage);
        $total = CardRepo::count($filter);
        View::page('card/index', array(
            'list' => $list,
            'softwares' => SoftwareRepo::all(),
            'filter' => $filter,
            'page' => $page,
            'pages' => max(1, ceil($total / $perPage)),
            'total' => $total,
            'csrf' => Auth::csrfToken(),
        ), '卡密管理');
    }

    /**
     * 发卡表单
     */
    public function create()
    {
        Auth::requireLogin();
        View::page('card/form', array(
            'softwares' => SoftwareRepo::enabled(),
            'preselect' => (int)Util::param('software_id', 0),
            'csrf' => Auth::csrfToken(),
        ), '生成卡密');
    }

    /**
     * 执行发卡
     */
    public function generate()
    {
        Auth::requireLogin();
        if (!Auth::checkCsrf(Util::param('csrf', ''))) {
            Response::json(1001, '表单已过期');
        }
        try {
            $softwareId = (int)Util::param('software_id', 0);
            $result = CardService::generate(array(
                'software_id' => $softwareId,
                'mode' => Util::param('mode', 'time'),
                'days' => Util::param('days', 30),
                'times' => Util::param('times', 100),
                'count' => Util::param('count', 1),
                'remark' => Util::param('remark', ''),
                'custom_config' => $this->buildCustomConfig($softwareId),
            ));
            LogRepo::admin(Auth::id(), Auth::name(), 'card.generate', $result['batch'],
                array('count' => count($result['cards'])));
            Response::json(0, '发卡成功', $result);
        } catch (\Exception $e) {
            Response::json(1001, $e->getMessage());
        }
    }

    /**
     * 组装卡专属下发配置：AI 只覆盖已开放字段的值，额外配置按类型写入。
     * 返回空串表示沿用软件默认配置。
     * @param int $softwareId 软件 ID
     * @return string JSON
     */
    private function buildCustomConfig($softwareId)
    {
        $aiOn = (int)Util::param('ai_enabled', 0) === 1;
        $extraOn = (int)Util::param('extra_enabled', 0) === 1;
        if (!$aiOn && !$extraOn) {
            return '';
        }
        $software = SoftwareRepo::find($softwareId);
        if (!$software) {
            throw new \RuntimeException('软件不存在');
        }
        $base = json_decode($software['default_config'], true);
        if (!is_array($base)) {
            $base = array();
        }
        $cfg = array();

        if ($aiOn) {
            // 以软件默认 AI 配置为骨架，只允许改 endpoint / key / model 的值，
            // 结构与其他固定字段保持不变。
            $baseAi = isset($base['ai']) && is_array($base['ai']) ? $base['ai'] : array();
            $endpoint = trim(Util::param('ai_endpoint', ''));
            $key = trim(Util::param('ai_key', ''));
            $model = trim(Util::param('ai_model', ''));
            if ($endpoint !== '') {
                $baseAi['endpoint'] = $endpoint;
            }
            if ($key !== '') {
                $baseAi['key'] = $key;
            }
            if ($model !== '') {
                $baseAi['model'] = $model;
            }
            if (!$baseAi) {
                throw new \RuntimeException('请至少填写一项 AI 配置');
            }
            $cfg['ai'] = $baseAi;
        }

        if ($extraOn) {
            $type = Util::param('extra_type', 'text');
            $content = (string)Util::param('extra_content', '');
            if ($type === 'json') {
                $decoded = json_decode($content, true);
                if (!is_array($decoded)) {
                    throw new \RuntimeException('额外 JSON 内容格式不正确');
                }
                $cfg['json'] = $decoded;
            } elseif ($type === 'script') {
                if (trim($content) === '') {
                    throw new \RuntimeException('脚本 / 配置文件内容不能为空');
                }
                $cfg['script'] = $content;
            } else {
                if (trim($content) === '') {
                    throw new \RuntimeException('自定义文本不能为空');
                }
                $cfg['text'] = $content;
            }
        }

        $json = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('配置序列化失败');
        }
        return $json;
    }

    /**
     * 刷新软件关联信息：统计卡密与软件的关联情况（孤儿卡、停用软件下的卡等）
     */
    public function sync()
    {
        Auth::requireLogin();
        if (!Auth::checkCsrf(Util::param('csrf', ''))) {
            Response::json(1001, '表单已过期');
        }
        Response::json(0, '已刷新', CardRepo::linkStats());
    }

    /**
     * 延期
     */
    public function extend()
    {
        Auth::requireLogin();
        if (!Auth::checkCsrf(Util::param('csrf', ''))) {
            Response::json(1001, '表单已过期');
        }
        $id = (int)Util::param('id', 0);
        $days = (int)Util::param('days', 0);
        $card = CardRepo::find($id);
        if (!$card || $days <= 0) {
            Response::json(1001, '参数错误');
        }
        $base = max((int)$card['expire_at'], time());
        CardRepo::extend($id, $base + $days * 86400);
        LogRepo::admin(Auth::id(), Auth::name(), 'card.extend', $card['card_id'], array('days' => $days));
        Response::json(0, '已延期');
    }

    /**
     * 改次数
     */
    public function setTimes()
    {
        Auth::requireLogin();
        if (!Auth::checkCsrf(Util::param('csrf', ''))) {
            Response::json(1001, '表单已过期');
        }
        $id = (int)Util::param('id', 0);
        $remain = (int)Util::param('remain', 0);
        $card = CardRepo::find($id);
        if (!$card || $remain < 0) {
            Response::json(1001, '参数错误');
        }
        CardRepo::setTimes($id, $remain, max((int)$card['total_times'], $remain));
        LogRepo::admin(Auth::id(), Auth::name(), 'card.times', $card['card_id'], array('remain' => $remain));
        Response::json(0, '已更新');
    }

    public function status()
    {
        Auth::requireLogin();
        if (!Auth::checkCsrf(Util::param('csrf', ''))) {
            Response::json(1001, '表单已过期');
        }
        CardRepo::setStatus((int)Util::param('id', 0), (int)Util::param('status', 0) === 1 ? 1 : 0);
        Response::json(0, '已更新');
    }

    /**
     * 单卡详情 + 统计
     */
    public function detail()
    {
        Auth::requireLogin();
        $id = (int)Util::param('id', 0);
        $card = CardRepo::find($id);
        if (!$card) {
            Util::redirect('index.php?r=card/index');
        }
        View::page('card/detail', array(
            'card' => $card,
            'stats' => LogService::cardStats($card['card_id']),
            'csrf' => Auth::csrfToken(),
        ), '卡详情');
    }

    /**
     * 导出 K 码（批次）
     */
    public function export()
    {
        Auth::requireLogin();
        $batch = Util::param('batch', '');
        // K 码不入库原文，导出功能以"生成结果页复制"为主，这里导出卡号清单
        $list = CardRepo::paginate(array('keyword' => $batch), 0, 500);
        $lines = array("card_id,mode,expire_at,remain,status");
        foreach ($list as $card) {
            $lines[] = implode(',', array(
                $card['card_id'], $card['mode'], $card['expire_at'], $card['remain'], $card['status'],
            ));
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="cards.csv"');
        echo "\xEF\xBB\xBF" . implode("\n", $lines);
        exit;
    }
}
