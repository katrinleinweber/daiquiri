CREATE TABLE IF NOT EXISTS `Config_Entries` (
  `id` INTEGER PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(256) NOT NULL,
  `value` VARCHAR(256) NOT NULL
) ENGINE InnoDB COLLATE 'utf8_unicode_ci';

CREATE TABLE IF NOT EXISTS `Config_Messages` (
  `id` INTEGER PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(256) NOT NULL,
  `value` TEXT NOT NULL
) ENGINE InnoDB COLLATE 'utf8_unicode_ci';

CREATE TABLE IF NOT EXISTS `Config_Templates` (
  `id` INTEGER PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `template` VARCHAR(256) NOT NULL,
  `subject` VARCHAR(256) NOT NULL,
  `body` TEXT NOT NULL
) ENGINE InnoDB COLLATE 'utf8_unicode_ci';
