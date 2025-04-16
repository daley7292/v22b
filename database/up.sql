SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE `announcement`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT '' COMMENT 'Title',
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL COMMENT 'Content',
  `show` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Show',
  `pinned` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Pinned',
  `popup` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Popup',
  `created_at` datetime(3) NULL DEFAULT NULL COMMENT 'Create Time',
  `updated_at` datetime(3) NULL DEFAULT NULL COMMENT 'Update Time',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_uca1400_ai_ci ROW_FORMAT = DYNAMIC;

CREATE TABLE `application`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT '' COMMENT '应用名称',
  `subscribe_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT '' COMMENT '订阅类型',
  `icon` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT '' COMMENT '应用图标',
  `url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT '' COMMENT '应用地址',
  `platform` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT '' COMMENT '应用平台',
  `created_at` datetime(3) NULL DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime(3) NULL DEFAULT NULL COMMENT '更新时间',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL COMMENT '更新描述',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_uca1400_ai_ci ROW_FORMAT = DYNAMIC;

CREATE TABLE `application_config`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `domains` text CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL,
  `startup_picture` text CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL,
  `startup_picture_skip_time` bigint NOT NULL DEFAULT 0 COMMENT 'Startup Picture Skip Time',
  `encryption` tinyint(1) NOT NULL DEFAULT 0 COMMENT '启用加密',
  `created_at` datetime(3) NULL DEFAULT NULL COMMENT 'Create Time',
  `updated_at` datetime(3) NULL DEFAULT NULL COMMENT 'Update Time',
  `app_id` bigint NOT NULL DEFAULT 0 COMMENT 'App id',
  `encryption_key` text CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL COMMENT 'Encryption Key',
  `encryption_method` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL DEFAULT NULL COMMENT 'Encryption Method',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_uca1400_ai_ci ROW_FORMAT = DYNAMIC;

CREATE TABLE `application_version`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT '' COMMENT '应用地址',
  `version` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT '' COMMENT '应用版本',
  `platform` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT '' COMMENT '应用平台',
  `is_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT '默认版本',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL COMMENT '更新描述',
  `application_id` bigint NULL DEFAULT NULL COMMENT '所属应用',
  `created_at` datetime(3) NULL DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime(3) NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `fk_application_application_versions`(`application_id` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_uca1400_ai_ci ROW_FORMAT = DYNAMIC;

CREATE TABLE `coupon`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT '' COMMENT 'Coupon Name',
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT '' COMMENT 'Coupon Code',
  `count` bigint NOT NULL DEFAULT 0 COMMENT 'Count Limit',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Coupon Type: 1: Percentage 2: Fixed Amount',
  `discount` bigint NOT NULL DEFAULT 0 COMMENT 'Coupon Discount',
  `start_time` bigint NOT NULL DEFAULT 0 COMMENT 'Start Time',
  `expire_time` bigint NOT NULL DEFAULT 0 COMMENT 'Expire Time',
  `user_limit` bigint NOT NULL DEFAULT 0 COMMENT 'User Limit',
  `subscribe` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT '' COMMENT 'Subscribe Limit',
  `used_count` bigint NOT NULL DEFAULT 0 COMMENT 'Used Count',
  `enable` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Enable',
  `created_at` datetime(3) NULL DEFAULT NULL COMMENT 'Create Time',
  `updated_at` datetime(3) NULL DEFAULT NULL COMMENT 'Update Time',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uni_coupon_code`(`code` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_uca1400_ai_ci ROW_FORMAT = DYNAMIC;

CREATE TABLE `document`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT '' COMMENT 'Document Title',
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL COMMENT 'Document Content',
  `tags` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT '' COMMENT 'Document Tags',
  `show` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Show',
  `created_at` datetime(3) NULL DEFAULT NULL COMMENT 'Create Time',
  `updated_at` datetime(3) NULL DEFAULT NULL COMMENT 'Update Time',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_uca1400_ai_ci ROW_FORMAT = DYNAMIC;

CREATE TABLE `migrations`  (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = DYNAMIC;

CREATE TABLE `oauth_config`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `platform` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT '' COMMENT 'platform',
  `config` text CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL COMMENT 'OAuth Configuration',
  `redirect` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT '' COMMENT 'Redirect URL',
  `enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Is Enabled',
  `created_at` datetime(3) NULL DEFAULT NULL COMMENT 'Create Time',
  `updated_at` datetime(3) NULL DEFAULT NULL COMMENT 'Update Time',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uni_oauth_config_platform`(`platform` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_uca1400_ai_ci ROW_FORMAT = DYNAMIC;

CREATE TABLE `order`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` bigint NOT NULL DEFAULT 0 COMMENT 'User Id',
  `order_no` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT '' COMMENT 'Order No',
  `gift_days` int NOT NULL COMMENT '赠送天数',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Order Type: 1: Subscribe, 2: Renewal, 3: ResetTraffic, 4: Recharge',
  `quantity` bigint NOT NULL DEFAULT 1 COMMENT 'Quantity',
  `price` bigint NOT NULL DEFAULT 0 COMMENT 'Original price',
  `amount` bigint NOT NULL DEFAULT 0 COMMENT 'Order Amount',
  `discount` bigint NOT NULL DEFAULT 0 COMMENT 'Discount Amount',
  `coupon` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL DEFAULT NULL COMMENT 'Coupon',
  `coupon_discount` bigint NOT NULL DEFAULT 0 COMMENT 'Coupon Discount Amount',
  `method` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT '' COMMENT 'Payment Method',
  `fee_amount` bigint NOT NULL DEFAULT 0 COMMENT 'Fee Amount',
  `trade_no` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL DEFAULT NULL COMMENT 'Trade No',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Order Status: 1: Pending, 2: Paid, 3:Close, 4: Failed, 5:Finished',
  `subscribe_id` bigint NOT NULL DEFAULT 0 COMMENT 'Subscribe Id',
  `subscribe_token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL DEFAULT NULL COMMENT 'Renewal Subscribe Token',
  `is_new` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Is New Order',
  `created_at` datetime(3) NULL DEFAULT NULL COMMENT 'Create Time',
  `updated_at` datetime(3) NULL DEFAULT NULL COMMENT 'Update Time',
  `parent_id` bigint NULL DEFAULT NULL COMMENT 'Parent Order Id',
  `gift_amount` bigint NOT NULL DEFAULT 0 COMMENT 'User Gift Amount',
  `commission` bigint NOT NULL DEFAULT 0 COMMENT 'Order Commission',
  `redeem_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL DEFAULT NULL COMMENT '兑换码',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uni_order_order_no`(`order_no` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_uca1400_ai_ci ROW_FORMAT = DYNAMIC;

CREATE TABLE `payment`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT '' COMMENT 'Payment Name',
  `mark` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL COMMENT 'Payment Mark',
  `icon` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL DEFAULT '' COMMENT 'Payment Icon',
  `domain` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL DEFAULT '' COMMENT 'Notification Domain',
  `config` text CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL COMMENT 'Payment Configuration',
  `fee_mode` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Fee Mode: 0: No Fee 1: Percentage 2: Fixed Amount 3: Percentage + Fixed Amount',
  `fee_percent` bigint NULL DEFAULT 0 COMMENT 'Fee Percentage',
  `fee_amount` bigint NULL DEFAULT 0 COMMENT 'Fixed Fee Amount',
  `enable` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Is Enabled',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uni_payment_mark`(`mark` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_uca1400_ai_ci ROW_FORMAT = DYNAMIC;

CREATE TABLE `server`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT '' COMMENT 'Node Name',
  `server_addr` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT '' COMMENT 'Server Address',
  `enable_relay` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Relay Enabled',
  `relay_host` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL DEFAULT NULL COMMENT 'Relay Server Address',
  `relay_port` bigint NULL DEFAULT NULL COMMENT 'Relay Server Port',
  `speed_limit` bigint NOT NULL DEFAULT 0 COMMENT 'Speed Limit',
  `traffic_ratio` decimal(4, 2) NOT NULL DEFAULT 0.00 COMMENT 'Traffic Ratio',
  `group_id` bigint NULL DEFAULT NULL COMMENT 'Group ID',
  `protocol` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT '' COMMENT 'Protocol',
  `config` text CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL COMMENT 'Config',
  `enable` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Enabled',
  `sort` bigint NOT NULL DEFAULT 0 COMMENT 'Sort',
  `created_at` datetime(3) NULL DEFAULT NULL COMMENT 'Creation Time',
  `updated_at` datetime(3) NULL DEFAULT NULL COMMENT 'Update Time',
  `tags` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT '' COMMENT 'Tags',
  `country` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT '' COMMENT 'Country',
  `city` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT '' COMMENT 'City',
  `relay_mode` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT 'none' COMMENT 'Relay Mode',
  `relay_node` text CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL COMMENT 'Relay Node',
  `last_reported_at` datetime(3) NULL DEFAULT NULL COMMENT 'Last Reported Time',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_group_id`(`group_id` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_uca1400_ai_ci ROW_FORMAT = DYNAMIC;

CREATE TABLE `server_group`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT '' COMMENT 'Group Name',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL DEFAULT '' COMMENT 'Group Description',
  `created_at` datetime(3) NULL DEFAULT NULL COMMENT 'Creation Time',
  `updated_at` datetime(3) NULL DEFAULT NULL COMMENT 'Update Time',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_uca1400_ai_ci ROW_FORMAT = DYNAMIC;

CREATE TABLE `sms`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL,
  `platform` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL DEFAULT NULL,
  `area_code` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL DEFAULT NULL,
  `telephone` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL DEFAULT NULL,
  `status` tinyint(1) NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_uca1400_ai_ci ROW_FORMAT = DYNAMIC;

CREATE TABLE `subscribe`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT '' COMMENT 'Subscribe Name',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL COMMENT 'Subscribe Description',
  `unit_price` bigint NOT NULL DEFAULT 0 COMMENT 'Unit Price',
  `unit_time` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT '' COMMENT 'Unit Time',
  `discount` text CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL COMMENT 'Discount',
  `replacement` bigint NOT NULL DEFAULT 0 COMMENT 'Replacement',
  `inventory` bigint NOT NULL DEFAULT 0 COMMENT 'Inventory',
  `traffic` bigint NOT NULL DEFAULT 0 COMMENT 'Traffic',
  `speed_limit` bigint NOT NULL DEFAULT 0 COMMENT 'Speed Limit',
  `device_limit` bigint NOT NULL DEFAULT 0 COMMENT 'Device Limit',
  `quota` bigint NOT NULL DEFAULT 0 COMMENT 'Quota',
  `group_id` bigint NULL DEFAULT NULL COMMENT 'Group Id',
  `server_group` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL DEFAULT NULL COMMENT 'Server Group',
  `server` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL DEFAULT NULL COMMENT 'Server',
  `show` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Show portal page',
  `sell` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Sell',
  `deduction_ratio` bigint NULL DEFAULT 0 COMMENT 'Deduction Ratio',
  `purchase_with_discount` tinyint(1) NULL DEFAULT 0 COMMENT 'PurchaseWithDiscount',
  `reset_cycle` bigint NULL DEFAULT 0 COMMENT 'Reset Cycle: 0: No Reset, 1: 1st, 2: Monthly, 3: Yearly',
  `renewal_reset` tinyint(1) NULL DEFAULT 0 COMMENT 'Renew Reset',
  `sort` bigint NOT NULL DEFAULT 0 COMMENT 'Sort',
  `created_at` datetime(3) NULL DEFAULT NULL COMMENT 'Create Time',
  `updated_at` datetime(3) NULL DEFAULT NULL COMMENT 'Update Time',
  `allow_deduction` tinyint(1) NULL DEFAULT 1 COMMENT 'Allow deduction',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_uca1400_ai_ci ROW_FORMAT = DYNAMIC;

CREATE TABLE `subscribe_group`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT '' COMMENT 'Group Name',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL COMMENT 'Group Description',
  `created_at` datetime(3) NULL DEFAULT NULL COMMENT 'Create Time',
  `updated_at` datetime(3) NULL DEFAULT NULL COMMENT 'Update Time',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_uca1400_ai_ci ROW_FORMAT = DYNAMIC;

CREATE TABLE `subscribe_type`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT '' COMMENT '订阅类型',
  `mark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT '' COMMENT '订阅标识',
  `created_at` datetime(3) NULL DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime(3) NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_uca1400_ai_ci ROW_FORMAT = DYNAMIC;

CREATE TABLE `system`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `category` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT '' COMMENT 'Category',
  `key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT '' COMMENT 'Key Name',
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL COMMENT 'Key Value',
  `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT '' COMMENT 'Type',
  `desc` text CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL COMMENT 'Description',
  `created_at` datetime(3) NULL DEFAULT NULL COMMENT 'Creation Time',
  `updated_at` datetime(3) NULL DEFAULT NULL COMMENT 'Update Time',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uni_system_key`(`key` ASC) USING BTREE,
  INDEX `index_key`(`key` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_uca1400_ai_ci ROW_FORMAT = DYNAMIC;

CREATE TABLE `ticket`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT '' COMMENT 'Title',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL COMMENT 'Description',
  `user_id` bigint NOT NULL DEFAULT 0 COMMENT 'UserId',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Status',
  `created_at` datetime(3) NULL DEFAULT NULL COMMENT 'Create Time',
  `updated_at` datetime(3) NULL DEFAULT NULL COMMENT 'Update Time',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_uca1400_ai_ci ROW_FORMAT = DYNAMIC;

CREATE TABLE `ticket_follow`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `ticket_id` bigint NOT NULL DEFAULT 0 COMMENT 'TicketId',
  `from` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT '' COMMENT 'From',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Type: 1 text, 2 image',
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL COMMENT 'Content',
  `created_at` datetime(3) NULL DEFAULT NULL COMMENT 'Create Time',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_uca1400_ai_ci ROW_FORMAT = DYNAMIC;

CREATE TABLE `traffic_log`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `server_id` bigint NOT NULL COMMENT 'Server ID',
  `user_id` bigint NOT NULL COMMENT 'User ID',
  `subscribe_id` bigint NOT NULL COMMENT 'Subscription ID',
  `download` bigint NULL DEFAULT 0 COMMENT 'Download Traffic',
  `upload` bigint NULL DEFAULT 0 COMMENT 'Upload Traffic',
  `timestamp` datetime(3) NOT NULL DEFAULT current_timestamp(3) COMMENT 'Traffic Log Time',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_server_id`(`server_id` ASC) USING BTREE,
  INDEX `idx_user_id`(`user_id` ASC) USING BTREE,
  INDEX `idx_subscribe_id`(`subscribe_id` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_uca1400_ai_ci ROW_FORMAT = DYNAMIC;

CREATE TABLE `user`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `password` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL COMMENT 'User Password',
  `avatar` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL DEFAULT '' COMMENT 'User Avatar',
  `balance` bigint NULL DEFAULT 0 COMMENT 'User Balance',
  `telegram` bigint NULL DEFAULT NULL COMMENT 'Telegram Account',
  `refer_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL DEFAULT '' COMMENT 'Referral Code',
  `referer_id` bigint NULL DEFAULT NULL COMMENT 'Referrer ID',
  `commission` bigint NULL DEFAULT 0 COMMENT 'Commission',
  `enable` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Is Account Enabled',
  `is_admin` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Is Admin',
  `valid_email` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Is Email Verified',
  `enable_email_notify` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Enable Email Notifications',
  `enable_telegram_notify` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Enable Telegram Notifications',
  `enable_balance_notify` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Enable Balance Change Notifications',
  `enable_login_notify` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Enable Login Notifications',
  `enable_subscribe_notify` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Enable Subscription Notifications',
  `enable_trade_notify` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Enable Trade Notifications',
  `created_at` datetime(3) NULL DEFAULT NULL COMMENT 'Creation Time',
  `updated_at` datetime(3) NULL DEFAULT NULL COMMENT 'Update Time',
  `deleted_at` datetime(3) NULL DEFAULT NULL COMMENT 'Deletion Time',
  `is_del` bigint UNSIGNED NULL DEFAULT NULL COMMENT '1: Normal 0: Deleted',
  `gift_amount` bigint NULL DEFAULT 0 COMMENT 'User Gift Amount',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_referer`(`referer_id` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_uca1400_ai_ci ROW_FORMAT = DYNAMIC;

CREATE TABLE `user_auth_methods`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` bigint NOT NULL COMMENT 'User ID',
  `auth_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL COMMENT 'Auth Type 1: apple 2: google 3: github 4: facebook 5: telegram 6: email 7: phone',
  `auth_identifier` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL COMMENT 'Auth Identifier',
  `verified` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Is Verified',
  `created_at` datetime(3) NULL DEFAULT NULL COMMENT 'Creation Time',
  `updated_at` datetime(3) NULL DEFAULT NULL COMMENT 'Update Time',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_user_id`(`user_id` ASC) USING BTREE,
  INDEX `idx_auth_identifier`(`auth_identifier` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_uca1400_ai_ci ROW_FORMAT = DYNAMIC;

CREATE TABLE `user_balance_log`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` bigint NOT NULL COMMENT 'User ID',
  `amount` bigint NOT NULL COMMENT 'Amount',
  `type` tinyint(1) NOT NULL COMMENT 'Type: 1: Recharge 2: Withdraw 3: Payment 4: Refund 5: Reward',
  `order_id` bigint NULL DEFAULT NULL COMMENT 'Order ID',
  `balance` bigint NOT NULL COMMENT 'Balance',
  `created_at` datetime(3) NULL DEFAULT NULL COMMENT 'Creation Time',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_user_id`(`user_id` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_uca1400_ai_ci ROW_FORMAT = DYNAMIC;

CREATE TABLE `user_commission_log`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` bigint NOT NULL COMMENT 'User ID',
  `order_no` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL DEFAULT NULL COMMENT 'Order No.',
  `amount` bigint NOT NULL COMMENT 'Amount',
  `created_at` datetime(3) NULL DEFAULT NULL COMMENT 'Creation Time',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_user_id`(`user_id` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_uca1400_ai_ci ROW_FORMAT = DYNAMIC;

CREATE TABLE `user_device`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` bigint NOT NULL COMMENT 'User ID',
  `device_number` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL DEFAULT NULL COMMENT 'Device Number.',
  `online` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Online',
  `enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'EnableDeviceNumber',
  `last_online` datetime(3) NULL DEFAULT NULL COMMENT 'Last Online',
  `created_at` datetime(3) NULL DEFAULT NULL COMMENT 'Creation Time',
  `updated_at` datetime(3) NULL DEFAULT NULL COMMENT 'Update Time',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_user_id`(`user_id` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_uca1400_ai_ci ROW_FORMAT = DYNAMIC;

CREATE TABLE `user_gift_amount_log`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` bigint NOT NULL COMMENT 'User ID',
  `user_subscribe_id` bigint NULL DEFAULT NULL COMMENT 'Deduction User Subscribe ID',
  `order_no` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL DEFAULT NULL COMMENT 'Order No.',
  `type` tinyint(1) NOT NULL COMMENT 'Type: 1: Increase 2: Reduce',
  `amount` bigint NOT NULL COMMENT 'Amount',
  `balance` bigint NOT NULL COMMENT 'Balance',
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL DEFAULT '' COMMENT 'Remark',
  `created_at` datetime(3) NULL DEFAULT NULL COMMENT 'Creation Time',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_user_id`(`user_id` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_uca1400_ai_ci ROW_FORMAT = DYNAMIC;

CREATE TABLE `user_subscribe`  (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` bigint NOT NULL COMMENT 'User ID',
  `order_id` bigint NOT NULL COMMENT 'Order ID',
  `subscribe_id` bigint NOT NULL COMMENT 'Subscription ID',
  `start_time` datetime(3) NOT NULL DEFAULT current_timestamp(3) COMMENT 'Subscription Start Time',
  `expire_time` datetime(3) NULL DEFAULT NULL COMMENT 'Subscription Expire Time',
  `traffic` bigint NULL DEFAULT 0 COMMENT 'Traffic',
  `download` bigint NULL DEFAULT 0 COMMENT 'Download Traffic',
  `upload` bigint NULL DEFAULT 0 COMMENT 'Upload Traffic',
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL DEFAULT '' COMMENT 'Token',
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NULL DEFAULT '' COMMENT 'UUID',
  `status` tinyint(1) NULL DEFAULT 0 COMMENT 'Subscription Status: 0: Pending 1: Active 2: Finished 3: Expired 4: Deducted',
  `created_at` datetime(3) NULL DEFAULT NULL COMMENT 'Creation Time',
  `updated_at` datetime(3) NULL DEFAULT NULL COMMENT 'Update Time',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uni_user_subscribe_token`(`token` ASC) USING BTREE,
  UNIQUE INDEX `uni_user_subscribe_uuid`(`uuid` ASC) USING BTREE,
  INDEX `idx_user_id`(`user_id` ASC) USING BTREE,
  INDEX `idx_order_id`(`order_id` ASC) USING BTREE,
  INDEX `idx_subscribe_id`(`subscribe_id` ASC) USING BTREE,
  INDEX `idx_token`(`token` ASC) USING BTREE,
  INDEX `idx_uuid`(`uuid` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_uca1400_ai_ci ROW_FORMAT = DYNAMIC;

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

ALTER TABLE `application_version` ADD CONSTRAINT `fk_application_application_versions` FOREIGN KEY (`application_id`) REFERENCES `application` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE `user_auth_methods` ADD CONSTRAINT `fk_user_auth_methods` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE `user_device` ADD CONSTRAINT `fk_user_user_devices` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

SET FOREIGN_KEY_CHECKS=1;