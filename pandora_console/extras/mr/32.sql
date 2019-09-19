START TRANSACTION;

UPDATE `tlayout_data` SET `height` = 70 , `width` = 70 WHERE `height` = 0 && `width` = 0 && image NOT LIKE '%dot%' && ((`type` IN (0,5)) ||
(`type` = 10 && `image` IS NOT NULL && `image` != '' && `image` != 'none') ||
(`type` = 11 && `image` IS NOT NULL && `image` != '' && `image` != 'none' && `show_statistics` = 0));

ALTER TABLE `treport_content` ADD COLUMN `uncompressed_module` TINYINT DEFAULT '0';
ALTER TABLE `treport_content_template` ADD COLUMN `uncompressed_module` TINYINT DEFAULT '0';


COMMIT;