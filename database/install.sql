SET NAMES utf8mb4;
SET time_zone = '+08:00';
SET foreign_key_checks = 0;

-- ----------------------------
-- Table structure for failed_jobs
-- ----------------------------
DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for v2_commission_log
-- ----------------------------
DROP TABLE IF EXISTS `v2_commission_log`;
CREATE TABLE `v2_commission_log` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `invite_user_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `trade_no` char(36) NOT NULL,
  `note` varchar(255) DEFAULT NULL COMMENT '佣金备注',
  `order_amount` int(11) NOT NULL,
  `get_amount` int(11) NOT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `invite_user_id` (`invite_user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for v2_convert
-- ----------------------------
DROP TABLE IF EXISTS `v2_convert`;
CREATE TABLE `v2_convert` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL COMMENT '兑换码名称',
  `plan_id` int(11) DEFAULT NULL COMMENT '兑换套餐ID',
  `ordinal_number` int(11) NOT NULL DEFAULT 0 COMMENT '兑换次数，0为不限制',
  `duration_unit` enum('month','quarter','half_year','year') NOT NULL COMMENT '存储时间单位（月、季度、半年、年）',
  `duration_value` int(11) NOT NULL DEFAULT 0 COMMENT '兑换几个季度',
  `redeem_code` varchar(255) NOT NULL COMMENT '兑换码',
  `email` varchar(255) DEFAULT NULL COMMENT '绑定上级邀请邮箱',
  `is_invitation` tinyint(4) DEFAULT NULL COMMENT '0不是 1 是，用来判断是否要强制输入邀请码',
  `created_at` int(11) DEFAULT NULL COMMENT 'Creation Time',
  `updated_at` int(11) DEFAULT NULL COMMENT 'Update Time',
  `deleted_at` int(11) DEFAULT NULL COMMENT 'Deletion Time',
  `end_at` int(11) DEFAULT NULL COMMENT '兑换码到期时间',
  PRIMARY KEY (`id`),
  KEY `duration_unit` (`duration_unit`),
  KEY `redeem_code` (`redeem_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for v2_coupon
-- ----------------------------
DROP TABLE IF EXISTS `v2_coupon`;
CREATE TABLE `v2_coupon` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` tinyint(1) NOT NULL,
  `value` int(11) NOT NULL,
  `show` tinyint(1) NOT NULL DEFAULT 0,
  `limit_use` int(11) DEFAULT NULL,
  `limit_use_with_user` int(11) DEFAULT NULL,
  `limit_plan_ids` varchar(255) DEFAULT NULL,
  `limit_period` varchar(255) DEFAULT NULL,
  `started_at` int(11) NOT NULL,
  `ended_at` int(11) NOT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  `limit_inviter_ids` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for v2_invite_code
-- ----------------------------
DROP TABLE IF EXISTS `v2_invite_code`;
CREATE TABLE `v2_invite_code` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `code` char(32) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  `pv` int(11) NOT NULL DEFAULT 0,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for v2_knowledge
-- ----------------------------
DROP TABLE IF EXISTS `v2_knowledge`;
CREATE TABLE `v2_knowledge` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `language` char(5) NOT NULL COMMENT '語言',
  `category` varchar(255) NOT NULL COMMENT '分類名',
  `title` varchar(255) NOT NULL COMMENT '標題',
  `body` text NOT NULL COMMENT '內容',
  `sort` int(11) DEFAULT NULL COMMENT '排序',
  `show` tinyint(1) NOT NULL DEFAULT 0 COMMENT '顯示',
  `created_at` int(11) NOT NULL COMMENT '創建時間',
  `updated_at` int(11) NOT NULL COMMENT '更新時間',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='知識庫';

-- ----------------------------
-- Table structure for v2_log
-- ----------------------------
DROP TABLE IF EXISTS `v2_log`;
CREATE TABLE `v2_log` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(512) NOT NULL,
  `level` varchar(11) DEFAULT NULL,
  `host` varchar(255) DEFAULT NULL,
  `uri` varchar(255) NOT NULL,
  `method` varchar(11) NOT NULL,
  `data` text DEFAULT NULL,
  `ip` varchar(128) DEFAULT NULL,
  `context` text DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for v2_mail_log
-- ----------------------------
DROP TABLE IF EXISTS `v2_mail_log`;
CREATE TABLE `v2_mail_log` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` varchar(64) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `template_name` varchar(255) NOT NULL,
  `error` text DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for v2_notice
-- ----------------------------
DROP TABLE IF EXISTS `v2_notice`;
CREATE TABLE `v2_notice` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `show` tinyint(1) NOT NULL DEFAULT 0,
  `windows_type` int(11) DEFAULT NULL COMMENT '弹窗类型:1=使用文档,2=购买订阅,3=节点状态,4=我的订单,5=我的邀请,6=我的工单,7=个人中心',
  `img_url` varchar(255) DEFAULT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for v2_order
-- ----------------------------
DROP TABLE IF EXISTS `v2_order`;
CREATE TABLE `v2_order` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `invite_user_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `coupon_id` int(11) DEFAULT NULL,
  `payment_id` int(11) DEFAULT NULL,
  `type` int(11) NOT NULL COMMENT '1新购2续费3升级4试用  5兑换码  6推广增送',
  `redeem_code` varchar(255) DEFAULT NULL,
  `period` varchar(255) NOT NULL,
  `trade_no` varchar(36) NOT NULL,
  `gift_days` double DEFAULT NULL COMMENT '赠送天数',
  `callback_no` varchar(255) DEFAULT NULL,
  `total_amount` int(11) NOT NULL,
  `handling_amount` int(11) DEFAULT NULL,
  `discount_amount` int(11) DEFAULT NULL,
  `surplus_amount` int(11) DEFAULT NULL COMMENT '剩余价值',
  `refund_amount` int(11) DEFAULT NULL COMMENT '退款金额',
  `balance_amount` int(11) DEFAULT NULL COMMENT '使用余额',
  `surplus_order_ids` text DEFAULT NULL COMMENT '折抵订单',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0待支付1开通中2已取消3已完成4已折抵',
  `commission_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0待确认1发放中2有效3无效',
  `commission_balance` int(11) NOT NULL DEFAULT 0,
  `actual_commission_balance` int(11) DEFAULT NULL COMMENT '实际支付佣金',
  `paid_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  `invited_user_id` int(11) DEFAULT NULL COMMENT '邀请奖励来源用户ID',
  PRIMARY KEY (`id`),
  UNIQUE KEY `trade_no` (`trade_no`),
  KEY `user_id` (`user_id`),
  KEY `plan_id` (`plan_id`),
  KEY `payment_id` (`payment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for v2_payment
-- ----------------------------
DROP TABLE IF EXISTS `v2_payment`;
CREATE TABLE `v2_payment` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` char(32) NOT NULL,
  `payment` varchar(16) NOT NULL,
  `name` varchar(255) NOT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `config` text NOT NULL,
  `notify_domain` varchar(128) DEFAULT NULL,
  `handling_fee_fixed` int(11) DEFAULT NULL,
  `handling_fee_percent` decimal(5,2) DEFAULT NULL,
  `enable` tinyint(1) NOT NULL DEFAULT 0,
  `sort` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uuid` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for v2_plan
-- ----------------------------
DROP TABLE IF EXISTS `v2_plan`;
CREATE TABLE `v2_plan` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `transfer_enable` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `speed_limit` int(11) DEFAULT 0,
  `show` tinyint(1) DEFAULT 0,
  `sort` int(11) DEFAULT 0,
  `renew` tinyint(1) NOT NULL DEFAULT 1,
  `content` text DEFAULT NULL,
  `month_price` int(11) DEFAULT NULL,
  `quarter_price` int(11) DEFAULT NULL,
  `half_year_price` int(11) DEFAULT NULL,
  `year_price` int(11) DEFAULT NULL,
  `two_year_price` int(11) DEFAULT NULL,
  `three_year_price` int(11) DEFAULT NULL,
  `onetime_price` int(11) DEFAULT NULL,
  `reset_price` int(11) DEFAULT NULL,
  `reset_traffic_method` tinyint(1) DEFAULT NULL,
  `capacity_limit` int(11) DEFAULT NULL,
  `daily_unit_price` int(11) DEFAULT NULL,
  `transfer_unit_price` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for v2_rule
-- ----------------------------
DROP TABLE IF EXISTS `v2_rule`;
CREATE TABLE `v2_rule` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `sort` int(11) DEFAULT 0 COMMENT '排序',
  `port` int(11) DEFAULT NULL COMMENT '端口',
  `domain` varchar(255) DEFAULT NULL COMMENT '要替换的域名',
  `ua` varchar(255) NOT NULL COMMENT 'ua匹配信息',
  `server_arr` varchar(255) DEFAULT NULL COMMENT '用逗号分割分组id',
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for v2_server_group
-- ----------------------------
DROP TABLE IF EXISTS `v2_server_group`;
CREATE TABLE `v2_server_group` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for v2_server_hysteria
-- ----------------------------
DROP TABLE IF EXISTS `v2_server_hysteria`;
CREATE TABLE `v2_server_hysteria` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` varchar(255) NOT NULL,
  `route_id` varchar(255) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `host` varchar(255) NOT NULL,
  `port` varchar(11) NOT NULL,
  `server_port` int(11) NOT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `rate` varchar(11) NOT NULL,
  `show` tinyint(1) NOT NULL DEFAULT 0,
  `sort` int(11) DEFAULT NULL,
  `up_mbps` int(11) NOT NULL,
  `down_mbps` int(11) NOT NULL,
  `server_name` varchar(64) DEFAULT NULL,
  `insecure` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for v2_server_route
-- ----------------------------
DROP TABLE IF EXISTS `v2_server_route`;
CREATE TABLE `v2_server_route` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `remarks` varchar(255) NOT NULL,
  `match` text NOT NULL,
  `action` varchar(11) NOT NULL,
  `action_value` varchar(255) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for v2_server_shadowsocks
-- ----------------------------
DROP TABLE IF EXISTS `v2_server_shadowsocks`;
CREATE TABLE `v2_server_shadowsocks` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` varchar(255) NOT NULL,
  `route_id` varchar(255) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `rate` varchar(11) NOT NULL,
  `host` varchar(255) NOT NULL,
  `port` varchar(11) NOT NULL,
  `server_port` int(11) NOT NULL,
  `cipher` varchar(255) NOT NULL,
  `obfs` char(11) DEFAULT NULL,
  `obfs_settings` varchar(255) DEFAULT NULL,
  `show` tinyint(1) NOT NULL DEFAULT 0,
  `sort` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for v2_server_trojan
-- ----------------------------
DROP TABLE IF EXISTS `v2_server_trojan`;
CREATE TABLE `v2_server_trojan` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` varchar(255) NOT NULL,
  `route_id` varchar(255) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `rate` varchar(11) NOT NULL,
  `host` varchar(255) NOT NULL,
  `port` varchar(11) NOT NULL,
  `server_port` int(11) NOT NULL,
  `allow_insecure` tinyint(1) NOT NULL DEFAULT 0,
  `server_name` varchar(255) DEFAULT NULL,
  `show` tinyint(1) NOT NULL DEFAULT 0,
  `sort` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for v2_server_vless
-- ----------------------------
DROP TABLE IF EXISTS `v2_server_vless`;
CREATE TABLE `v2_server_vless` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` text NOT NULL,
  `route_id` text DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `host` varchar(255) NOT NULL,
  `port` int(11) NOT NULL,
  `server_port` int(11) NOT NULL,
  `tls` tinyint(1) NOT NULL,
  `tls_settings` text DEFAULT NULL,
  `flow` varchar(64) DEFAULT NULL,
  `network` varchar(11) NOT NULL,
  `network_settings` text DEFAULT NULL,
  `tags` text DEFAULT NULL,
  `rate` varchar(11) NOT NULL,
  `show` tinyint(1) NOT NULL DEFAULT 0,
  `sort` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for v2_server_vmess
-- ----------------------------
DROP TABLE IF EXISTS `v2_server_vmess`;
CREATE TABLE `v2_server_vmess` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` varchar(255) NOT NULL,
  `route_id` varchar(255) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `host` varchar(255) NOT NULL,
  `port` varchar(11) NOT NULL,
  `server_port` int(11) NOT NULL,
  `tls` tinyint(4) NOT NULL DEFAULT 0,
  `tags` varchar(255) DEFAULT NULL,
  `rate` varchar(11) NOT NULL,
  `network` varchar(11) NOT NULL,
  `rules` text DEFAULT NULL,
  `networkSettings` text DEFAULT NULL,
  `tlsSettings` text DEFAULT NULL,
  `ruleSettings` text DEFAULT NULL,
  `dnsSettings` text DEFAULT NULL,
  `show` tinyint(1) NOT NULL DEFAULT 0,
  `sort` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for v2_stat
-- ----------------------------
DROP TABLE IF EXISTS `v2_stat`;
CREATE TABLE `v2_stat` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `record_at` int(11) NOT NULL,
  `record_type` char(1) NOT NULL,
  `order_count` int(11) NOT NULL COMMENT '订单数量',
  `order_total` int(11) NOT NULL COMMENT '订单合计',
  `commission_count` int(11) NOT NULL,
  `commission_total` int(11) NOT NULL COMMENT '佣金合计',
  `paid_count` int(11) NOT NULL,
  `paid_total` int(11) NOT NULL,
  `register_count` int(11) NOT NULL,
  `invite_count` int(11) NOT NULL,
  `transfer_used_total` varchar(32) NOT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `record_at` (`record_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for v2_stat_server
-- ----------------------------
DROP TABLE IF EXISTS `v2_stat_server`;
CREATE TABLE `v2_stat_server` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `server_id` int(11) NOT NULL COMMENT '节点id',
  `server_type` char(11) NOT NULL COMMENT '节点类型',
  `u` bigint(20) NOT NULL,
  `d` bigint(20) NOT NULL,
  `record_type` char(1) NOT NULL COMMENT 'd day m month',
  `record_at` int(11) NOT NULL COMMENT '记录时间',
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `server_id_server_type_record_at` (`server_id`, `server_type`, `record_at`),
  KEY `record_at` (`record_at`),
  KEY `server_id` (`server_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for v2_stat_user
-- ----------------------------
DROP TABLE IF EXISTS `v2_stat_user`;
CREATE TABLE `v2_stat_user` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `server_rate` decimal(10,2) NOT NULL,
  `u` bigint(20) NOT NULL,
  `d` bigint(20) NOT NULL,
  `record_type` char(2) NOT NULL,
  `record_at` int(11) NOT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `record_at` (`record_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for v2_ticket
-- ----------------------------
DROP TABLE IF EXISTS `v2_ticket`;
CREATE TABLE `v2_ticket` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `level` tinyint(1) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0:已开启 1:已关闭',
  `reply_status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0:待回复 1:已回复',
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for v2_ticket_message
-- ----------------------------
DROP TABLE IF EXISTS `v2_ticket_message`;
CREATE TABLE `v2_ticket_message` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ticket_id` (`ticket_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for v2_user
-- ----------------------------
DROP TABLE IF EXISTS `v2_user`;
CREATE TABLE `v2_user` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `invite_user_id` int(11) DEFAULT NULL,
  `telegram_id` bigint(20) DEFAULT NULL,
  `email` varchar(64) NOT NULL,
  `password` varchar(64) NOT NULL,
  `password_algo` char(10) DEFAULT NULL,
  `password_salt` char(10) DEFAULT NULL,
  `balance` int(11) NOT NULL DEFAULT 0,
  `discount` int(11) DEFAULT NULL,
  `commission_type` tinyint(4) NOT NULL DEFAULT 0 COMMENT '0: system 1: period 2: onetime',
  `commission_rate` int(11) DEFAULT NULL,
  `commission_balance` int(11) NOT NULL DEFAULT 0,
  `t` int(11) NOT NULL DEFAULT 0,
  `u` bigint(20) NOT NULL DEFAULT 0,
  `d` bigint(20) NOT NULL DEFAULT 0,
  `transfer_enable` bigint(20) NOT NULL DEFAULT 0,
  `banned` tinyint(1) NOT NULL DEFAULT 0,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `last_login_at` int(11) DEFAULT NULL,
  `is_staff` tinyint(1) NOT NULL DEFAULT 0,
  `last_login_ip` varchar(255) DEFAULT NULL,
  `uuid` varchar(36) NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  `plan_id` int(11) DEFAULT NULL,
  `speed_limit` int(11) DEFAULT NULL,
  `remind_expire` tinyint(4) DEFAULT 1,
  `remind_traffic` tinyint(4) DEFAULT 1,
  `token` char(32) NOT NULL,
  `expired_at` bigint(20) DEFAULT 0,
  `remarks` text DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  `delete_reason` varchar(255) DEFAULT NULL,
  `deleted_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `invite_user_id` (`invite_user_id`),
  KEY `telegram_id` (`telegram_id`),
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for v2_user_del
-- ----------------------------
DROP TABLE IF EXISTS `v2_user_del`;
CREATE TABLE `v2_user_del` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `invite_user_id` int(11) DEFAULT NULL,
  `telegram_id` bigint(20) DEFAULT NULL,
  `email` varchar(64) NOT NULL,
  `password` varchar(64) NOT NULL,
  `password_algo` char(10) DEFAULT NULL,
  `password_salt` char(10) DEFAULT NULL,
  `balance` int(11) NOT NULL DEFAULT 0,
  `discount` int(11) DEFAULT NULL,
  `commission_type` tinyint(4) NOT NULL DEFAULT 0 COMMENT '0: system 1: period 2: onetime',
  `commission_rate` int(11) DEFAULT NULL,
  `commission_balance` int(11) NOT NULL DEFAULT 0,
  `t` int(11) NOT NULL DEFAULT 0,
  `u` bigint(20) NOT NULL DEFAULT 0,
  `d` bigint(20) NOT NULL DEFAULT 0,
  `transfer_enable` bigint(20) NOT NULL DEFAULT 0,
  `banned` tinyint(1) NOT NULL DEFAULT 0,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `last_login_at` int(11) DEFAULT NULL,
  `is_staff` tinyint(1) NOT NULL DEFAULT 0,
  `last_login_ip` varchar(255) DEFAULT NULL,
  `uuid` varchar(36) NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  `plan_id` int(11) DEFAULT NULL,
  `speed_limit` int(11) DEFAULT NULL,
  `remind_expire` tinyint(4) DEFAULT 1,
  `remind_traffic` tinyint(4) DEFAULT 1,
  `token` char(32) NOT NULL,
  `expired_at` bigint(20) DEFAULT 0,
  `remarks` text DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  `delete_reason` varchar(255) DEFAULT NULL,
  `deleted_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `invite_user_id` (`invite_user_id`),
  KEY `telegram_id` (`telegram_id`),
  KEY `deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
