# 软件激活与授权管理系统 — API 接口文档

| 项目 | 内容 |
|---|---|
| 文档编号 | D4 |
| 版本 | v1.1 |
| 状态 | 评审稿 |
| 编写日期 | 2026-11-17 |
| 配套文档 | D1 需求梳理、D2 技术架构、D3 数据字典 |
| Base URL | `http://{license_host}/api`（地址来自 K 码解出的 `u` 字段） |

---

## 修订记录

| 版本 | 日期 | 修订人 | 说明 |
|---|---|---|---|
| v1.0 | 2026-11-17 | 架构评审 | 初稿，基于初始需求与两轮澄清 |
| v1.1 | 2026-11-17 | 架构评审 | Base URL 改 HTTP；K 码载荷新增公钥 B_i 字段 `b`；业务包改用该软件私钥 B_i 加密、公钥 B_i 解密；更新客户端解密流程与联调清单 |

---

## 1. 通用约定

### 1.1 传输与格式

- 协议：HTTP（当前按 HTTP 部署；如需生产环境可自行加 HTTPS）。
- 客户端接口请求/响应：`POST` + `application/x-www-form-urlencoded`（兼容老客户端）或 `application/json`（二选一，首版建议 form 更易对接）。
- 字符编码：UTF-8。
- 时间：Unix 时间戳（秒），客户端与服务器时间差超 300 秒的请求被拒绝。

### 1.2 公共请求参数

每个客户端接口都需携带：

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `card_id` | string | 是 | 卡号（由 K 码解出） |
| `software_id` | string | 是 | 软件标识 |
| `device_id` | string | 是 | 客户端生成的设备标识 |
| `nonce` | string | 是 | 随机串（建议 32 字节随机 base64url），每次唯一 |
| `timestamp` | int | 是 | 当前 Unix 时间戳（秒） |

### 1.3 公共响应结构

```json
{
  "code": 0,
  "msg": "ok",
  "request_id": "a1b2c3d4e5f6...",
  "data": { }
}
```

- `code=0` 成功，非 0 见 D2 §7.1 错误码表。
- 客户端**只依据 `code` 处理逻辑**，不得解析内部异常文本。
- `request_id` 用于问题排查，请客户端在反馈时提供。

### 1.4 错误码速查

| code | 含义 |
|---|---|
| 0 | 成功 |
| 1001 | 参数缺失/格式错误 |
| 1002 | 请求超时/重放 |
| 2001 | 卡不存在 |
| 2002 | 卡已禁用 |
| 2003 | 授权类型不符 |
| 2004 | 已过期（时效型） |
| 2005 | 次数耗尽（次数型） |
| 3001 | K 码格式/校验失败 |
| 3002 | 软件标识无效 |
| 9001 | 系统内部错误 |

### 1.5 限流与幂等

- 频率限制：默认每 `card_id + IP` 每分钟 60 次，超限返回 1002。
- 幂等：相同 `nonce` 重复提交返回 1002（服务端唯一索引去重）。
- 时间窗口：`|服务器时间 - timestamp| > 300` 返回 1002。

---

## 2. 客户端授权接口

> 调用前，客户端必须已用内置的**公钥 A** 本地离线解出 K 码中的 `card_id`、`u`（服务器地址）与 `b`（该软件公钥 B_i，供解密业务包）。

### 2.1 校验并获取授权（时效型 / 通用）

用于激活与时效型校验。次数型也可先调此接口读取授权状态（不扣次）。

**请求**

```
POST /api/activate
```

| 参数 | 说明 |
|---|---|
| card_id, software_id, device_id, nonce, timestamp | 公共参数 |

**处理逻辑**

1. 校验时间窗口、nonce 去重、软件标识有效性。
2. 查卡：`status=1`？否则 2002；存在？否则 2001。
3. 模式为 `time` 且 `now<=expire_at` → 通过；过期 → 2004。
4. 记录日志 `action=activate`。
5. 查卡识别所属软件，组装业务包（auth 含 type/time/expire；config 取卡专属或软件默认）→ 用该软件**私钥 B_i** 混合加密。

**成功响应**

```json
{
  "code": 0,
  "msg": "ok",
  "request_id": "a1b2c3d4...",
  "data": {
    "package": "<base64url 加密业务包>"
  }
}
```

- `package` 由客户端用**从 K 码中解出的该软件公钥 B_i** 解密，解密流程见 §2.3 / §5。

**失败响应示例**

```json
{"code":2004,"msg":"激活码已过期","request_id":"a1b2c3d4...","data":{}}
```

### 2.2 申请消费票据（次数型扣次）

次数型每次消费前调用；成功即原子扣 1。

**请求**

```
POST /api/ticket
```

| 参数 | 说明 |
|---|---|
| card_id, software_id, device_id, nonce, timestamp | 公共参数 |

**处理逻辑（同一事务）**

1. 公共校验（时间窗、nonce、软件标识）。
2. `UPDATE lc_cards SET remain=remain-1, used=used+1, last_used_at=now WHERE card_id=? AND software_id=? AND status=1 AND mode='times' AND remain>0`，`affected_rows===1` 否则回滚并返回 2005/2002。
3. 生成票据写入 `lc_tickets`（`expire_at=now+ticket_ttl`，默认 60 秒，`used_at=0`）。
4. 写日志 `action=ticket`，记录 `remain_before/remain_after`。
5. 提交事务，返回票据 + 用该软件**私钥 B_i** 加密的业务包（auth 含最新 remain）。

**成功响应**

```json
{
  "code": 0,
  "msg": "ok",
  "request_id": "b2c3d4e5...",
  "data": {
    "ticket": "<票据令牌>",
    "expire_at": 1731000060,
    "package": "<base64url 加密业务包，含最新 remain>"
  }
}
```

**失败响应**：次数耗尽 2005、卡禁用 2002、模式不符 2003。

> 语义说明：`ticket` 是本次消费的短期凭证；本系统**不代理 AI 流量**，客户端拿到解密后的配置即本地直连 AI。票据用于服务端统计与防重放，客户端应在 `expire_at` 后丢弃。

### 2.3 客户端解密流程（离线，非 HTTP）

客户端**只内置公钥 A**（不预置公钥 B_i），解密步骤：

**第一步：解 K 码（得到 card_id、服务器地址、公钥 B_i）**

```
1. base64url 解码 K码 -> 二进制
2. 拆出: RSA密文段(256B for 2048bit) | iv(12B) | tag(16B) | AES密文
3. openssl_public_decrypt(RSA密文段, 内置公钥A) -> aesKey (32B)
4. AES-256-GCM 解密(AES密文, aesKey, iv, tag) -> payload JSON
5. 读取 payload.card_id、payload.u（授权服务器地址）与 payload.b（该软件公钥 B_i，PEM 文本）
```

**第二步：解业务包（得到授权与配置）**

```
用第一步从 K 码解出的公钥 B_i 代替公钥 A，按相同布局解出业务包 payload:
  payload.auth    -> 授权信息（type/expire_at/remain）
  payload.config.ai       -> {endpoint, key, model}
  payload.config.text     -> 自定义文本
  payload.config.json     -> JSON 参数对象
客户端据此本地直连 AI 接口。
```

> 公钥 B_i 随软件 K 码动态下发，软件无需预置、也无需为每款软件更新内置公钥；轮换某软件 B_i 后重发该软件 K 码即可。

> 若服务端降级为 AES-256-CBC，则格式改为 `iv(16B) | HMAC(32B) | AES密文`，客户端须先验 HMAC 再解密，验签失败丢弃。

---

## 3. 管理端接口（Web + AJAX）

管理端接口均需管理员会话（Cookie session）与 CSRF 令牌。请求方式为表单 POST 或 AJAX。以下列出主要端点与参数，响应以页面渲染或 JSON 形式（AJAX 时）。

### 3.1 认证

| 端点 | 方法 | 参数 | 说明 |
|---|---|---|---|
| `/login` | GET/POST | username, password, csrf | 登录；成功写 session |
| `/logout` | POST | csrf | 退出 |
| `/profile/password` | POST | old_password, new_password, csrf | 改密 |

### 3.2 软件管理

| 端点 | 方法 | 参数 | 说明 |
|---|---|---|---|
| `/software` | GET | — | 列表 |
| `/software/save` | POST | id?, name, identifier, default_mode, default_days, default_times, default_config, status, csrf | 新增/编辑 |
| `/software/delete` | POST | id, csrf | 软删除/禁用 |
| `/software/config` | GET | id | 读取默认配置 |

### 3.3 卡密管理

| 端点 | 方法 | 参数 | 说明 |
|---|---|---|---|
| `/card` | GET | software_id?, status?, keyword?, page | 列表（分页/筛选） |
| `/card/generate` | POST | software_id, mode, days/times, count, custom_config?, remark, csrf | 批量生成并返回 K 码列表，写 `batch_no` |
| `/card/extend` | POST | id, days, csrf | 时效型延期 |
| `/card/times` | POST | id, remain, csrf | 次数型改次数（覆盖，记日志） |
| `/card/status` | POST | id, status, csrf | 启用/禁用 |
| `/card/detail` | GET | id | 单卡详情 + 消费记录 |
| `/card/export` | GET | batch_no? | 导出 K 码 CSV/TXT |

### 3.4 日志与统计（尽力而为增强）

> 统计与概览为尽力而为的增强功能，核心是发卡/激活；若环境不具备统计条件，可仅保留请求日志，不影响主流程。

| 端点 | 方法 | 参数 | 说明 |
|---|---|---|---|
| `/log/requests` | GET | card_id?, software_id?, result?, start?, end?, page | 请求日志分页 |
| `/log/card-stats` | GET | card_id | 单卡：激活次数、消费次数、扣次前后 |
| `/log/overview` | GET | — | 概览：今日请求/成功失败/扣次总数/失败 Top5 |
| `/log/clean` | POST | days, csrf | 清理 N 天前的请求日志（超管） |

### 3.5 管理员管理（超管）

| 端点 | 方法 | 参数 | 说明 |
|---|---|---|---|
| `/admin` | GET | — | 管理员列表 |
| `/admin/save` | POST | id?, username, password?, role, status, csrf | 增删/编辑 |
| `/admin/reset-password` | POST | id, new_password, csrf | 重置密码 |

---

## 4. 地址转换接口

### 4.1 转换页

| 端点 | 方法 | 说明 |
|---|---|---|
| `/convert` | GET | 转换页：显示/设置当前服务器地址，输入旧 K 码 |
| `/convert/save-url` | POST | url, csrf：保存新的授权服务器地址到 `license_url` |
| `/convert/do` | POST | old_kcode, csrf：执行转换，输出新 K 码 |

### 4.2 转换接口 `/convert/do`

**请求**

| 参数 | 说明 |
|---|---|
| `old_kcode` | 旧 K 码 |
| `csrf` | 管理会话 CSRF 令牌（需管理员登录） |

**处理逻辑**

1. base64url 解码 → 用**全局私钥 A** 混合解密 → 得 payload。
2. 校验 `v` 版本与 nonce 合法性；失败返回 3001。
3. `payload.u = 当前 license_url`；**保留 `payload.c`（card_id）与 `payload.b`（公钥 B_i）不变**；生成新 nonce。
4. 用**全局私钥 A** 重新混合加密 → 输出新 K 码。
5. 写 `lc_admin_logs`（action=`convert`，detail 含旧地址→新地址、卡号）。

**成功响应（JSON）**

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "new_kcode": "<新的 base64url K码>",
    "card_id": "CARD202611170001",
    "old_url": "http://old.example.com/api",
    "new_url": "http://new.example.com/api"
  }
}
```

**失败响应**：`3001` K 码格式/校验失败；`1001` 参数错误。

> 注意：转换只改 K 码内的服务器地址，**不改 `card_id`、公钥 B_i，也不改卡数据**（卡在数据库中，随服务器迁移一并迁移）。转换后新 K 码解出的 `card_id` 与公钥 B_i 不变，软件无需重新发布。

---

## 5. 加解密数据格式参考（供客户端实现对齐）

### 5.1 K 码 / 业务包二进制布局

```
偏移量        长度(字节)   内容
0             256          RSA 用私钥加密的 AES-256 密钥（2048 位密钥）
256           12           AES-GCM 的 IV
268           16           AES-GCM 的认证标签 tag
284           N            AES-GCM 加密的 payload 密文
整体           --          上述二进制拼接后做 base64url 编码（无填充 =）
```

- 若 RSA 密钥为 3072 位，首段长度改为 384；客户端按内置公钥位数确定。首版统一 **2048 位**。
- base64url：把 `+`→`-`、`/`→`_`、去掉 `=`。

### 5.2 K 码 payload

```json
{"v":1,"c":"CARD202611170001","u":"http://license.example.com/api","b":"<该软件公钥B_i的PEM base64>","x":"<nonce>"}
```

- `b`：该软件的公钥 B_i（PEM 文本做 base64 后的字符串），由服务器创建软件时生成并入库，发卡时写入 K 码；软件用内置公钥 A 解出 K 码后取出 `b`，作为第二层公钥解密业务包。
- 公钥 B_i 位于 AES-GCM 加密的载荷内（长度不限）；RSA 段始终只加密 32 字节 AES 密钥，不受 RSA 明文长度限制影响。

### 5.3 业务包 payload

```json
{
  "v": 1,
  "c": "CARD202611170001",
  "auth": {"type":"times","remain":9,"total":10,"expire_at":0},
  "config": {
    "ai": {"endpoint":"https://api.xxx.com/v1/chat/completions","key":"sk-xxxx","model":"gpt-4o-mini"},
    "text": "欢迎使用",
    "json": {"temperature":0.7,"max_tokens":2048}
  },
  "ts": 1731000000,
  "nonce": "a1b2c3..."
}
```

- `auth.type`：`time` 时效型 / `times` 次数型。
- 业务包由服务器用**该软件私钥 B_i** 加密，客户端用从 K 码解出的**公钥 B_i** 解密。
- 客户端解密后应校验 `c` 与请求的 `card_id` 一致、`ts` 在合理窗口内，防止错包/旧包。

---

## 6. 联调测试清单（端到端）

| 步骤 | 操作 | 预期 |
|---|---|---|
| 1 | 安装生成全局密钥对 A 并下载公钥 A；创建软件时自动生成该软件 B_i | keys/ 下有 private_a.pem、public_a.pem 及每软件 private_b_{id}.pem，私钥 0600；公钥 B_i 入库 |
| 2 | 创建软件（次数型，10 次），生成 1 张卡 | 得到 K 码，库中有卡记录，K 码载荷含 `b` |
| 3 | 用内置公钥 A 解 K 码 | 得 card_id 正确、u=当前地址、b=该软件公钥 B_i |
| 4 | 调 `/api/activate` | 返回加密业务包 |
| 5 | 用 K 码中解出的公钥 B_i 解包 | 得 auth.remain=10、config 完整 |
| 6 | 调 `/api/ticket` 三次 | 每次扣 1，remain 依次 9/8/7，日志有前后值 |
| 7 | 重放同一 nonce | 返回 1002 |
| 8 | 时效卡：设置过期 | 调 activate 返回 2004 |
| 9 | 地址转换：改新地址，转旧码 | 新码解出 card_id 与公钥 B_i 不变、u=新地址 |
| 10 | 抓包与查库 | 无明文 API Key / 配置 |
| 11 | 次数扣完（remain=0） | 调 ticket 返回 2005 |
| 12 | 错误码检查 | 客户端只见友好文案，无堆栈 |

---

## 7. 版本与兼容说明

- 客户端与服务端通过 payload 中的 `v` 字段协商格式版本；首版 `v=1`。
- 服务端升级加密格式时递增 `v`，并保持对旧 `v` 的解密兼容，避免已发软件失效。
- 公钥 B_i 由 K 码载荷的 `b` 字段携带，客户端无需预置；轮换某软件 B_i 后重发该软件 K 码即可，无需更新客户端内置公钥 A。
- 所有接口在 PHP 7.2 + MySQL 5.6 环境验证通过为准入标准。
