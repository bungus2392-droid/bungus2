-- database.sql

-- قم بإنشاء قاعدة بيانات جديدة بالاسم الذي اخترته في ملف db.php
-- CREATE DATABASE quiz_app_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE quiz_app_db;

-- جدول لتخزين معلومات المستخدمين
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `access_code` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول لتخزين معلومات الاختبارات
CREATE TABLE `tests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `test_id` bigint(20) NOT NULL,
  `title` varchar(255) NOT NULL,
  `category` varchar(50) NOT NULL,
  `duration` int(11) NOT NULL,
  `max_attempts` int(11) NOT NULL,
  `prerequisite_percentage` int(11) DEFAULT 0,
  `questions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`questions`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `test_id` (`test_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول لتخزين درجات المستخدمين
CREATE TABLE `scores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `test_id` bigint(20) NOT NULL,
  `score` int(11) NOT NULL,
  `total` int(11) NOT NULL,
  `date` varchar(20) NOT NULL,
  `time` varchar(20) NOT NULL,
  `time_taken` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `test_id` (`test_id`),
  CONSTRAINT `scores_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `scores_ibfk_2` FOREIGN KEY (`test_id`) REFERENCES `tests` (`test_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول لتخزين المحاولات المتبقية للمستخدمين
CREATE TABLE `attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `test_id` bigint(20) NOT NULL,
  `attempts_left` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_test_unique` (`user_id`,`test_id`),
  KEY `test_id` (`test_id`),
  CONSTRAINT `attempts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attempts_ibfk_2` FOREIGN KEY (`test_id`) REFERENCES `tests` (`test_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
