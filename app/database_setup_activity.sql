

DROP TABLE IF EXISTS users;
CREATE TABLE users (
	id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,

	username VARCHAR(25) NOT NULL,
	real_name VARCHAR(100) NOT NULL,
	
	email varchar(45) NOT NULL,
	title varchar(100) DEFAULT NULL,
	
	created DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
	modified DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',

	PRIMARY KEY(id)
);

DROP TABLE IF EXISTS vehicles;
CREATE TABLE vehicles (
	id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
	
	class VARCHAR(100) DEFAULT "car", /* values: 'car', 'boat', 'tricycle' */
	
	name VARCHAR(1024) DEFAULT "Default Vehicle Name",

	PRIMARY KEY(id)
)


/* Activity base model */
DROP TABLE IF EXISTS activities;
CREATE TABLE activities (
	id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
	
	parent_activity_id MEDIUMINT UNSIGNED DEFAULT NULL,
	
	title VARCHAR(1024) DEFAULT "Default Title",
	description TEXT DEFAULT NULL,
	
	last_change DATETIME DEFAULT NULL,
	last_change_user_id MEDIUMINT UNSIGNED DEFAULT NULL,
	
	PRIMARY KEY(id)
);


/* Alpha Activity */
DROP TABLE IF EXISTS alpha_activities;
CREATE TABLE alpha_activities (
	id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
	activity_id MEDIUMINT UNSIGNED DEFAULT NULL,

	alpha_field_one VARCHAR(100) DEFAULT "Alpha field one default",
	alpha_field_two VARCHAR(100) DEFAULT "Alpha field two default",
	
	some_detail_id MEDIUMINT UNSIGNED DEFAULT NULL,
	
	
	PRIMARY KEY(id)
);


/* Beta Activity */
DROP TABLE IF EXISTS beta_activities;
CREATE TABLE beta_activities (
	id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
	activity_id MEDIUMINT UNSIGNED DEFAULT NULL,
	
	beta_field_1 VARCHAR(100) DEFAULT "Beta field 1 default",
	beta_field_2 VARCHAR(100) DEFAULT "Beta field 2 default",
	
	PRIMARY KEY(id)
);


/* Beta Activity */
DROP TABLE IF EXISTS some_details;
CREATE TABLE some_details (
	id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,

	detail_field VARCHAR(100) DEFAULT "Some detail field default",
	
	PRIMARY KEY(id)
);


/* Beta Activity */
DROP TABLE IF EXISTS some_specific_details;
CREATE TABLE some_specific_details (
	id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
	some_detail_id MEDIUMINT UNSIGNED DEFAULT NULL,

	specific_detail_field VARCHAR(100) DEFAULT "Some specific detail field default",
	
	PRIMARY KEY(id)
);


