-- insert new config values
ALTER TABLE `#__jem_events` MODIFY `author_ip` varchar(39);
ALTER TABLE `#__jem_venues` MODIFY `author_ip` varchar(39);
ALTER TABLE `#__jem_events` ADD `requestanswer` TINYINT(1) NOT NULL DEFAULT '0' AFTER `waitinglist`;
