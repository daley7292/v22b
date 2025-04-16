-- Adminer 4.7.7 MySQL dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

SET NAMES utf8mb4;

DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE `failed_jobs` (
                               `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                               `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
                               `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
                               `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
                               `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
                               `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                               PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `v2_commission_log`;
CREATE TABLE `v2_commission_log` (
                                     `id` int(11) NOT NULL AUTO_INCREMENT,
                                     `invite_user_id` int(11) NOT NULL,
                                     `user_id` int(11) NOT NULL,
                                     `trade_no` char(36) NOT NULL,
                                     `order_amount` int(11) NOT NULL,
                                     `get_amount` int(11) NOT NULL,
                                     `created_at` int(11) NOT NULL,
                                     `updated_at` int(11) NOT NULL,
                                     PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


DROP TABLE IF EXISTS `v2_coupon`;
CREATE TABLE `v2_coupon` (
                             `id` int(11) NOT NULL AUTO_INCREMENT,
                             `code` varchar(255) NOT NULL,
                             `name` varchar(255) CHARACTER SET utf8mb4 NOT NULL,
                             `type` tinyint(1) NOT NULL,
                             `value` int(11) NOT NULL,
                             `show` tinyint(1) NOT NULL DEFAULT '0',
                             `limit_use` int(11) DEFAULT NULL,
                             `limit_use_with_user` int(11) DEFAULT NULL,
                             `limit_plan_ids` varchar(255) DEFAULT NULL,
                             `limit_period` varchar(255) DEFAULT NULL,
                             `started_at` int(11) NOT NULL,
                             `ended_at` int(11) NOT NULL,
                             `created_at` int(11) NOT NULL,
                             `updated_at` int(11) NOT NULL,
                             PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `v2_invite_code`;
CREATE TABLE `v2_invite_code` (
                                  `id` int(11) NOT NULL AUTO_INCREMENT,
                                  `user_id` int(11) NOT NULL,
                                  `code` char(32) NOT NULL,
                                  `status` tinyint(1) NOT NULL DEFAULT '0',
                                  `pv` int(11) NOT NULL DEFAULT '0',
                                  `created_at` int(11) NOT NULL,
                                  `updated_at` int(11) NOT NULL,
                                  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `v2_knowledge`;
CREATE TABLE `v2_knowledge` (
                                `id` int(11) NOT NULL AUTO_INCREMENT,
                                `language` char(5) NOT NULL COMMENT '語言',
                                `category` varchar(255) NOT NULL COMMENT '分類名',
                                `title` varchar(255) NOT NULL COMMENT '標題',
                                `body` text NOT NULL COMMENT '內容',
                                `sort` int(11) DEFAULT NULL COMMENT '排序',
                                `show` tinyint(1) NOT NULL DEFAULT '0' COMMENT '顯示',
                                `created_at` int(11) NOT NULL COMMENT '創建時間',
                                `updated_at` int(11) NOT NULL COMMENT '更新時間',
                                PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='知識庫';


DROP TABLE IF EXISTS `v2_log`;
CREATE TABLE `v2_log` (
                          `id` int(11) NOT NULL AUTO_INCREMENT,
                          `title` varchar(255) NOT NULL,
                          `level` varchar(11) DEFAULT NULL,
                          `host` varchar(255) DEFAULT NULL,
                          `uri` varchar(255) NOT NULL,
                          `method` varchar(11) NOT NULL,
                          `data` text,
                          `ip` varchar(128) DEFAULT NULL,
                          `context` text,
                          `created_at` int(11) NOT NULL,
                          `updated_at` int(11) NOT NULL,
                          PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


DROP TABLE IF EXISTS `v2_mail_log`;
CREATE TABLE `v2_mail_log` (
                               `id` int(11) NOT NULL AUTO_INCREMENT,
                               `email` varchar(64) NOT NULL,
                               `subject` varchar(255) NOT NULL,
                               `template_name` varchar(255) NOT NULL,
                               `error` text,
                               `created_at` int(11) NOT NULL,
                               `updated_at` int(11) NOT NULL,
                               PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


DROP TABLE IF EXISTS `v2_notice`;
CREATE TABLE `v2_notice` (
                             `id` int(11) NOT NULL AUTO_INCREMENT,
                             `title` varchar(255) NOT NULL,
                             `content` text NOT NULL,
                             `show` tinyint(1) NOT NULL DEFAULT '0',
                             `img_url` varchar(255) DEFAULT NULL,
                             `tags` varchar(255) DEFAULT NULL,
                             `created_at` int(11) NOT NULL,
                             `updated_at` int(11) NOT NULL,
                             PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `v2_order`;
CREATE TABLE `v2_order` (
                            `id` int(11) NOT NULL AUTO_INCREMENT,
                            `invite_user_id` int(11) DEFAULT NULL,
                            `user_id` int(11) NOT NULL,
                            `plan_id` int(11) NOT NULL,
                            `coupon_id` int(11) DEFAULT NULL,
                            `payment_id` int(11) DEFAULT NULL,
                            `type` int(11) NOT NULL COMMENT '1新购2续费3升级',
                            `period` varchar(255) NOT NULL,
                            `trade_no` varchar(36) NOT NULL,
                            `callback_no` varchar(255) DEFAULT NULL,
                            `total_amount` int(11) NOT NULL,
                            `handling_amount` int(11) DEFAULT NULL,
                            `discount_amount` int(11) DEFAULT NULL,
                            `surplus_amount` int(11) DEFAULT NULL COMMENT '剩余价值',
                            `refund_amount` int(11) DEFAULT NULL COMMENT '退款金额',
                            `balance_amount` int(11) DEFAULT NULL COMMENT '使用余额',
                            `surplus_order_ids` text COMMENT '折抵订单',
                            `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0待支付1开通中2已取消3已完成4已折抵',
                            `commission_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0待确认1发放中2有效3无效',
                            `commission_balance` int(11) NOT NULL DEFAULT '0',
                            `actual_commission_balance` int(11) DEFAULT NULL COMMENT '实际支付佣金',
                            `paid_at` int(11) DEFAULT NULL,
                            `created_at` int(11) NOT NULL,
                            `updated_at` int(11) NOT NULL,
                            PRIMARY KEY (`id`),
                            UNIQUE KEY `trade_no` (`trade_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `v2_payment`;
CREATE TABLE `v2_payment` (
                              `id` int(11) NOT NULL AUTO_INCREMENT,
                              `uuid` char(32) NOT NULL,
                              `payment` varchar(16) NOT NULL,
                              `name` varchar(255) NOT NULL,
                              `icon` varchar(255) DEFAULT NULL,
                              `config` text NOT NULL,
                              `notify_domain` varchar(128) DEFAULT NULL,
                              `handling_fee_fixed` int(11) DEFAULT NULL,
                              `handling_fee_percent` decimal(5,2) DEFAULT NULL,
                              `enable` tinyint(1) NOT NULL DEFAULT '0',
                              `sort` int(11) DEFAULT NULL,
                              `created_at` int(11) NOT NULL,
                              `updated_at` int(11) NOT NULL,
                              PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


DROP TABLE IF EXISTS `v2_plan`;
CREATE TABLE `v2_plan` (
                           `id` int(11) NOT NULL AUTO_INCREMENT,
                           `group_id` int(11) NOT NULL,
                           `transfer_enable` int(11) NOT NULL,
                           `name` varchar(255) NOT NULL,
                           `speed_limit` int(11) DEFAULT NULL,
                           `show` tinyint(1) NOT NULL DEFAULT '0',
                           `sort` int(11) DEFAULT NULL,
                           `renew` tinyint(1) NOT NULL DEFAULT '1',
                           `content` text,
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
                           `created_at` int(11) NOT NULL,
                           `updated_at` int(11) NOT NULL,
                           PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


DROP TABLE IF EXISTS `v2_server_group`;
CREATE TABLE `v2_server_group` (
                                   `id` int(11) NOT NULL AUTO_INCREMENT,
                                   `name` varchar(255) NOT NULL,
                                   `created_at` int(11) NOT NULL,
                                   `updated_at` int(11) NOT NULL,
                                   PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `v2_server_hysteria`;
CREATE TABLE `v2_server_hysteria` (
                                      `id` int(11) NOT NULL AUTO_INCREMENT,
                                      `group_id` varchar(255) NOT NULL,
                                      `route_id` varchar(255) DEFAULT NULL,
                                      `name` varchar(255) NOT NULL,
                                      `parent_id` int(11) DEFAULT NULL,
                                      `host` varchar(255) NOT NULL,
                                      `port` varchar(11) NOT NULL,
                                      `server_port` int(11) NOT NULL,
                                      `tags` varchar(255) DEFAULT NULL,
                                      `rate` varchar(11) NOT NULL,
                                      `show` tinyint(1) NOT NULL DEFAULT '0',
                                      `sort` int(11) DEFAULT NULL,
                                      `up_mbps` int(11) NOT NULL,
                                      `down_mbps` int(11) NOT NULL,
                                      `server_name` varchar(64) DEFAULT NULL,
                                      `insecure` tinyint(1) NOT NULL DEFAULT '0',
                                      `created_at` int(11) NOT NULL,
                                      `updated_at` int(11) NOT NULL,
                                      PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


DROP TABLE IF EXISTS `v2_server_route`;
CREATE TABLE `v2_server_route` (
                                   `id` int(11) NOT NULL AUTO_INCREMENT,
                                   `remarks` varchar(255) NOT NULL,
                                   `match` text NOT NULL,
                                   `action` varchar(11) NOT NULL,
                                   `action_value` varchar(255) DEFAULT NULL,
                                   `created_at` int(11) NOT NULL,
                                   `updated_at` int(11) NOT NULL,
                                   PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


DROP TABLE IF EXISTS `v2_server_shadowsocks`;
CREATE TABLE `v2_server_shadowsocks` (
                                         `id` int(11) NOT NULL AUTO_INCREMENT,
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
                                         `show` tinyint(4) NOT NULL DEFAULT '0',
                                         `sort` int(11) DEFAULT NULL,
                                         `created_at` int(11) NOT NULL,
                                         `updated_at` int(11) NOT NULL,
                                         PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


DROP TABLE IF EXISTS `v2_server_trojan`;
CREATE TABLE `v2_server_trojan` (
                                    `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '节点ID',
                                    `group_id` varchar(255) NOT NULL COMMENT '节点组',
                                    `route_id` varchar(255) DEFAULT NULL,
                                    `parent_id` int(11) DEFAULT NULL COMMENT '父节点',
                                    `tags` varchar(255) DEFAULT NULL COMMENT '节点标签',
                                    `name` varchar(255) NOT NULL COMMENT '节点名称',
                                    `rate` varchar(11) NOT NULL COMMENT '倍率',
                                    `host` varchar(255) NOT NULL COMMENT '主机名',
                                    `port` varchar(11) NOT NULL COMMENT '连接端口',
                                    `server_port` int(11) NOT NULL COMMENT '服务端口',
                                    `allow_insecure` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否允许不安全',
                                    `server_name` varchar(255) DEFAULT NULL,
                                    `show` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否显示',
                                    `sort` int(11) DEFAULT NULL,
                                    `created_at` int(11) NOT NULL,
                                    `updated_at` int(11) NOT NULL,
                                    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='trojan伺服器表';


DROP TABLE IF EXISTS `v2_server_vmess`;
CREATE TABLE `v2_server_vmess` (
                                   `id` int(11) NOT NULL AUTO_INCREMENT,
                                   `group_id` varchar(255) NOT NULL,
                                   `route_id` varchar(255) DEFAULT NULL,
                                   `name` varchar(255) NOT NULL,
                                   `parent_id` int(11) DEFAULT NULL,
                                   `host` varchar(255) NOT NULL,
                                   `port` varchar(11) NOT NULL,
                                   `server_port` int(11) NOT NULL,
                                   `tls` tinyint(4) NOT NULL DEFAULT '0',
                                   `tags` varchar(255) DEFAULT NULL,
                                   `rate` varchar(11) NOT NULL,
                                   `network` varchar(11) NOT NULL,
                                   `rules` text,
                                   `networkSettings` text,
                                   `tlsSettings` text,
                                   `ruleSettings` text,
                                   `dnsSettings` text,
                                   `show` tinyint(1) NOT NULL DEFAULT '0',
                                   `sort` int(11) DEFAULT NULL,
                                   `created_at` int(11) NOT NULL,
                                   `updated_at` int(11) NOT NULL,
                                   PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


DROP TABLE IF EXISTS `v2_stat`;
CREATE TABLE `v2_stat` (
                           `id` int(11) NOT NULL AUTO_INCREMENT,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='订单统计';


DROP TABLE IF EXISTS `v2_stat_server`;
CREATE TABLE `v2_stat_server` (
                                  `id` int(11) NOT NULL AUTO_INCREMENT,
                                  `server_id` int(11) NOT NULL COMMENT '节点id',
                                  `server_type` char(11) NOT NULL COMMENT '节点类型',
                                  `u` bigint(20) NOT NULL,
                                  `d` bigint(20) NOT NULL,
                                  `record_type` char(1) NOT NULL COMMENT 'd day m month',
                                  `record_at` int(11) NOT NULL COMMENT '记录时间',
                                  `created_at` int(11) NOT NULL,
                                  `updated_at` int(11) NOT NULL,
                                  PRIMARY KEY (`id`),
                                  UNIQUE KEY `server_id_server_type_record_at` (`server_id`,`server_type`,`record_at`),
                                  KEY `record_at` (`record_at`),
                                  KEY `server_id` (`server_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='节点数据统计';


DROP TABLE IF EXISTS `v2_stat_user`;
CREATE TABLE `v2_stat_user` (
                                `id` int(11) NOT NULL AUTO_INCREMENT,
                                `user_id` int(11) NOT NULL,
                                `server_rate` decimal(10,2) NOT NULL,
                                `u` bigint(20) NOT NULL,
                                `d` bigint(20) NOT NULL,
                                `record_type` char(2) NOT NULL,
                                `record_at` int(11) NOT NULL,
                                `created_at` int(11) NOT NULL,
                                `updated_at` int(11) NOT NULL,
                                PRIMARY KEY (`id`),
                                UNIQUE KEY `server_rate_user_id_record_at` (`server_rate`,`user_id`,`record_at`),
                                KEY `user_id` (`user_id`),
                                KEY `record_at` (`record_at`),
                                KEY `server_rate` (`server_rate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


DROP TABLE IF EXISTS `v2_ticket`;
CREATE TABLE `v2_ticket` (
                             `id` int(11) NOT NULL AUTO_INCREMENT,
                             `user_id` int(11) NOT NULL,
                             `subject` varchar(255) NOT NULL,
                             `level` tinyint(1) NOT NULL,
                             `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0:已开启 1:已关闭',
                             `reply_status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0:待回复 1:已回复',
                             `created_at` int(11) NOT NULL,
                             `updated_at` int(11) NOT NULL,
                             PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `v2_ticket_message`;
CREATE TABLE `v2_ticket_message` (
                                     `id` int(11) NOT NULL AUTO_INCREMENT,
                                     `user_id` int(11) NOT NULL,
                                     `ticket_id` int(11) NOT NULL,
                                     `message` text CHARACTER SET utf8mb4 NOT NULL,
                                     `created_at` int(11) NOT NULL,
                                     `updated_at` int(11) NOT NULL,
                                     PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `v2_user`;
CREATE TABLE `v2_user` (
                           `id` int(11) NOT NULL AUTO_INCREMENT,
                           `invite_user_id` int(11) DEFAULT NULL,
                           `telegram_id` bigint(20) DEFAULT NULL,
                           `email` varchar(64) NOT NULL,
                           `password` varchar(64) NOT NULL,
                           `password_algo` char(10) DEFAULT NULL,
                           `password_salt` char(10) DEFAULT NULL,
                           `balance` int(11) NOT NULL DEFAULT '0',
                           `discount` int(11) DEFAULT NULL,
                           `commission_type` tinyint(4) NOT NULL DEFAULT '0' COMMENT '0: system 1: period 2: onetime',
                           `commission_rate` int(11) DEFAULT NULL,
                           `commission_balance` int(11) NOT NULL DEFAULT '0',
                           `t` int(11) NOT NULL DEFAULT '0',
                           `u` bigint(20) NOT NULL DEFAULT '0',
                           `d` bigint(20) NOT NULL DEFAULT '0',
                           `transfer_enable` bigint(20) NOT NULL DEFAULT '0',
                           `banned` tinyint(1) NOT NULL DEFAULT '0',
                           `is_admin` tinyint(1) NOT NULL DEFAULT '0',
                           `last_login_at` int(11) DEFAULT NULL,
                           `is_staff` tinyint(1) NOT NULL DEFAULT '0',
                           `last_login_ip` int(11) DEFAULT NULL,
                           `uuid` varchar(36) NOT NULL,
                           `group_id` int(11) DEFAULT NULL,
                           `plan_id` int(11) DEFAULT NULL,
                           `speed_limit` int(11) DEFAULT NULL,
                           `remind_expire` tinyint(4) DEFAULT '1',
                           `remind_traffic` tinyint(4) DEFAULT '1',
                           `token` char(32) NOT NULL,
                           `expired_at` bigint(20) DEFAULT '0',
                           `remarks` text,
                           `created_at` int(11) NOT NULL,
                           `updated_at` int(11) NOT NULL,
                           PRIMARY KEY (`id`),
                           UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


-- 2023-05-23 17:01:12

---2025-04-15 17:01:12

ALTER TABLE `v2_commission_log` ADD COLUMN `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL DEFAULT NULL COMMENT '佣金备注' AFTER `trade_no`;

CREATE TABLE `v2_convert`  (
                               `id` int NOT NULL AUTO_INCREMENT,
                               `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL DEFAULT NULL COMMENT '兑换码名称',
                               `plan_id` int NULL DEFAULT NULL COMMENT '兑换套餐ID',
                               `ordinal_number` int NOT NULL DEFAULT 0 COMMENT '兑换次数，0为不限制',
                               `duration_unit` enum('month','quarter','half_year','year') CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL COMMENT '存储时间单位（月、季度、半年、年）',
                               `duration_value` int NOT NULL DEFAULT 0 COMMENT '兑换几个季度',
                               `redeem_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL COMMENT '兑换码',
                               `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL DEFAULT NULL COMMENT '绑定上级邀请邮箱',
                               `is_invitation` tinyint NULL DEFAULT NULL COMMENT '0不是 1 是，用来判断是否要强制输入邀请码',
                               `created_at` int NULL DEFAULT NULL COMMENT 'Creation Time',
                               `updated_at` int NULL DEFAULT NULL COMMENT 'Update Time',
                               `deleted_at` int NULL DEFAULT NULL COMMENT 'Deletion Time',
                               `end_at` int NULL DEFAULT NULL COMMENT '兑换码到期时间',
                               PRIMARY KEY (`id`, `duration_unit`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_uca1400_ai_ci ROW_FORMAT = DYNAMIC;

ALTER TABLE `v2_coupon` ADD COLUMN `limit_inviter_ids` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL AFTER `updated_at`;

ALTER TABLE `v2_log` MODIFY COLUMN `title` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL AFTER `id`;

ALTER TABLE `v2_notice` ADD COLUMN `windows_type` int NULL DEFAULT NULL COMMENT '弹窗类型:1=使用文档,2=购买订阅,3=节点状态,4=我的订单,5=我的邀请,6=我的工单,7=个人中心' AFTER `show`;

ALTER TABLE `v2_order` ADD COLUMN `redeem_code` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL AFTER `type`;

ALTER TABLE `v2_order` ADD COLUMN `gift_days` double NULL DEFAULT NULL COMMENT '赠送天数' AFTER `trade_no`;

ALTER TABLE `v2_order` ADD COLUMN `invited_user_id` int NULL DEFAULT NULL COMMENT '邀请奖励来源用户ID' AFTER `updated_at`;

ALTER TABLE `v2_order` MODIFY COLUMN `type` int NOT NULL COMMENT '1新购2续费3升级4试用  5兑换码  6推广增送' AFTER `payment_id`;

ALTER TABLE `v2_plan` ADD COLUMN `block_ipv4_cont` tinyint NULL DEFAULT 0 AFTER `updated_at`;

ALTER TABLE `v2_plan` ADD COLUMN `block_plant_cont` tinyint NULL DEFAULT 0 AFTER `block_ipv4_cont`;

ALTER TABLE `v2_plan` ADD COLUMN `ip_limit` int(11) UNSIGNED ZEROFILL NULL DEFAULT NULL AFTER `block_plant_cont`;

ALTER TABLE `v2_plan` MODIFY COLUMN `speed_limit` int NULL DEFAULT 0 AFTER `name`;

ALTER TABLE `v2_plan` MODIFY COLUMN `show` tinyint(1) NULL DEFAULT 0 AFTER `speed_limit`;

ALTER TABLE `v2_plan` MODIFY COLUMN `sort` int NULL DEFAULT 0 AFTER `show`;

CREATE TABLE `v2_rule`  (
                            `id` int NOT NULL AUTO_INCREMENT,
                            `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL DEFAULT NULL,
                            `sort` int NULL DEFAULT 0 COMMENT '排序',
                            `port` int NULL DEFAULT NULL COMMENT '端口',
                            `domain` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL DEFAULT NULL COMMENT '要替换的域名',
                            `ua` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL COMMENT 'ua匹配信息',
                            `server_arr` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL DEFAULT NULL COMMENT '用逗号分割分组id',
                            `created_at` int NOT NULL,
                            `updated_at` int NOT NULL,
                            PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_uca1400_ai_ci ROW_FORMAT = DYNAMIC;

ALTER TABLE `v2_server_shadowsocks` MODIFY COLUMN `show` tinyint(1) UNSIGNED ZEROFILL NULL DEFAULT 0 AFTER `obfs_settings`;

ALTER TABLE `v2_user` ADD COLUMN `ip_limit` int NULL DEFAULT NULL AFTER `d`;

ALTER TABLE `v2_user` ADD COLUMN `has_received_inviter_reward` tinyint(1) NULL DEFAULT 0 COMMENT '是否已获得该用户首次付费的邀请奖励' AFTER `updated_at`;

CREATE TABLE `v2_user_del`  (
                                `id` int NOT NULL AUTO_INCREMENT,
                                `invite_user_id` int NULL DEFAULT NULL,
                                `telegram_id` bigint NULL DEFAULT NULL,
                                `email` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
                                `password` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
                                `password_algo` char(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
                                `password_salt` char(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
                                `balance` int NOT NULL DEFAULT 0,
                                `discount` int NULL DEFAULT NULL,
                                `commission_type` tinyint NOT NULL DEFAULT 0 COMMENT '0: system 1: period 2: onetime',
                                `commission_rate` int NULL DEFAULT NULL,
                                `commission_balance` int NOT NULL DEFAULT 0,
                                `t` int NOT NULL DEFAULT 0,
                                `u` bigint NOT NULL DEFAULT 0,
                                `d` bigint NOT NULL DEFAULT 0,
                                `transfer_enable` bigint NOT NULL DEFAULT 0,
                                `banned` tinyint(1) NOT NULL DEFAULT 0,
                                `is_admin` tinyint(1) NOT NULL DEFAULT 0,
                                `last_login_at` int NULL DEFAULT NULL,
                                `is_staff` tinyint(1) NOT NULL DEFAULT 0,
                                `last_login_ip` int NULL DEFAULT NULL,
                                `uuid` varchar(36) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
                                `group_id` int NULL DEFAULT NULL,
                                `plan_id` int NULL DEFAULT NULL,
                                `speed_limit` int NULL DEFAULT NULL,
                                `remind_expire` tinyint NULL DEFAULT 1,
                                `remind_traffic` tinyint NULL DEFAULT 1,
                                `token` char(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
                                `expired_at` bigint NULL DEFAULT 0,
                                `remarks` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL,
                                `created_at` int NOT NULL,
                                `updated_at` int NOT NULL,
                                `delete_reason` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
                                `deleted_at` int NULL DEFAULT NULL,
                                PRIMARY KEY (`id`) USING BTREE,
                                UNIQUE INDEX `email`(`email` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci ROW_FORMAT = DYNAMIC;