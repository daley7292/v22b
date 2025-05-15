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

ALTER TABLE v2_convert MODIFY COLUMN duration_unit ENUM('day','month','year','quarter','half_year','onetime');

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
