
CREATE DATABASE IF NOT EXISTS `cosplay_sites` CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
USE `cosplay_sites`;

-- Users
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `passwordHash` VARCHAR(255) DEFAULT NULL,
  `accountType` VARCHAR(20) NOT NULL DEFAULT 'user',
  `createdAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_users_username` (`username`)
) ENGINE=InnoDB;

-- Shops
CREATE TABLE IF NOT EXISTS `shops` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ownerUserId` INT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `address` TEXT DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `externalUrl` VARCHAR(1024) DEFAULT NULL,
  `createdAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_shops_owner` (`ownerUserId`),
  CONSTRAINT `fk_shops_owner` FOREIGN KEY (`ownerUserId`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Shop members (co-owners and cooperators)
CREATE TABLE IF NOT EXISTS `shop_members` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `shopId` INT UNSIGNED NOT NULL,
  `userId` INT UNSIGNED NOT NULL,
  `role` VARCHAR(50) DEFAULT 'cooperator',
  `createdAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_shop_user` (`shopId`,`userId`),
  CONSTRAINT `fk_shop_members_shop` FOREIGN KEY (`shopId`) REFERENCES `shops` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_shop_members_user` FOREIGN KEY (`userId`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Items
CREATE TABLE IF NOT EXISTS `items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `shopId` INT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `series` VARCHAR(255) DEFAULT NULL,
  `brand` VARCHAR(255) DEFAULT NULL,
  `size` VARCHAR(50) DEFAULT NULL,
  `priceTest` BIGINT DEFAULT NULL,
  `priceShoot` BIGINT DEFAULT NULL,
  `priceFestival` BIGINT DEFAULT NULL,
  `sourceLink` VARCHAR(1024) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `createdAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_items_shop` (`shopId`),
  CONSTRAINT `fk_items_shop` FOREIGN KEY (`shopId`) REFERENCES `shops` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Item images
CREATE TABLE IF NOT EXISTS `item_images` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `itemId` INT UNSIGNED NOT NULL,
  `url` VARCHAR(1024) NOT NULL,
  `isPrimary` TINYINT(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_item_images_item` (`itemId`),
  CONSTRAINT `fk_item_images_item` FOREIGN KEY (`itemId`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Categories
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `createdAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_categories_slug` (`slug`)
) ENGINE=InnoDB;

-- Item <-> Category mapping
CREATE TABLE IF NOT EXISTS `item_categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `itemId` INT UNSIGNED NOT NULL,
  `categoryId` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_item_categories_item` (`itemId`),
  KEY `idx_item_categories_category` (`categoryId`),
  CONSTRAINT `fk_item_categories_item` FOREIGN KEY (`itemId`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_item_categories_category` FOREIGN KEY (`categoryId`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Item availability mapping: which shops carry a given item (and optional note)
CREATE TABLE IF NOT EXISTS `item_availability` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `itemId` INT UNSIGNED NOT NULL,
  `shopId` INT UNSIGNED NOT NULL,
  `available` TINYINT(1) NOT NULL DEFAULT 1,
  `note` VARCHAR(512) DEFAULT NULL,
  `createdAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_item_avail_item` (`itemId`),
  KEY `idx_item_avail_shop` (`shopId`),
  CONSTRAINT `fk_item_availability_item` FOREIGN KEY (`itemId`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_item_availability_shop` FOREIGN KEY (`shopId`) REFERENCES `shops` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Feedbacks
CREATE TABLE IF NOT EXISTS `feedbacks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `itemId` INT UNSIGNED NOT NULL,
  `userId` INT UNSIGNED DEFAULT NULL,
  `rating` TINYINT DEFAULT NULL,
  `message` TEXT DEFAULT NULL,
  `createdAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_feedback_item` (`itemId`),
  CONSTRAINT `fk_feedbacks_item` FOREIGN KEY (`itemId`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_feedbacks_user` FOREIGN KEY (`userId`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Feedback images
CREATE TABLE IF NOT EXISTS `feedback_images` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `feedbackId` INT UNSIGNED NOT NULL,
  `url` VARCHAR(1024) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_feedback_images_feedback` (`feedbackId`),
  CONSTRAINT `fk_feedback_images_feedback` FOREIGN KEY (`feedbackId`) REFERENCES `feedbacks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Track active user sessions/devices. Each login creates a row; oldest rows
-- are removed by application logic when limiting the number of active devices.
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `userId` INT UNSIGNED NOT NULL,
  `sessionId` VARCHAR(128) NOT NULL,
  `ip` VARCHAR(64) DEFAULT NULL,
  `userAgent` VARCHAR(512) DEFAULT NULL,
  `createdAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_user_sessions_session` (`sessionId`),
  KEY `idx_us_user` (`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Temporary sessions table: stores short-lived sessions for users who did not
-- opt into 'remember this device'. These rows may be pruned by background
-- jobs or left to expire depending on application needs.
CREATE TABLE IF NOT EXISTS `temp_user_sessions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `userId` INT UNSIGNED NOT NULL,
  `sessionId` VARCHAR(128) NOT NULL,
  `ip` VARCHAR(64) DEFAULT NULL,
  `userAgent` VARCHAR(512) DEFAULT NULL,
  `createdAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_temp_user_sessions_session` (`sessionId`),
  KEY `idx_tus_user` (`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- End of schema

-- Application-level owner account (do not confuse with a database user):
-- This creates a site owner account `T1WIN` (accountType='shop') and a default shop owned by that account.
-- These INSERTs are idempotent (use IGNORE or check existence). Adjust email/password after import.

INSERT IGNORE INTO `users` (`username`, `email`, `passwordHash`, `accountType`, `createdAt`)
VALUES ('T1WIN', 'owner@example.com', '', 'shop', NOW());

SET @ownerId := (SELECT `id` FROM `users` WHERE `username` = 'T1WIN' LIMIT 1);

INSERT IGNORE INTO `shops` (`ownerUserId`, `name`, `address`, `phone`, `description`, `externalUrl`, `createdAt`)
VALUES (IFNULL(@ownerId, 0), 'Site Owner Shop', 'Owner Address', '', 'Default shop for site owner', NULL, NOW());

-- If you need a database-level user with privileges (CREATE USER / GRANT), run those commands manually as a DBA.
