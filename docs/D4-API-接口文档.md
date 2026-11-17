# 软件激活与授权管理系统 — API 接口文档

| 项目 | 内容 |
|---|---|
| 文档编号 | D4 |
| 版本 | v1.2 |
| 状态 | 与源码一致（已逐条核对 `Crypto.php` / `LicenseService.php` / `Response.php` / `Router.php`） |
| 编写日期 | 2026-11-17 |
| 配套文档 | D1 需求梳理、D2 技术架构、D3 数据字典 |
| Base URL | K 码载荷中的 `u` 字段，形如 `http://{host}/api` |

> **v1.2 说明**：本版修正了 v1.1 中与源码不一致的描述，并补充了客户端对接必需的两个接口。凡与本文件冲突的旧描述，一律以本文件为准。

---

## 修订记录

| 版本 | 日期 | 说明 |
|---|---|---|
| v1.0 | 2026-11-17 | 初稿 |
| v1.1 | 2026-11-17 | K 码载荷新增公钥 B_i；业务包改用每软件私钥 B_i |
| v1.2 | 2026-11-17 | **修正密文布局**（原文写成固定 256/12/16，实为带长度前缀的变长结构）；**修正 CBC 降级描述**（无 HMAC 的说法已更正，代码已补 HMAC）；**修正 `activate` 语义**（原文称次数卡可用，实际返回 2003）；**新增 `/api/status`**（只读不扣次，两种模式通用）；**新增 `/api/refund`**（失败退次）；**新增 CORS**；修正 K 码 `b` 字段为原始 PEM、补充 `s` 字段；修正业务包字段名 `n`；修正管理端真实路由；删除未实现的限流描述 |

---

## 1. 通用约定

### 1.1 传输与格式

- 协议：HTTP（当前内部测试部署，生产环境可自行加 HTTPS）。
- 端点地址：由 K 码解出的 `u` 作为前缀，客户端拼接口名。
  - 推荐：`{u}/activate` → `http://{host}/api/activate`
  - 兼容：`{host}/?r=api/activate`
  - 两种写法等价，服务端同时支持路径式与 `?r=` 式路由。**静态资源目录名不要与模块名冲突**（模块名：install / admin / dashboard / software / card / log / convert / api）。
- 请求方法：`POST`。
- 请求体：`application/x-www-form-urlencoded` 或 `application/json` 均可。
- 字符编码：UTF-8。
- 时间：Unix 时间戳（秒）。**服务端与客户端时间差超过 300 秒直接拒绝**（返回 1002），客户端需保证设备时间基本准确。

### 1.2 跨域（CORS）

所有 `/api/*` 响应均带以下头，浏览器 / WebView 可跨域直连：

```
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, POST, OPTIONS
Access-Control-Allow-Headers: Content-Type, X-Requested-With
Access-Control-Max-Age: 86400
```

`OPTIONS` 预检请求返回 `204 No Content` 且带上述头，不需额外处理。客户端 API 不使用 Cookie，因此允许任意来源。

### 1.3 公共请求参数

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `card_id` | string | 是 | 卡号，来自 K 码载荷 `c` |
| `software_id` | string | 是 | 软件标识，来自 K 码载荷 `s`（**客户端无需内置**） |
| `device_id` | string | 是 | 客户端生成的稳定设备标识 |
| `nonce` | string | 是 | 每次请求唯一，建议 16–32 位随机串 |
| `timestamp` | int | 是 | 当前 Unix 时间戳（秒） |
| `ticket` | string | 否 | 仅 `/api/refund` 需要 |

`device_id` 说明：服务端会记录**首次成功请求**的设备标识（`cards.device_id`、`cards.activated_at`），但**不做绑定校验**——同一张卡换设备仍可继续使用。它只用于后台排查与日志统计，不构成技术约束。

### 1.4 公共响应结构

```json
{
  "code": 0,
  "msg": "ok",
  "request_id": "d27ca21b1117ff12",
  "data": {}
}
```

- `code=0` 成功；非 0 见 §1.5。客户端**只依据 `code` 分支处理**，`msg` 仅作可选提示。
- `request_id` 每次请求唯一，排查问题时提供给服务端。

### 1.5 错误码

| code | 含义 | 客户端文案 | 出现接口 |
|---|---|---|---|
| 0 | 成功 | — | 全部 |
| 1001 | 参数缺失/格式错误 | 请求参数不合法 | 全部 |
| 1002 | 时间戳超窗 / nonce 重复 | 请求已过期，请重试 | 全部 |
| 2001 | 卡不存在 | 激活码无效 | 全部 |
| 2002 | 卡已禁用 | 激活码已被停用 | 全部 |
| 2003 | 授权类型不符 | 该激活码不适用当前操作 | activate / ticket / refund |
| 2004 | 已过期（时效型） | 激活码已过期 | activate / status |
| 2005 | 次数耗尽（次数型） | 可用次数已用完 | status / ticket |
| 2006 | 票据无效 / 已核销 / 已超期 | 无法退回次数 | refund |
| 3002 | 软件标识无效 | 软件未授权 | 全部 |
| 9001 | 服务端内部错误 | 服务暂时不可用 | 全部 |

> `3001`（K 码格式/校验失败）只出现在后台**地址转换页**，客户端接口不会返回。

### 1.6 幂等与防重放

- **nonce 去重**：服务端按 `sha256(card_id + "|" + nonce)` 记录，重复即返回 1002。因此**同一 nonce 只能成功使用一次**，重试必须换 nonce。
- **时间窗**：`|服务器时间 − timestamp| > 300` 返回 1002。
- **未实现限流**：源码中**没有**内置频率限制（v1.1 文档写的"每卡 60 次/分钟"并未实现）。如需防刷，请在 Nginx 层用 `limit_req` 或在前置 CDN 处理，不要依赖服务端返回限流错误码。

---

## 2. 客户端接口

### 2.1 接口与授权模式的对应关系（重要）

| 接口 | 时效型 `time` | 次数型 `times` | 是否扣次 |
|---|---|---|---|
| `POST /api/activate` | ✅ 校验到期时间 | ❌ 返回 **2003** | 否 |
| `POST /api/status` | ✅ | ✅ | 否 |
| `POST /api/ticket` | ❌ 返回 **2003** | ✅ 原子扣 1 | 是 |
| `POST /api/refund` | ❌ 返回 **2003** | ✅ 退回 1 | 否（回补） |
| `GET/POST /api/ping` | — | — | 否 |

**这是 v1.1 文档最容易误导的地方**：次数型卡**不能**用 `activate` 探测状态，会被拒绝。只读查询请用 `status`。

### 2.2 `/api/activate` — 时效型激活/校验（不扣次）

**处理逻辑**：校验公共参数 → 查卡（须属于该软件）→ 状态启用 → 模式必须为 `time` → 未过期 → 组装业务包用该软件私钥 B_i 加密返回。

**成功响应**

```json
{
  "code": 0,
  "msg": "ok",
  "request_id": "7f7a24f5c7aa7e3b",
  "data": {
    "package": "<base64url 业务包>",
    "mode": "time"
  }
}
```

**失败**：2001 / 2002 / 2003（卡是次数型）/ 2004。

### 2.3 `/api/status` — 只读查询授权与配置（不扣次）【新增】

两种模式通用。用于：启动时拉配置、界面展示剩余次数、判断是否还能用。

**处理逻辑**：

1. 公共校验（时间窗、nonce、软件标识）。
2. 查卡 → 状态启用，否则 2002/2001。
3. `time` 模式：`expire_at>0 且 now>expire_at` → 2004。
4. `times` 模式：`remain<=0` → 2005（**只判断，不扣减**）。
5. 记录首次设备（仅记录）。
6. 组装并返回业务包，`auth.remain` 为当前真实剩余次数。

**成功响应**

```json
{
  "code": 0,
  "msg": "ok",
  "request_id": "a1b2c3d4e5f6a7b8",
  "data": {
    "package": "<base64url 业务包>",
    "mode": "times"
  }
}
```

> 与 `ticket` 的区别：`status` 多次调用不消耗次数，可以放心用于启动时读取配置；`ticket` 每调一次扣 1。

### 2.4 `/api/ticket` — 次数型消费（原子扣 1）

**处理逻辑（同一事务）**：

1. 公共校验。
2. 查卡 → 启用 → 模式必须为 `times`，否则 2003。
3. 单条原子更新：`UPDATE cards SET remain=remain-1, used=used+1, last_used_at=now WHERE card_id=? AND software_id=? AND status=1 AND mode='times' AND remain>0`；受影响行数 ≠ 1 则回滚并返回 2005。
4. 生成票据（48 位 base62 随机串）写入 `lc_tickets`，`expire_at = now + ticket_ttl`（默认 **1800 秒**）。
5. 写请求日志（含 `remain_before` / `remain_after`）。
6. 提交事务，返回票据 + 业务包（`auth.remain` 为扣次后的最新值）。

**成功响应**

```json
{
  "code": 0,
  "msg": "ok",
  "request_id": "303940a426f27d0a",
  "data": {
    "ticket": "cC6eoMWeYaedNKEdiAPNQMG7Pl7ZK9KLjIe6VzFrVvOhbNpG...",
    "expire_at": 1794906600,
    "package": "<base64url 业务包>"
  }
}
```

**失败**：2005（次数耗尽）/ 2002（禁用）/ 2003（卡是时效型）。

> 票据不参与任何鉴权，只用于统计与失败退次。默认有效期 30 分钟，客户端应保存到本地，在 AI 调用失败时用它调 `/api/refund`。

### 2.5 `/api/refund` — 失败退回 1 次【新增】

AI 超时、用户取消、生成失败时调用，把刚才扣掉的 1 次退回。

**额外参数**：`ticket`（`/api/ticket` 返回的票据原文）。

**处理逻辑（同一事务）**：

1. 公共校验 + `ticket` 非空。
2. 卡必须为 `times` 模式，否则 2003。
3. 核销票据：`UPDATE tickets SET used_at=now WHERE ticket=? AND card_id=? AND used_at=0 AND expire_at>=now`；受影响行数 ≠ 1 → 2006（重复退、票据不存在或已超期）。
4. 回补次数：`UPDATE cards SET remain=remain+1, used=used-1 WHERE ... AND remain<total_times`（防止刷到超过购买量）。
5. 写日志（`action=refund`，含前后值）后提交。

**成功响应**

```json
{
  "code": 0,
  "msg": "ok",
  "request_id": "e5f6a7b8c9d0e1f2",
  "data": { "remain": 3 }
}
```

**失败**：2006（票据已核销/超期）、2003（非次数卡）、2001/2002。

> **一张票据只能退一次**；退回后 `remain` 不会超过该卡的 `total_times`。若客户端重复调用同一票据，第二次返回 2006。

### 2.6 `/api/ping` — 连通性测试

`GET` 或 `POST` 均可，无参数。

```json
{"code":0,"msg":"ok","request_id":"...","data":{"time":1794904801,"service":"license-api"}}
```

---

## 3. 客户端三种典型流程

### 3.1 时效型

```
启动 → 解 K 码（本地，离线）得 c / u / b / s
     → POST {u}/activate
     → code=0：用 b 解包得 config 与 auth.expire_at
     → code=2004：提示已过期
     → 缓存到本地（建议 6–12 小时刷新一次）
     → 本地直连 AI
```

### 3.2 次数型（推荐：一次用户点击扣一次）

```
启动 → 解 K 码 → POST {u}/status  （读配置与剩余次数，不扣次）
     → 界面上显示"剩余 N 次"

用户点击"开始解读"
     → POST {u}/ticket        （扣 1，拿 ticket + package）
     → 用 b 解包拿最新 config
     → 执行本次解读（内部可能包含几十次 AI 请求，都算这一次）
     → 成功：结束，ticket 丢弃
     → 失败/取消：POST {u}/refund {ticket}  （退回 1 次）
```

> 服务端无法感知 AI 实际调用，扣次点由客户端决定。**推荐口径：一次用户点击 = 1 次**，在真正发起 AI 请求之前扣；不要按"每次 AI 请求"扣，否则"完整档"一次点击会连续扣几十次。

### 3.3 次数型（简版，不要退次）

若产品上接受"发起即扣、失败不退"，可省掉 `refund`，流程为：`status` 展示 → 点击时 `ticket` → 失败仅提示。此时请在产品说明中写明"失败不退次数"。

---

## 4. 管理端接口（Web 后台）

管理端全部走 `?r=模块/方法`，需管理员会话（Cookie）与 CSRF 令牌。路径式（`/admin/login`）同样可用，但建议直接用 `?r=` 形式，避免与静态目录冲突。

| 路由 | 方法 | 主要参数 | 说明 |
|---|---|---|---|
| `?r=admin/login` | GET/POST | username, password | 登录页 / 提交登录 |
| `?r=admin/logout` | POST | — | 退出 |
| `?r=admin/password` | GET/POST | old_password, new_password | 修改自己的密码 |
| `?r=admin/index` | GET | — | 管理员列表（超管） |
| `?r=admin/add` | POST | username, password, role | 新增管理员（超管） |
| `?r=admin/remove` | POST | id | 删除管理员（超管） |
| `?r=dashboard/index` | GET | — | 概览与统计 |
| `?r=dashboard/clean` | POST | days | 清理 N 天前日志 |
| `?r=software/index` | GET | — | 软件列表 |
| `?r=software/create` | GET/POST | name, identifier, default_mode, default_days, default_times, default_config | 新建软件（自动生成 B_i 密钥对） |
| `?r=software/edit` | GET | id | 编辑页 |
| `?r=software/save` | POST | id, name, default_mode, default_days, default_times, default_config, status | 保存 |
| `?r=software/status` | POST | id, status | 启用/停用 |
| `?r=software/publicKey` | GET | id | 查看该软件公钥 B_i |
| `?r=software/rotate` | POST | id | 轮换该软件 B_i 密钥对（需重发该软件 K 码） |
| `?r=software/publicA` | GET | — | 下载全局公钥 A（**客户端要内置的就是它**） |
| `?r=software/models` | POST | endpoint, key | 用接口地址与 Key 拉取可选模型列表（服务端代请求 `/models`） |
| `?r=card/index` | GET | software_id, status, keyword, page | 卡密列表 |
| `?r=card/create` | GET | — | 发卡页 |
| `?r=card/generate` | POST | software_id, mode, days, times, count, remark, custom_config | 批量发卡，返回 K 码 |
| `?r=card/extend` | POST | id, days | 时效型延期 |
| `?r=card/setTimes` | POST | id, remain | 次数型改次数 |
| `?r=card/status` | POST | id, status | 启用/禁用 |
| `?r=card/detail` | GET | id | 单卡详情 + 消费记录 |
| `?r=card/export` | GET | batch_no | 导出 K 码 |
| `?r=card/sync` | POST | — | 刷新软件关联信息：返回卡数/软件数/已关联/孤儿卡/停用软件下的卡/自定义配置卡数 |
| `?r=log/requests` | GET | card_id, software_id, result, page | 请求日志 |
| `?r=log/stats` | GET | — | 统计汇总 |
| `?r=log/adminLogs` | GET | — | 管理操作日志 |
| `?r=convert/index` | GET/POST | POST: new_url, kcode, csrf | 地址转换页 |

---

## 5. 地址转换

`?r=convert/index` 单页完成：填新地址 + 旧 K 码 → 用**全局私钥 A** 解旧码 → 只替换 `u` → 保留 `c`（卡号）、`b`（公钥 B_i）、`s`（软件标识）、重新生成 `x` → 用私钥 A 重新加密输出新码。

- 只改 K 码里的服务器地址，**不动数据库中的卡**（卡随服务器迁移一起导）。
- 转换后卡号与公钥 B_i 不变，软件无需重新发布。
- 失败时页面直接展示错误文案「K 码格式错误或校验失败」（内部对应错误码 3001，仅此处使用，客户端接口不会返回该码）。

---

## 6. 加解密格式（客户端实现依据）

### 6.1 二进制布局（真实实现，已核对源码）

**K 码与业务包使用同一套封装**，唯一区别是 RSA 用哪把私钥（A 或 B_i）。

```
偏移从 0 开始，全部大端：

[1 字节]  算法 algo        1 = AES-256-GCM；2 = AES-256-CBC + HMAC-SHA256
[2 字节]  密钥段长度 keyLen
[keyLen]  RSA 密文          = RSA_privEncrypt(AESkey)，2048 位密钥时为 256 字节
[1 字节]  IV 长度 ivLen      GCM 为 12；CBC 为 16
[ivLen]   IV
[1 字节]  标签长度 tagLen    GCM 为 16（认证标签）；CBC 为 32（HMAC-SHA256）
[tagLen]  标签
[剩余]    AES 密文          GCM/CBC 均为 16 字节对齐的密文
整体再用 base64url 编码（`+`→`-`、`/`→`_`、去掉 `=`）
```

**必须按长度字段逐段解析，不能假设固定偏移**：`keyLen` 随 RSA 位数变化（2048 位 = 256），这也是 v1.1 文档写错的地方（原文写成"0 偏移 256 字节 / 256 偏移 12 字节"的定长结构）。

**已验证实例**（含 451 字节 PEM 公钥 B_i 的 K 码）：总长 864 字节 = `1 + 2 + 256 + 1 + 12 + 1 + 16 + 575`，`algo=1`、`keyLen=256`、`ivLen=12`、`tagLen=16`、密文 575 字节，base64url 后约 1152 字符。

### 6.2 解密步骤（客户端）

```
1. base64url 解码 → bin
2. algo    = bin[0]
   keyLen  = bin[1]<<8 | bin[2]           （大端 2 字节）
   encKey  = bin[3 .. 3+keyLen-1]
   p       = 3 + keyLen
   ivLen   = bin[p]
   iv      = bin[p+1 .. p+ivLen]
   p       = p + 1 + ivLen
   tagLen  = bin[p]
   tag     = bin[p+1 .. p+tagLen]
   p       = p + 1 + tagLen
   cipher  = bin[p .. end]
3. aesKey = RSA_publicDecrypt(encKey, 公钥)      → 必须正好 32 字节
4. algo==1: plain = AES-256-GCM-Decrypt(cipher, aesKey, iv, tag)
   algo==2: 先校验 HMAC-SHA256(iv||cipher, aesKey) == tag，再 AES-256-CBC 解密
5. plain 为 UTF-8 JSON
```

**RSA 方向**：服务端用 `openssl_private_encrypt()`（RSAES-PKCS1-v1_5），客户端须用**公钥做 RSAES-PKCS1-v1_5 解密**，即 `m = c^e mod n`，再去掉 `0x00 0x02 || 随机填充 || 0x00` 前缀。

浏览器 `WebCrypto` **没有** `publicDecrypt`/`RSAES-PKCS1-v1_5 decrypt` 能力，只有 `encrypt`，因此纯前端需自行实现：解析 SPKI DER 取 `n`、`e`（BigInt），做模幂 `c^e mod n`，再剥离 PKCS#1 v1.5 填充。Node / Electron / Android 有原生 RSA，直接调用即可。

### 6.3 K 码载荷

```json
{
  "v": 1,
  "c": "CARD-20261117-ZTJ7-S5DD",
  "u": "http://43.128.228.157/api",
  "b": "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkq...\n-----END PUBLIC KEY-----\n",
  "s": "demo-test",
  "x": "XkaWcaJgcAlwTxgW"
}
```

| 字段 | 说明 |
|---|---|
| `v` | 格式版本，当前固定 1 |
| `c` | 卡号 |
| `u` | 授权服务器 Base URL（客户端拼 `/activate` 等） |
| `b` | **该软件公钥 B_i 的原始 PEM 文本**（不是 base64；JSON 里换行以 `\n` 转义） |
| `s` | 软件标识，直接用它的值填 `software_id` |
| `x` | 16 位随机校验串（每次签发生成） |

> `b` 是原始 PEM：客户端拿到后直接当公钥字符串用，**不要再 base64 解码一次**。v1.1 文档写成"PEM base64"是错的。

### 6.4 业务包载荷

```json
{
  "v": 1,
  "c": "CARD-20261117-ZTJ7-S5DD",
  "auth": { "type": "times", "remain": 2, "total": 3, "expire_at": 0 },
  "config": {
    "ai": { "endpoint": "https://api.example.com/v1/chat/completions", "key": "sk-demo-123456", "model": "demo-model" },
    "text": "这是自定义文本",
    "json": { "temperature": 0.7, "max_tokens": 2048 }
  },
  "ts": 1794904801,
  "n": "9f2c...（本次请求 nonce 的 sha256，十六进制）"
}
```

| 字段 | 说明 |
|---|---|
| `auth.type` | `time` / `times` |
| `auth.remain` | 次数型剩余次数（`ticket` 返回的是扣后值） |
| `auth.total` | 次数型总次数 |
| `auth.expire_at` | 时效型到期时间戳，`0` 表示不限 |
| `config.ai` | AI 接口地址、Key、模型名（客户端本地直连，不经服务器） |
| `config.text` | 自定义文本，可为空 |
| `config.json` | 自定义 JSON 参数对象，可为空 |
| `ts` | 服务端签发时间 |
| `n` | 本次请求的 nonce 哈希（**字段名是 `n`，不是 `nonce`**） |

**配置生效规则**：`config` 是「软件默认配置 + 该卡专属覆盖」合并后的结果。

- `ai` 段按**字段级合并**：卡只覆盖 `endpoint` / `key` / `model` 的值，其他固定字段沿用软件默认；同一软件的不同卡可以下发不同 AI 配置。
- `text` / `json` / `script` 段**整段覆盖**：卡上设置了哪个段就替换哪个段，未设置则沿用软件默认。
- 卡未设置任何专属配置时，直接下发软件默认配置。

客户端解包后建议校验 `c` 与请求的卡号一致，`ts` 与本地时间差在合理范围，防止错包。

### 6.5 需要内置的内容

客户端只需内置**全局公钥 A**（`?r=software/publicA` 下载），无需内置任何私钥、任何公钥 B_i、任何软件标识。公钥 B_i 与软件标识都从 K 码里取。

---

## 7. 联调测试清单

| # | 步骤 | 预期 |
|---|---|---|
| 1 | 后台创建软件（次数型，3 次） | `keys/` 下生成该软件 `private_b_*.pem`，公钥 B_i 入库 |
| 2 | 发 1 张卡 | 得到 K 码；载荷含 `c/u/b/s/x` 五个字段 |
| 3 | 客户端用公钥 A 离线解 K 码 | 得到卡号、`u`、`b`（PEM 原文）、`s` |
| 4 | `POST {u}/status` | `code=0`，解包 `auth.remain` 等于剩余次数，**数据库 remain 不变** |
| 5 | `POST {u}/ticket` | `code=0`，返回 `ticket`、`expire_at`、`package`；数据库 remain 减 1 |
| 6 | 用 `b` 解业务包 | `config.ai.key` 等配置正确，`auth.remain` 为扣后值 |
| 7 | `POST {u}/refund` 带上一张 `ticket` | `code=0`，`remain` 回补 1 |
| 8 | 用同一 `ticket` 再 `refund` | `code=2006` |
| 9 | 次数卡调 `{u}/activate` | `code=2003` |
| 10 | 时效卡调 `{u}/activate` | `code=0`，`auth.expire_at` 正确 |
| 11 | 复用同一 `nonce` 再请求 | `code=1002` |
| 12 | `timestamp` 改成 10 分钟前 | `code=1002` |
| 13 | 浏览器 `OPTIONS {u}/status` | `204` + CORS 头 |
| 14 | 扣到 `remain=0` 再 `ticket` | `code=2005` |
| 15 | 地址转换换地址后重解 K 码 | `c`、`b`、`s` 不变，`u` 为新地址 |

---

## 8. 版本与兼容

- 载荷 `v` 为格式版本，当前 `1`。服务端升级格式时递增并保留旧版解密兼容。
- 新增的 `status` / `refund` 是**增量接口**，不影响已有客户端；老客户端只调 `activate` / `ticket` 依然可用（但次数卡只能用 `ticket`）。
- 服务端在 PHP 7.2–8.3 均验证可运行；本机部署环境为 PHP 8.3 + MariaDB 10.11 + Nginx。
