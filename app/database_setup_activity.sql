
/* Changes TODO: move to a cream_database_setup file */
DROP TABLE IF EXISTS changes;
CREATE TABLE changes (
	id                          MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,

	/* Tracing */
	user_id                     VARCHAR(32)                 DEFAULT NULL,
	time                        BIGINT                      DEFAULT NULL,

	/* Entity Id I.e. Project.id=13 */
	entity_id_model             VARCHAR(128)                DEFAULT NULL,
	entity_id_primary_key       VARCHAR(64)                 DEFAULT NULL,
	entity_id_primary_key_value VARCHAR(64)                 DEFAULT NULL,

	/* Modify & Create fields */
	type                        VARCHAR(16)                 DEFAULT NULL, /* modify or create */
	field_or_relation           VARCHAR(512)                DEFAULT NULL,
	value                       TEXT                        DEFAULT NULL, /* value VARCHAR(16384) DEFAULT NULL, */

	KEY (id),
	PRIMARY KEY (entity_id_model, entity_id_primary_key_value, id)
);

/* Index TODO: move to a cream_database_setup file */
DROP TABLE IF EXISTS indices;
CREATE TABLE indices (
	id                MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
	entity_model_name VARCHAR(1024)               DEFAULT '',
	entity_model_id   MEDIUMINT UNSIGNED          DEFAULT NULL,
	dummy             BOOLEAN                     DEFAULT TRUE,

	/* Model */
	model_name        VARCHAR(128)                DEFAULT NULL,
	conditions        VARCHAR(1024)               DEFAULT NULL,

	PRIMARY KEY (id)
);

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


