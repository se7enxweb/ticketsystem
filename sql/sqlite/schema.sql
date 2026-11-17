-- ticketsystem (the help desk): its tables on sqlite.
-- Generated from ticketsystemSchema::definition() by bin/php/install.php --write-schema-files with the kernel's
-- schema handler; bin/php/install.php creates the same tables in a running installation.

CREATE TABLE ticketsystem_ticket (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  contentobject_id INTEGER(11) NOT NULL DEFAULT '0',
  node_id INTEGER(11) NOT NULL DEFAULT '0',
  number varchar(32) NOT NULL DEFAULT '',
  prefix varchar(16) NOT NULL DEFAULT '',
  year INTEGER(11) NOT NULL DEFAULT '0',
  seq INTEGER(11) NOT NULL DEFAULT '0',
  account_key varchar(64) NOT NULL DEFAULT '',
  opener_id INTEGER(11) NOT NULL DEFAULT '0',
  subject varchar(255) NOT NULL DEFAULT '',
  status varchar(32) NOT NULL DEFAULT '',
  category varchar(64) NOT NULL DEFAULT '',
  priority varchar(32) NOT NULL DEFAULT '',
  subscription_id INTEGER(11) NOT NULL DEFAULT '0',
  post_count INTEGER(11) NOT NULL DEFAULT '0',
  last_post INTEGER(11) NOT NULL DEFAULT '0',
  last_post_by INTEGER(11) NOT NULL DEFAULT '0',
  last_post_staff INTEGER(11) NOT NULL DEFAULT '0',
  created INTEGER(11) NOT NULL DEFAULT '0',
  modified INTEGER(11) NOT NULL DEFAULT '0',
  resolved INTEGER(11) NOT NULL DEFAULT '0',
  closed INTEGER(11) NOT NULL DEFAULT '0',
  priority_rank INTEGER(11) NOT NULL DEFAULT '0',
  assignee_id INTEGER(11) NOT NULL DEFAULT '0',
  assigned INTEGER(11) NOT NULL DEFAULT '0',
  source varchar(16) NOT NULL DEFAULT 'web',
  external_id varchar(64) NOT NULL DEFAULT '',
  sla_level varchar(32) NOT NULL DEFAULT '',
  response_due INTEGER(11) NOT NULL DEFAULT '0',
  resolve_due INTEGER(11) NOT NULL DEFAULT '0',
  responded INTEGER(11) NOT NULL DEFAULT '0',
  paused_since INTEGER(11) NOT NULL DEFAULT '0',
  escalation INTEGER(11) NOT NULL DEFAULT '0',
  escalated INTEGER(11) NOT NULL DEFAULT '0',
  breached INTEGER(11) NOT NULL DEFAULT '0',
  rating INTEGER(11) NOT NULL DEFAULT '0',
  rating_comment varchar(1000) NOT NULL DEFAULT '',
  rated INTEGER(11) NOT NULL DEFAULT '0',
  note_count INTEGER(11) NOT NULL DEFAULT '0',
  attachment_count INTEGER(11) NOT NULL DEFAULT '0'
);

CREATE  INDEX ticketsystem_ticket_account ON ticketsystem_ticket  ( account_key, status );

CREATE  INDEX ticketsystem_ticket_assignee ON ticketsystem_ticket  ( assignee_id, status );

CREATE  INDEX ticketsystem_ticket_escal ON ticketsystem_ticket  ( escalation, status );

CREATE  UNIQUE INDEX ticketsystem_ticket_number ON ticketsystem_ticket  ( number );

CREATE  UNIQUE INDEX ticketsystem_ticket_object ON ticketsystem_ticket  ( contentobject_id );

CREATE  INDEX ticketsystem_ticket_opener ON ticketsystem_ticket  ( opener_id );

CREATE  INDEX ticketsystem_ticket_status ON ticketsystem_ticket  ( status, modified );

CREATE TABLE ticketsystem_counter (
  prefix varchar(16) NOT NULL DEFAULT '',
  year INTEGER(11) NOT NULL DEFAULT '0',
  last_value INTEGER(11) NOT NULL DEFAULT '0',
  PRIMARY KEY ( prefix, year )
);

CREATE TABLE ticketsystem_note (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  ticket_id INTEGER(11) NOT NULL DEFAULT '0',
  author_id INTEGER(11) NOT NULL DEFAULT '0',
  body longtext DEFAULT NULL,
  created INTEGER(11) NOT NULL DEFAULT '0',
  attachment_count INTEGER(11) NOT NULL DEFAULT '0'
);

CREATE  INDEX ticketsystem_note_ticket ON ticketsystem_note  ( ticket_id );

CREATE TABLE ticketsystem_event (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  ticket_id INTEGER(11) NOT NULL DEFAULT '0',
  actor_id INTEGER(11) NOT NULL DEFAULT '0',
  kind varchar(32) NOT NULL DEFAULT '',
  old_value varchar(255) NOT NULL DEFAULT '',
  new_value varchar(255) NOT NULL DEFAULT '',
  created INTEGER(11) NOT NULL DEFAULT '0'
);

CREATE  INDEX ticketsystem_event_kind ON ticketsystem_event  ( kind, created );

CREATE  INDEX ticketsystem_event_ticket ON ticketsystem_event  ( ticket_id, created );

CREATE TABLE ticketsystem_canned (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title varchar(200) NOT NULL DEFAULT '',
  body longtext DEFAULT NULL,
  category varchar(64) NOT NULL DEFAULT '',
  set_status varchar(32) NOT NULL DEFAULT '',
  set_priority varchar(32) NOT NULL DEFAULT '',
  assign_self INTEGER(11) NOT NULL DEFAULT '0',
  owner_id INTEGER(11) NOT NULL DEFAULT '0',
  created_by INTEGER(11) NOT NULL DEFAULT '0',
  created INTEGER(11) NOT NULL DEFAULT '0',
  modified INTEGER(11) NOT NULL DEFAULT '0',
  use_count INTEGER(11) NOT NULL DEFAULT '0'
);

CREATE  INDEX ticketsystem_canned_owner ON ticketsystem_canned  ( owner_id );

CREATE TABLE ticketsystem_attachment (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  ticket_id INTEGER(11) NOT NULL DEFAULT '0',
  post_id INTEGER(11) NOT NULL DEFAULT '0',
  note_id INTEGER(11) NOT NULL DEFAULT '0',
  uploader_id INTEGER(11) NOT NULL DEFAULT '0',
  name varchar(255) NOT NULL DEFAULT '',
  mime varchar(128) NOT NULL DEFAULT '',
  filesize INTEGER(11) NOT NULL DEFAULT '0',
  sha256 varchar(64) NOT NULL DEFAULT '',
  storage_key varchar(128) NOT NULL DEFAULT '',
  created INTEGER(11) NOT NULL DEFAULT '0'
);

CREATE  INDEX ticketsystem_attachment__ticketsystem_attach_ticket ON ticketsystem_attachment  ( ticket_id );

CREATE TABLE ticketsystem_post (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  ticket_id INTEGER(11) NOT NULL DEFAULT '0',
  object_id INTEGER(11) NOT NULL DEFAULT '0',
  author_id INTEGER(11) NOT NULL DEFAULT '0',
  is_staff INTEGER(11) NOT NULL DEFAULT '0',
  created INTEGER(11) NOT NULL DEFAULT '0',
  body longtext DEFAULT NULL
);

CREATE  UNIQUE INDEX ticketsystem_post_object ON ticketsystem_post  ( object_id );

CREATE  INDEX ticketsystem_post_ticket ON ticketsystem_post  ( ticket_id );

CREATE TABLE ticketsystem_mailin (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  message_key varchar(64) NOT NULL DEFAULT '',
  ticket_id INTEGER(11) NOT NULL DEFAULT '0',
  user_id INTEGER(11) NOT NULL DEFAULT '0',
  result varchar(32) NOT NULL DEFAULT '',
  created INTEGER(11) NOT NULL DEFAULT '0'
);

CREATE  UNIQUE INDEX ticketsystem_mailin_key ON ticketsystem_mailin  ( message_key );

CREATE TABLE ticketsystem_import (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  source varchar(16) NOT NULL DEFAULT '',
  kind varchar(16) NOT NULL DEFAULT '',
  external_id varchar(64) NOT NULL DEFAULT '',
  local_id INTEGER(11) NOT NULL DEFAULT '0',
  created INTEGER(11) NOT NULL DEFAULT '0'
);

CREATE  UNIQUE INDEX ticketsystem_import_ext ON ticketsystem_import  ( source, kind, external_id );

CREATE TABLE ticketsystem_escalation (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  ticket_id INTEGER(11) NOT NULL DEFAULT '0',
  step_key varchar(48) NOT NULL DEFAULT '',
  action varchar(16) NOT NULL DEFAULT '',
  target varchar(16) NOT NULL DEFAULT '',
  percent INTEGER(11) NOT NULL DEFAULT '0',
  level INTEGER(11) NOT NULL DEFAULT '0',
  actor_id INTEGER(11) NOT NULL DEFAULT '0',
  recipients INTEGER(11) NOT NULL DEFAULT '0',
  sent INTEGER(11) NOT NULL DEFAULT '0',
  failed INTEGER(11) NOT NULL DEFAULT '0',
  created INTEGER(11) NOT NULL DEFAULT '0'
);

CREATE  INDEX ticketsystem_escalation__ticketsystem_escal_created ON ticketsystem_escalation  ( created );

CREATE  UNIQUE INDEX ticketsystem_escalation__ticketsystem_escal_step ON ticketsystem_escalation  ( ticket_id, step_key );

