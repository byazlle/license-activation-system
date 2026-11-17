# D5 开放 API 与 AI 集成文档

版本：v1.0　适用系统版本：v1.3+　更新日期：2026-11-17

## 1. 它解决什么问题

客户端授权接口 `/api/*` 面向已发卡的软件，**不提供管理功能**。开放 API `/openapi/*` 面向服务器持有者与 AI 助手，用一把 API Key 暴露后台的全部能力：

- 软件：新增、查询、修改、启停、删除、下载公钥、轮换密钥、拉取模型列表
- 卡密：生成、查询、改备注、改下发配置、延期、改次数、启停、删除、K 码解析/地址转换、关联体检
- 统计与日志：概览、卡统计、请求日志、操作日志、API 调用日志、日志清理
- 系统：系统信息、授权服务器地址读写、管理员账号增删与密码重置
- 授权联调：`status` / `activate` / `ticket` / `refund`（与 `/api/*` 同一套 Service）

后台页面与开放 API 共用同一套 Service/Repository，行为一致。

## 2. 调用约定

### 2.1 地址与请求方法

```
POST http://<服务器>/openapi/<资源>/<操作>
```

同时兼容：

```
POST http://<服务器>/index.php?r=openapi/<资源>/<操作>
```

请求体优先读取 JSON（`Content-Type: application/json`），也兼容表单参数。响应统一为：

```json
{"code":0,"msg":"ok","request_id":"9f2c...","data":{}}
```

`code=0` 成功；非 0 见 §6。`data` 永远是对象或数组，不会是 `null`。

预检请求 `OPTIONS /openapi/...` 直接返回 204，并带 CORS 头，允许 `Authorization`、`X-API-Key`。

### 2.2 鉴权

三选一，优先级从高到低：

```http
Authorization: Bearer lsk_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
X-API-Key: lsk_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
?api_key=lsk_xxx
```

- 密钥格式：`lsk_` + 40 位字母数字，由服务端生成。
- 数据库只保存 SHA256 哈希与前 12 位前缀；**明文仅在创建时返回一次**。
- 安装时自动生成一把全权限密钥，明文写在 `install/initial_api_key.txt`（Web 根之外）。
- 也可在后台「API 密钥」页创建，或通过 `keys/create` 创建。

### 2.3 权限范围（scope）

| scope | 允许的操作 |
|---|---|
| `*` | 全部 |
| `software:read` | software/list、get、public-key、public-a、models |
| `software:write` | software/create、update、status、delete、rotate-key |
| `card:read` | card/list、get、link-stats、parse-kcode |
| `card:write` | card/generate、update、extend、set-times、status、delete、convert-kcode |
| `key:read` | keys/list、self、scopes |
| `key:write` | keys/create、revoke |
| `log:read` | stats/*、log/requests、log/admin、log/calls |
| `log:write` | log/clean |
| `admin:read` | admin/list |
| `admin:write` | admin/create、delete、status、reset-password |
| `system:read` | system/info、license-url-get |
| `system:write` | license-url-set |
| `license:call` | license/status、activate、ticket、refund |

支持前缀通配，如 `software:*`；`ping` 只需要密钥有效，不校验 scope。权限不足返回 `4005`。

## 3. 接口清单

### 3.1 连通性与密钥

| 路径 | 说明 | 主要参数 |
|---|---|---|
| `ping` | 连通性与当前密钥信息 | 无 |
| `keys/list` | 密钥列表（不含哈希） | 无 |
| `keys/self` | 当前密钥信息 | 无 |
| `keys/scopes` | 可选权限列表 | 无 |
| `keys/create` | 创建密钥，返回明文 | `name`, `scopes[]`, `days`(0=永久) |
| `keys/revoke` | 撤销密钥 | `id` |

示例：

```bash
curl -s -X POST http://43.128.228.157/openapi/keys/create \
  -H 'Authorization: Bearer lsk_主密钥' \
  -H 'Content-Type: application/json' \
  -d '{"name":"AI助手","scopes":["software:read","card:read"],"days":30}'
```

### 3.2 软件管理

| 路径 | 说明 | 主要参数 |
|---|---|---|
| `software/list` | 软件列表 | `keyword`, `status`, `page`, `per_page` |
| `software/get` | 软件详情 | `id` 或 `identifier` |
| `software/create` | 新建软件（自动生成独立密钥对 B） | `name`, `identifier`, `mode`, `days`, `times`, `config` |
| `software/update` | 修改软件 | `id`, `name`, `mode`, `days`, `times`, `config` |
| `software/status` | 启用/停用 | `id`, `status` |
| `software/delete` | 删除软件（有卡密时拒绝） | `id` |
| `software/public-key` | 下载该软件公钥 B（PEM） | `id` 或 `identifier` |
| `software/public-a` | 下载全局公钥 A（PEM） | 无 |
| `software/rotate-key` | 轮换该软件密钥对 B | `id` |
| `software/models` | 根据接口地址+Key 拉取模型列表 | `endpoint`, `key` |

`config` 结构（只允许以下键，其他键被静默忽略）：

```json
{
  "ai": {"endpoint": "https://api.xxx.com/v1/chat/completions", "key": "sk-xxx", "model": "gpt-xxx"},
  "text": "自定义文本",
  "json": {"temperature": 0.7},
  "script": "可执行脚本/配置内容"
}
```

> `identifier` 创建后不可通过 API 修改（K 码绑定了标识）。

### 3.3 卡密管理

| 路径 | 说明 | 主要参数 |
|---|---|---|
| `card/list` | 卡密列表（含所属软件） | `software_id`, `status`, `keyword`, `page`, `per_page` |
| `card/get` | 单卡详情 + 统计 | `id` 或 `card_id` |
| `card/generate` | 批量发卡，返回卡号与 K 码 | 见下 |
| `card/update` | 改备注 / 改下发配置 | `card_id`, `remark`, `ai`, `extra` |
| `card/extend` | 时效卡延期 | `card_id`, `days` |
| `card/set-times` | 计次卡改剩余次数 | `card_id`, `remain` |
| `card/status` | 启用/停用 | `card_id`, `status` |
| `card/delete` | 删除卡密 | `card_id` |
| `card/link-stats` | 软件关联体检统计 | 无 |
| `card/parse-kcode` | 解 K 码载荷 | `kcode` |
| `card/convert-kcode` | 换服务器地址并重签 K 码 | `kcode`, `new_url` |

`card/generate` 参数：

```json
{
  "software_id": 6,
  "mode": "times",
  "times": 10,
  "count": 1,
  "remark": "给客户A",
  "ai": {
    "enabled": true,
    "endpoint": "https://api.override.com/v1/chat/completions",
    "key": "sk-override-999",
    "model": "model-x"
  },
  "extra": {"enabled": true, "type": "json", "content": "{\"temperature\":0.2}"}
}
```

- `mode`：`time`（时效，用 `days`）或 `times`（计次，用 `times`）。
- `ai` 只覆盖 `endpoint`/`key`/`model` **三个字段的值**；配置结构与固定字段继承软件默认配置，AI 无法改结构。
- `extra.type`：`text` / `json` / `script`；`json` 的 `content` 必须是合法 JSON 字符串。
- 返回的 `kcodes` 是 base64url 文本，每个卡对应一个。

### 3.4 统计、日志与系统

| 路径 | 说明 |
|---|---|
| `stats/overview` | 今日请求数、成功/失败、扣次总数、卡概览、最近操作日志 |
| `stats/card` | 卡总数、启用数、时效/计次、过期、耗尽 |
| `stats/link` | 卡与软件关联统计（同 card/link-stats） |
| `log/requests` | 请求日志分页，支持 `card_id`,`software_id`,`result`,`start`,`end`,`page`,`per_page` |
| `log/admin` | 后台/API 操作日志，`limit` |
| `log/calls` | 开放 API 调用日志（含返回码与耗时），`limit` |
| `log/clean` | 清理旧日志，`days` |
| `system/info` | 系统信息、PHP 版本、软件数、卡数、授权地址 |
| `system/license-url-get` | 读取 K 码内写入的授权服务器地址 |
| `system/license-url-set` | 修改地址（`url`）；已有 K 码需再调用 `card/convert-kcode` |

### 3.6 管理员账号

| 路径 | 说明 | 主要参数 |
|---|---|---|
| `admin/list` | 管理员列表（不含密码哈希） | 无 |
| `admin/create` | 新建管理员 | `username`, `password`(≥6位), `role`(1超管/2普管) |
| `admin/delete` | 删除管理员（ID=1 禁止删除） | `id` |
| `admin/status` | 启用/停用（ID=1 禁止停用） | `id`, `status` |
| `admin/reset-password` | 重置密码 | `id`, `password` |

### 3.5 授权接口联调

| 路径 | 说明 | 必需参数 |
|---|---|---|
| `license/status` | 只读查询，两种模式通用，不扣次 | `card_id`,`software_id`(填标识),`device_id`,`nonce`,`timestamp` |
| `license/activate` | 时效卡激活，不扣次 | 同上 |
| `license/ticket` | 计次卡扣 1 次并签发票据 | 同上 |
| `license/refund` | 用票据退回 1 次 | 同上 + `ticket` |

注意：这里的 `software_id` 历史字段名叫法，实际传的是**软件标识 identifier**（如 `demo-test`），不是数字 ID。`nonce` 每次必须唯一，重复返回 `1002`；`timestamp` 与服务器时间差不得超过 300 秒。完整加密与解密流程见 [D4-API 接口文档](./D4-API-接口文档.md)。

## 4. 典型 AI 工作流

1. `software/get` 确认软件与默认配置。
2. `software/models` 用客户提供的 endpoint + Key 拉取可选模型。
3. `card/generate` 生成一张卡：AI 填客户指定的三项值，extra 填客户文本内容。
4. 把返回的 K 码交给软件；软件用内置公钥 A 解密第一层，拿到 `u`（接口地址）与 `b`（该软件公钥 B）。
5. 软件调用 `/api/status` 或 `/api/ticket` 拿到业务包，用公钥 B 解密得到实际 AI 配置。
6. 客户要求换模型时，`card/update` 只传 `ai.model`，其余字段自动保留。

同一个软件的不同卡密可以下发完全不同的 AI 配置：配置存在卡的 `custom_config`，读取时由 `mergeConfig()` 与软件默认配置合并，`ai` 按字段覆盖，`text/json/script` 整体替换。

## 5. 给 AI 的工具描述（可直接复制）

```
你有一个软件授权管理系统的 HTTP API。基址由用户给出，密钥由环境变量 API_KEY 提供。
所有请求都是 POST，Header 带 Authorization: Bearer <API_KEY>，Body 是 JSON。
调用前先确认用户意图属于哪个资源：software（软件）、card（卡密）、stats/log（统计日志）、system（系统设置）、license（授权联调）。
创建软件需要用户明确给出 name 与 identifier；identifier 只允许字母数字下划线和短横线。
生成卡密前必须确认软件、模式（time/times）、数量与有效期/次数。
修改 AI 配置时只允许传 endpoint/key/model 三个字段的值，不得改动配置结构。
删除、轮换密钥、撤销密钥属于危险操作，必须先向用户复述目标再执行。
任何 code != 0 都要把 msg 原样告诉用户，不要自行重试扣次类接口。
```

## 6. 错误码

| code | 含义 |
|---|---|
| 0 | 成功 |
| 1001 | 参数错误 / 业务校验失败 |
| 1002 | 请求过期或 nonce 重复 |
| 2001 | 目标不存在 |
| 2002 | 已停用 |
| 2003 | 模式不匹配 |
| 2004 | 已过期 |
| 2005 | 次数耗尽 |
| 2006 | 票据无效或已使用 |
| 3001 | K 码格式错误 |
| 3002 | 软件标识无效 |
| 4001 | 缺少 API 密钥 |
| 4002 | 密钥无效 |
| 4003 | 密钥已撤销 |
| 4004 | 密钥已过期 |
| 4005 | 密钥权限不足 |
| 9001 | 服务内部错误 |

## 7. 测试脚本

仓库自带 `tools/api_smoke_test.sh`：

```bash
API_KEY=lsk_xxx BASE=http://服务器IP bash tools/api_smoke_test.sh
```

它会依次调用鉴权、只读、统计、日志接口，并验证「无密钥」确实返回 4001。

## 8. 安全边界

当前部署为内部测试用途：HTTP 明文、无限流、无登录锁定。开放 API 的密钥是唯一写操作凭据，请：

- 不要把全权限密钥交给不可信方；按 scope 最小授权。
- 密钥泄露后立即 `keys/revoke`。
- 生产环境请前置 HTTPS 与 IP 白名单。
