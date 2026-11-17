# 软件激活与授权管理系统

一套零依赖的 PHP + MySQL 激活码（K 码）发放与授权系统，面向多个客户端软件，支持时效型 / 次数型两种授权、远程加密配置下发，以及换服务器用的 K 码地址转换。

> 内部测试定位：追求最低部署门槛，可在廉价虚拟主机（PHP 7.2 + MySQL 5.6、无 HTTPS）上以纯 FTP 方式运行。安全性按需自行加强，生产环境建议迁移云服务器并启用 HTTPS。

## 功能

- **多软件管理**：每个软件独立标识、独立授权默认值、独立下发配置
- **两种授权**：时效型（按到期时间）、次数型（按剩余次数，消费原子扣 1）
- **两层加密**：全局密钥对 A 签发 K 码；每个软件独立密钥对 B 加密业务包
- **配置下发**：AI 配置（接口地址 / Key / 模型）、自定义文本、JSON 参数，整体加密下发
- **地址转换**：换服务器 / 域名时重签 K 码，软件无需重新发布
- **后台**：安装向导、管理员账号、软件 / 卡密 CRUD、请求日志、统计概览
- **零依赖**：无 Composer、无框架，仅用 PHP 标准扩展

## 密钥体系

| 密钥 | 范围 | 存放 | 用途 |
|---|---|---|---|
| 私钥 A | 全局唯一 | 服务器 `keys/private_a.pem` | 签发 / 重签 K 码 |
| 公钥 A | 全局唯一 | 内置于所有软件 | 离线解 K 码，得卡号、地址、公钥 B |
| 私钥 B_i | 每软件一对 | 服务器 `keys/*_private_b.pem` | 加密该软件业务包 |
| 公钥 B_i | 每软件一对 | 随该软件 K 码下发 | 软件解密业务包 |

软件**只内置一把公钥 A**，各软件公钥 B 由 K 码携带。轮换某软件 B 密钥后，重新生成该软件 K 码即可。

## 目录结构

```
public/           Web 根目录（唯一对外目录）
  index.php       单一入口
  assets/         样式与脚本
app/
  bootstrap.php   引导：自动加载、配置、异常处理
  Router.php      路由分发
  Core/           Config / DB / Crypto / Log / Response / Auth / View / Util
  Controller/     安装、管理员、软件、卡密、日志、API、转换
  Service/        业务逻辑
  Repository/     数据访问
  views/          模板
config/           配置（安装时生成 config.php）
keys/             私钥（服务器外置，禁止 Web 访问）
storage/          日志与会话
install/          建表脚本与安装锁
docs/             D1 需求 / D2 架构 / D3 数据字典 / D4 API
```

## 安装

1. 创建 MySQL 数据库（如 `license_db`），字符集 `utf8mb4`。
2. 把 `public/` 设为站点文档根；`config/`、`keys/`、`app/`、`storage/`、`install/` 放在文档根之外。
3. 访问首页，进入安装向导：填数据库连接 → 测试连接 → 填管理员账号 → 安装。
4. 安装程序自动建表、生成全局密钥对 A、写入配置并锁定安装。
5. 下载公钥 A（`软件管理 → 下载全局公钥 A`）内置到软件；创建软件后下载对应公钥 B。

若主机无法把目录移出文档根，至少用 `.htaccess` 拒绝访问 `config/`、`keys/`、`storage/`。

## 客户端对接

接口端点（完整字段见 [docs/D4-API-接口文档.md](docs/D4-API-接口文档.md)）：

```
GET/POST index.php?r=api/ping       连通性测试
POST    index.php?r=api/activate    时效型激活（不扣次，次数卡返回 2003）
POST    index.php?r=api/status      只读查询授权与配置（不扣次，两种模式通用）
POST    index.php?r=api/ticket      次数型消费（原子扣 1）
POST    index.php?r=api/refund      失败退次（需带 ticket，一张票据仅退一次）
```

也可以走路径式：`http://{host}/api/activate`。全部接口已开启 CORS，浏览器 / WebView 可跨域直连。

通用参数：`card_id`、`software_id`（软件标识，来自 K 码 `s`）、`device_id`、`nonce`、`timestamp`。

客户端解密流程：

1. 用内置**公钥 A** 解 K 码 → 得到 `card_id`、服务器地址 `u`、**该软件公钥 B** `b`、软件标识 `s`
2. 带参数请求 `status`（只读）或 `ticket`（扣次），拿到加密业务包
3. 用第 1 步得到的**公钥 B** 解密业务包 → 得到授权与配置
4. 用配置里的 AI 信息本地直连 AI 接口

> 密文是**带长度前缀的变长结构**（`algo|keyLen|RSA|ivLen|IV|tagLen|tag|cipher`），
> 不是定长偏移，客户端必须按长度字段解析。详见 D4 §6。

## 运行环境

- PHP 7.2 ~ 8.x（代码按 7.2 语法编写）
- MySQL 5.6 / 5.7
- 必需扩展：`openssl`、`pdo_mysql`、`json`、`session`、`mbstring`

## 文档

- [D1 用户需求梳理](docs/D1-用户需求梳理文档.md)
- [D2 项目技术架构](docs/D2-项目技术架构文档.md)
- [D3 数据字典](docs/D3-数据字典.md)
- [D4 API 接口文档](docs/D4-API-接口文档.md)

## 许可

MIT
