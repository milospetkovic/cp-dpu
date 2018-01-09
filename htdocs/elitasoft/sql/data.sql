CREATE TABLE IF NOT EXISTS `llx_elita_dpu` (
	`rowid` INT(11) NOT NULL AUTO_INCREMENT,
	`tms` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
	`date` DATE NOT NULL,
	`fk_statut` INT(11) NOT NULL,
	`user_id` INT(11) NOT NULL,
	`model_pdf` varchar(60) NULL DEFAULT NULL,
	PRIMARY KEY (`rowid`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS `llx_elita_dpudet` (
	`rowid` INT(11) NOT NULL AUTO_INCREMENT,
	`fk_dpu` INT(11) NOT NULL,
	`fk_product` INT(11) NOT NULL,
	`transfered_qty` double,
	`supplied_qty` double,
	`sold_qty` double,
	PRIMARY KEY (`rowid`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB;

ALTER TABLE `llx_elita_dpudet` ADD COLUMN `unit_price` double NOT NULL AFTER `fk_product`;

ALTER TABLE `llx_elita_dpu` ADD COLUMN `pk_nr` varchar(20) NULL DEFAULT NULL AFTER `fk_statut`;

ALTER TABLE `llx_commandedet` ADD COLUMN `prev_qty` double NULL DEFAULT NULL AFTER `qty`;

ALTER TABLE `llx_commandedet` DROP COLUMN `prev_qty`;

ALTER TABLE `llx_commandedet` ADD COLUMN `transfered_qty` double NULL DEFAULT NULL AFTER `qty`;

ALTER TABLE `llx_elita_dpu` ADD UNIQUE KEY (date);

ALTER TABLE `llx_elita_dpudet` ADD COLUMN `remained_qty` double AFTER `sold_qty`;

