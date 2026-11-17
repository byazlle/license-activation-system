# 安装部署教程

适用版本：v1.3+　环境底线：**PHP 7.2+**（推荐 7.4 / 8.x）、**MySQL 5.6+ / MariaDB 10+**、Nginx 或 Apache。零 Composer 依赖，HTTPS 非必需（内部测试）。

---

## 0. 先理解目录结构

```
license-activation-system/     ← 程序根目录（放 Web 根之外）
├── app/                       业务代码
├── config/config.php          安装时生成（含数据库密码）
├── keys/                      私钥 A 与各软件私钥 B（绝不能放进 public/）
├── install/
│   ├── schema.sql             建表脚本
│   ├── install.lock           安装锁
│   ├── initial_api_key.txt    初始 API 密钥明文
│   └── cli_install.php        命令行静默安装器
├── deploy/bt-onekey.sh        宝塔一键部署脚本
├── tools/                     冒烟测试脚本
└── public/                    ← 唯一需要设为网站根目录的目录
    ├── index.php
    └── assets/
```

**关键点：网站根目录必须指向 `public/`，不是程序根目录。** 这样 `config/`、`keys/`、`install/` 天然无法被浏览器访问。

---

## 1. 方式 A：宝塔一键脚本（推荐）

### 1.1 准备

1. 服务器已安装宝塔 Linux 面板 + Nginx + PHP 7.2+ + MySQL/MariaDB。
2. 在宝塔「数据库」页新建数据库与用户，记下库名、用户名、密码。例如：

```sql
-- 也可以手动在 MySQL 执行
CREATE DATABASE license DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER 'license'@'127.0.0.1' IDENTIFIED BY 'StrongPass123';
GRANT ALL PRIVILEGES ON license.* TO 'license'@'127.0.0.1';
FLUSH PRIVILEGES;
```

3. 下载发布包 `license-activation-system-v1.3.zip`，上传到服务器，例如 `/root/`。

### 1.2 执行

```bash
unzip -q license-activation-system-v1.3.zip -d /root/license-src

bash /root/license-src/deploy/bt-onekey.sh \
  --zip=/root/license-activation-system-v1.3.zip \
  --domain=license.example.com \
  --site=/www/wwwroot/license \
  --db=license \
  --db-user=license \
  --db-pass='StrongPass123' \
  --admin=admin \
  --admin-pass='AdminPass123'
```

脚本会自动完成：解压上传 → 设置属主与权限 → 静默安装（建表 + 生成密钥 A + 生成初始 API 密钥） → 写 Nginx 站点配置（含重写与 `Authorization` 头透传） → 检查并重载 Nginx。

完成后输出：

```
访问地址   : http://license.example.com/
后台账号   : admin
后台密码   : AdminPass123
API 密钥   : /www/wwwroot/license/install/initial_api_key.txt
开放 API   : http://license.example.com/openapi/ping
```

常用可选参数：`--port=8080`、`--php=/www/server/php/82/bin/php`、`--fpm=unix:/www/server/php/82/var/run/php-fpm.sock`、`--prefix=lc_`。完整参数用 `--help` 查看。

> 脚本会拒绝覆盖已有安装（存在 `install/install.lock` 时停止），不会删除任何已有密钥。域名填 `_` 表示默认站点；若宝塔已有 80 端口默认站点，请换端口或先停用原默认站点。

---

## 2. 方式 B：宝塔手动部署（上传即用）

1. 宝塔 → 网站 → 添加站点：域名填你的域名或服务器 IP，根目录 `/www/wwwroot/license`，数据库选「创建」并记下账号密码。
2. 上传 `license-activation-system-v1.3.zip` 到 `/www/wwwroot/license/`，解压。
3. 设置网站目录为 `/www/wwwroot/license/public`（宝塔：站点 → 设置 → 基本配置 → 网站目录）。
4. 设置运行目录为 `/public`（如果面板有该选项）。
5. 确认 Nginx 配置包含（宝塔伪静态里填也可）：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/run/php-fpm/www.sock;   # 按实际 PHP 版本调整
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME /www/wwwroot/license/public$fastcgi_script_name;
    include fastcgi_params;
    fastcgi_param HTTP_AUTHORIZATION $http_authorization;   # 开放 API Bearer 鉴权需要
}
```

6. 权限：

```bash
chown -R www:www /www/wwwroot/license
chmod -R u=rwX,go=rX /www/wwwroot/license
chmod 0700 /www/wwwroot/license/keys
mkdir -p /www/wwwroot/license/storage/cache /www/wwwroot/license/storage/sessions
chown -R www:www /www/wwwroot/license/storage
```

7. 浏览器访问 `http://你的域名/`，进入安装向导：填数据库信息 → 提交 → 设置管理员账号密码。安装程序会自动建表、生成全局密钥对 A 和初始 API 密钥。

8. 检查 `/www/wwwroot/license/install/initial_api_key.txt`，里面就是你的第一把 API Key。

---

## 3. 方式 C：命令行静默安装（已有 Web 站点时）

适合已配好 Web 站点，只想初始化程序：

```bash
cd /www/wwwroot/license
php install/cli_install.php \
  --host=127.0.0.1 --port=3306 \
  --db=license --db-user=license --db-pass='StrongPass123' \
  --prefix=lc_ --admin=admin --admin-pass='AdminPass123'
```

成功输出 `{"ok":true,...}`。已安装时会拒绝重复执行；如确需重装，先手动删除 `install/install.lock` 并清空数据库。

---

## 4. 本地快速体验（可选）

```bash
cd license-activation-system
php -S 127.0.0.1:8080 -t public
```

浏览器打开 `http://127.0.0.1:8080/`。SQLite 不支持，仍需本机 MySQL/MariaDB。

---

## 5. 安装后必做的验证

### 5.1 页面

- `http://域名/` → 登录页
- 登录后侧栏出现：概览 / 软件管理 / 卡密管理 / 请求日志 / 数据统计 / API 密钥 / 地址转换

### 5.2 API

```bash
KEY=$(tail -n 1 install/initial_api_key.txt)

# 开放 API（需要密钥）
curl -s -X POST http://域名/openapi/ping \
  -H "Authorization: Bearer $KEY" -H 'Content-Type: application/json' -d '{}'

# 客户端 API（不需要密钥）
curl -s -X POST http://域名/api/ping -H 'Content-Type: application/json' -d '{}'
```

两者都应返回 `"code":0`。冒烟测试：

```bash
API_KEY="$KEY" BASE=http://域名 bash tools/api_smoke_test.sh
```

### 5.3 一次完整业务闭环

1. 后台「软件管理」新建软件 → 自动生成独立密钥对 B。
2. 后台「卡密管理」生成一张时效卡或计次卡 → 复制 K 码。
3. 软件内置全局公钥 A，解 K 码得到 `u`（接口）、`b`（公钥 B）、`s`（软件标识）。
4. 软件带卡号、标识、设备号、nonce、timestamp 调 `/api/status`。
5. 计次场景改调 `/api/ticket` 获取票据；AI 调用失败可凭票据调 `/api/refund` 退回一次。
6. 用公钥 B 解密业务包，读取 `config.ai` / `config.text` / `config.json` / `config.script`。

详细协议见 [D4-API 接口文档](./D4-API-接口文档.md)，AI 调用见 [D5-开放 API 与 AI 集成文档](./D5-开放API与AI集成文档.md)。

---

## 6. 常见问题

| 现象 | 原因与处理 |
|---|---|
| 打开首页是目录列表或 404 | 网站根目录没指向 `public/` |
| `/api/xxx` 返回登录页 | Nginx 缺少 `try_files` 重写，或没走 `index.php` |
| 开放 API 返回 `4001 缺少 API 密钥` | 请求头未透传：Nginx 缺少 `fastcgi_param HTTP_AUTHORIZATION $http_authorization;`；临时可用 `?api_key=xxx` |
| 接口返回 `9001` | 看 `storage/logs/`；常见是 `keys/` 权限不对，php-fpm 读不到私钥 |
| 解密失败 | K 码里的公钥 B 与服务器当前私钥 B 不配套；轮换密钥后必须重新发卡 |
| 卡密列表「所属软件」为空 | 软件被删；用「刷新软件关联」查看孤儿卡数量 |
| 后台 API 密钥页空白 | 用超级管理员登录；普通管理员无权限 |
| 计次卡调 `/api/activate` 返回 2003 | 正常，`activate` 只用于时效卡；计次用 `/api/ticket` |
| 想把域名/服务器换掉 | 后台「地址转换」或 `openapi/card/convert-kcode`，旧 K 码需逐个转换 |

---

## 7. 安全提示

本项目按「内部测试」定位实现：HTTP 明文、无限流、无登录失败锁定、API Key 是唯一写凭据。上线公网前建议：

1. 前置 HTTPS 并强制跳转。
2. 按 scope 给 AI 最小权限的 API Key，不要下发全权限密钥。
3. 对 `/openapi/*` 增加 IP 白名单。
4. 定期用 `keys/revoke` 轮换密钥，并备份 `keys/` 与数据库。
