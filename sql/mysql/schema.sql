-- ticketsystem (the help desk): its tables on mysql.
-- Generated from ticketsystemSchema::definition() by bin/php/install.php --write-schema-files with the kernel's
-- schema handler; bin/php/install.php creates the same tables in a running installation.

CREATE TABLE ticketsystem_ticket (
  id int(11) NOT NULL AUTO_INCREMENT,
  contentobject_id int(11) NOT NULL DEFAULT '0',
  node_id int(11) NOT NULL DEFAULT '0',
  number varchar(32) NOT NULL DEFAULT '',
  prefix varchar(16) NOT NULL DEFAULT '',
  year int(11) NOT NULL DEFAULT '0',
  seq int(11) NOT NULL DEFAULT '0',
  account_key varchar(64) NOT NULL DEFAULT '',
  opener_id int(11) NOT NULL DEFAULT '0',
  subject varchar(255) NOT NULL DEFAULT '',
  status varchar(32) NOT NULL DEFAULT '',
  category varchar(64) NOT NULL DEFAULT '',
  priority varchar(32) NOT NULL DEFAULT '',
  subscription_id int(11) NOT NULL DEFAULT '0',
  post_count int(11) NOT NULL DEFAULT '0',
  last_post int(11) NOT NULL DEFAULT '0',
  last_post_by int(11) NOT NULL DEFAULT '0',
  last_post_staff int(11) NOT NULL DEFAULT '0',
  created int(11) NOT NULL DEFAULT '0',
  modified int(11) NOT NULL DEFAULT '0',
  resolved int(11) NOT NULL DEFAULT '0',
  closed int(11) NOT NULL DEFAULT '0',
  priority_rank int(11) NOT NULL DEFAULT '0',
  assignee_id int(11) NOT NULL DEFAULT '0',
  assigned int(11) NOT NULL DEFAULT '0',
  source varchar(16) NOT NULL DEFAULT 'web',
  external_id varchar(64) NOT NULL DEFAULT '',
  sla_level varchar(32) NOT NULL DEFAULT '',
  response_due int(11) NOT NULL DEFAULT '0',
  resolve_due int(11) NOT NULL DEFAULT '0',
  responded int(11) NOT NULL DEFAULT '0',
  paused_since int(11) NOT NULL DEFAULT '0',
  escalation int(11) NOT NULL DEFAULT '0',
  escalated int(11) NOT NULL DEFAULT '0',
  breached int(11) NOT NULL DEFAULT '0',
  rating int(11) NOT NULL DEFAULT '0',
  rating_comment varchar(1000) NOT NULL DEFAULT '',
  rated int(11) NOT NULL DEFAULT '0',
  note_count int(11) NOT NULL DEFAULT '0',
  attachment_count int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY ( id ),
  UNIQUE KEY ticketsystem_ticket_number ( number ),
  UNIQUE KEY ticketsystem_ticket_object ( contentobject_id ),
  KEY ticketsystem_ticket_account ( account_key, status ),
  KEY ticketsystem_ticket_opener ( opener_id ),
  KEY ticketsystem_ticket_status ( status, modified ),
  KEY ticketsystem_ticket_assignee ( assignee_id, status ),
  KEY ticketsystem_ticket_escal ( escalation, status )
) ENGINE=InnoDB;

CREATE TABLE ticketsystem_counter (
  prefix varchar(16) NOT NULL DEFAULT '',
  year int(11) NOT NULL DEFAULT '0',
  last_value int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY ( prefix, year )
) ENGINE=InnoDB;

CREATE TABLE ticketsystem_note (
  id int(11) NOT NULL AUTO_INCREMENT,
  ticket_id int(11) NOT NULL DEFAULT '0',
  author_id int(11) NOT NULL DEFAULT '0',
  body longtext DEFAULT NULL,
  created int(11) NOT NULL DEFAULT '0',
  attachment_count int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY ( id ),
  KEY ticketsystem_note_ticket ( ticket_id )
) ENGINE=InnoDB;

CREATE TABLE ticketsystem_event (
  id int(11) NOT NULL AUTO_INCREMENT,
  ticket_id int(11) NOT NULL DEFAULT '0',
  actor_id int(11) NOT NULL DEFAULT '0',
  kind varchar(32) NOT NULL DEFAULT '',
  old_value varchar(255) NOT NULL DEFAULT '',
  new_value varchar(255) NOT NULL DEFAULT '',
  created int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY ( id ),
  KEY ticketsystem_event_ticket ( ticket_id, created ),
  KEY ticketsystem_event_kind ( kind, created )
) ENGINE=InnoDB;

CREATE TABLE ticketsystem_canned (
  id int(11) NOT NULL AUTO_INCREMENT,
  title varchar(200) NOT NULL DEFAULT '',
  body longtext DEFAULT NULL,
  category varchar(64) NOT NULL DEFAULT '',
  set_status varchar(32) NOT NULL DEFAULT '',
  set_priority varchar(32) NOT NULL DEFAULT '',
  assign_self int(11) NOT NULL DEFAULT '0',
  owner_id int(11) NOT NULL DEFAULT '0',
  created_by int(11) NOT NULL DEFAULT '0',
  created int(11) NOT NULL DEFAULT '0',
  modified int(11) NOT NULL DEFAULT '0',
  use_count int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY ( id ),
  KEY ticketsystem_canned_owner ( owner_id )
) ENGINE=InnoDB;

CREATE TABLE ticketsystem_attachment (
  id int(11) NOT NULL AUTO_INCREMENT,
  ticket_id int(11) NOT NULL DEFAULT '0',
  post_id int(11) NOT NULL DEFAULT '0',
  note_id int(11) NOT NULL DEFAULT '0',
  uploader_id int(11) NOT NULL DEFAULT '0',
  name varchar(255) NOT NULL DEFAULT '',
  mime varchar(128) NOT NULL DEFAULT '',
  filesize int(11) NOT NULL DEFAULT '0',
  sha256 varchar(64) NOT NULL DEFAULT '',
  storage_key varchar(128) NOT NULL DEFAULT '',
  created int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY ( id ),
  KEY ticketsystem_attach_ticket ( ticket_id )
) ENGINE=InnoDB;

CREATE TABLE ticketsystem_post (
  id int(11) NOT NULL AUTO_INCREMENT,
  ticket_id int(11) NOT NULL DEFAULT '0',
  object_id int(11) NOT NULL DEFAULT '0',
  author_id int(11) NOT NULL DEFAULT '0',
  is_staff int(11) NOT NULL DEFAULT '0',
  created int(11) NOT NULL DEFAULT '0',
  body longtext DEFAULT NULL,
  PRIMARY KEY ( id ),
  KEY ticketsystem_post_ticket ( ticket_id ),
  UNIQUE KEY ticketsystem_post_object ( object_id )
) ENGINE=InnoDB;

CREATE TABLE ticketsystem_mailin (
  id int(11) NOT NULL AUTO_INCREMENT,
  message_key varchar(64) NOT NULL DEFAULT '',
  ticket_id int(11) NOT NULL DEFAULT '0',
  user_id int(11) NOT NULL DEFAULT '0',
  result varchar(32) NOT NULL DEFAULT '',
  created int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY ( id ),
  UNIQUE KEY ticketsystem_mailin_key ( message_key )
) ENGINE=InnoDB;

CREATE TABLE ticketsystem_import (
  id int(11) NOT NULL AUTO_INCREMENT,
  source varchar(16) NOT NULL DEFAULT '',
  kind varchar(16) NOT NULL DEFAULT '',
  external_id varchar(64) NOT NULL DEFAULT '',
  local_id int(11) NOT NULL DEFAULT '0',
  created int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY ( id ),
  UNIQUE KEY ticketsystem_import_ext ( source, kind, external_id )
) ENGINE=InnoDB;

