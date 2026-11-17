-- ============================================================
-- 软件激活与授权管理系统 数据库结构
-- 兼容 MySQL 5.6 / 5.7，InnoDB，utf8mb4
-- {{PREFIX}} 会在安装时替换为实际表前缀
-- ============================================================

CREATE TABLE `{{PREFIX}}admins` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(64) NOT NULL COMMENT '登录名',
  `password_hash` VARCHAR(255) NOT NULL COMMENT '密码哈希',
  `role` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1超管 2普管',
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1启用 0禁用',
  `last_login_at` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_login_ip` VARCHAR(45) DEFAULT NULL,
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员表';

CREATE TABLE `{{PREFIX}}softwares` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(128) NOT NULL COMMENT '软件名称',
  `identifier` VARCHAR(64) NOT NULL COMMENT '软件唯一标识',
  `default_mode` VARCHAR(16) NOT NULL DEFAULT 'time' COMMENT 'time时效 times次数',
  `default_days` INT UNSIGNED NOT NULL DEFAULT 30 COMMENT '时效默认天数',
  `default_times` INT UNSIGNED NOT NULL DEFAULT 100 COMMENT '次数默认次数',
  `default_config` TEXT NULL COMMENT '默认配置JSON（AI/text/json）',
  `b_key_id` VARCHAR(64) DEFAULT NULL COMMENT '本软件第二层密钥对标识',
  `public_b` TEXT NULL COMMENT '本软件公钥B（PEM），随K码下发',
  `private_b_path` VARCHAR(191) DEFAULT NULL COMMENT '本软件私钥B文件路径（Web根外）',
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1启用 0禁用',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_identifier` (`identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='软件表';

CREATE TABLE `{{PREFIX}}cards` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `card_id` VARCHAR(64) NOT NULL COMMENT '卡号',
  `software_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '所属软件ID',
  `mode` VARCHAR(16) NOT NULL DEFAULT 'time' COMMENT 'time时效 times次数',
  `expire_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '到期时间',
  `total_times` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '总次数',
  `remain` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '剩余次数',
  `used` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '已用次数',
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1启用 0禁用',
  `custom_config` TEXT NULL COMMENT '卡专属配置JSON',
  `device_id` VARCHAR(128) DEFAULT NULL COMMENT '首激活绑定设备',
  `activated_at` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_used_at` INT UNSIGNED NOT NULL DEFAULT 0,
  `remark` VARCHAR(255) DEFAULT NULL,
  `batch_no` VARCHAR(64) DEFAULT NULL,
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_card_id` (`card_id`),
  KEY `idx_software` (`software_id`),
  KEY `idx_status_mode` (`status`,`mode`),
  KEY `idx_batch` (`batch_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='卡密表';

CREATE TABLE `{{PREFIX}}tickets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket` CHAR(64) NOT NULL COMMENT '票据令牌',
  `card_id` VARCHAR(64) NOT NULL,
  `device_id` VARCHAR(128) DEFAULT NULL,
  `expire_at` INT UNSIGNED NOT NULL DEFAULT 0,
  `used_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '核销时间 0未用',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ticket` (`ticket`),
  KEY `idx_card` (`card_id`),
  KEY `idx_expire` (`expire_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='授权票据表';

CREATE TABLE `{{PREFIX}}request_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id` CHAR(32) NOT NULL,
  `card_id` VARCHAR(64) DEFAULT NULL,
  `software_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `software_identifier` VARCHAR(64) DEFAULT NULL,
  `device_id` VARCHAR(128) DEFAULT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `action` VARCHAR(32) NOT NULL COMMENT 'activate/ticket/convert_check',
  `result` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1成功 0失败',
  `error_code` INT UNSIGNED NOT NULL DEFAULT 0,
  `remain_before` INT UNSIGNED NOT NULL DEFAULT 0,
  `remain_after` INT UNSIGNED NOT NULL DEFAULT 0,
  `nonce` CHAR(64) DEFAULT NULL COMMENT '防重放串哈希',
  `detail` TEXT NULL,
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_nonce` (`nonce`),
  KEY `idx_card_time` (`card_id`,`created_at`),
  KEY `idx_time` (`created_at`),
  KEY `idx_software` (`software_id`),
  KEY `idx_result` (`result`,`error_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='请求日志表';

CREATE TABLE `{{PREFIX}}admin_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `admin_name` VARCHAR(64) DEFAULT NULL,
  `action` VARCHAR(64) NOT NULL,
  `target` VARCHAR(128) DEFAULT NULL,
  `detail` TEXT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_admin_time` (`admin_id`,`created_at`),
  KEY `idx_action` (`action`),
  KEY `idx_time` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理操作日志表';

CREATE TABLE `{{PREFIX}}settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `skey` VARCHAR(64) NOT NULL,
  `svalue` TEXT NULL,
  `remark` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_skey` (`skey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统设置表';
