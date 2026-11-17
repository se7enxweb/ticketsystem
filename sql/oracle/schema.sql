-- ticketsystem (the help desk): its tables on oracle.
-- Generated from ticketsystemSchema::definition() by bin/php/install.php --write-schema-files with the kernel's
-- schema handler; bin/php/install.php creates the same tables in a running installation.

BEGIN
  EXECUTE IMMEDIATE 'CREATE SEQUENCE se_ticketsystem_ticket';
EXCEPTION WHEN OTHERS THEN
  IF SQLCODE != -955 THEN RAISE; END IF;
END;
/

CREATE TABLE ticketsystem_ticket (
  id INTEGER NOT NULL,
  contentobject_id INTEGER DEFAULT 0 NOT NULL,
  node_id INTEGER DEFAULT 0 NOT NULL,
  "NUMBER" VARCHAR2(32),
  prefix VARCHAR2(16),
  year INTEGER DEFAULT 0 NOT NULL,
  seq INTEGER DEFAULT 0 NOT NULL,
  account_key VARCHAR2(64),
  opener_id INTEGER DEFAULT 0 NOT NULL,
  subject VARCHAR2(255),
  status VARCHAR2(32),
  category VARCHAR2(64),
  priority VARCHAR2(32),
  subscription_id INTEGER DEFAULT 0 NOT NULL,
  post_count INTEGER DEFAULT 0 NOT NULL,
  last_post INTEGER DEFAULT 0 NOT NULL,
  last_post_by INTEGER DEFAULT 0 NOT NULL,
  last_post_staff INTEGER DEFAULT 0 NOT NULL,
  created INTEGER DEFAULT 0 NOT NULL,
  modified INTEGER DEFAULT 0 NOT NULL,
  resolved INTEGER DEFAULT 0 NOT NULL,
  closed INTEGER DEFAULT 0 NOT NULL,
  priority_rank INTEGER DEFAULT 0 NOT NULL,
  assignee_id INTEGER DEFAULT 0 NOT NULL,
  assigned INTEGER DEFAULT 0 NOT NULL,
  source VARCHAR2(16) DEFAULT 'web',
  external_id VARCHAR2(64),
  sla_level VARCHAR2(32),
  response_due INTEGER DEFAULT 0 NOT NULL,
  resolve_due INTEGER DEFAULT 0 NOT NULL,
  responded INTEGER DEFAULT 0 NOT NULL,
  paused_since INTEGER DEFAULT 0 NOT NULL,
  escalation INTEGER DEFAULT 0 NOT NULL,
  escalated INTEGER DEFAULT 0 NOT NULL,
  breached INTEGER DEFAULT 0 NOT NULL,
  rating INTEGER DEFAULT 0 NOT NULL,
  rating_comment VARCHAR2(1000),
  rated INTEGER DEFAULT 0 NOT NULL,
  note_count INTEGER DEFAULT 0 NOT NULL,
  attachment_count INTEGER DEFAULT 0 NOT NULL,
  merged_into INTEGER DEFAULT 0 NOT NULL,
  time_minutes INTEGER DEFAULT 0 NOT NULL,
  billable_minutes INTEGER DEFAULT 0 NOT NULL,
  PRIMARY KEY ( id )
);

CREATE OR REPLACE TRIGGER ticketsystem_ticket_id_tr
BEFORE INSERT ON ticketsystem_ticket FOR EACH ROW WHEN (new.id IS NULL)
BEGIN
  SELECT se_ticketsystem_ticket.nextval INTO :new.id FROM dual;
END;
/

CREATE INDEX ticketsystem_ticket_account ON ticketsystem_ticket ( account_key, status );

CREATE INDEX ticketsystem_ticket_assignee ON ticketsystem_ticket ( assignee_id, status );

CREATE INDEX ticketsystem_ticket_escal ON ticketsystem_ticket ( escalation, status );

CREATE UNIQUE INDEX ticketsystem_ticket_number ON ticketsystem_ticket ( "NUMBER" );

CREATE UNIQUE INDEX ticketsystem_ticket_object ON ticketsystem_ticket ( contentobject_id );

CREATE INDEX ticketsystem_ticket_opener ON ticketsystem_ticket ( opener_id );

CREATE INDEX ticketsystem_ticket_status ON ticketsystem_ticket ( status, modified );

CREATE TABLE ticketsystem_counter (
  prefix VARCHAR2(16),
  year INTEGER DEFAULT 0 NOT NULL,
  last_value INTEGER DEFAULT 0 NOT NULL,
  PRIMARY KEY ( prefix, year )
);

BEGIN
  EXECUTE IMMEDIATE 'CREATE SEQUENCE se_ticketsystem_note';
EXCEPTION WHEN OTHERS THEN
  IF SQLCODE != -955 THEN RAISE; END IF;
END;
/

CREATE TABLE ticketsystem_note (
  id INTEGER NOT NULL,
  ticket_id INTEGER DEFAULT 0 NOT NULL,
  author_id INTEGER DEFAULT 0 NOT NULL,
  body CLOB,
  created INTEGER DEFAULT 0 NOT NULL,
  attachment_count INTEGER DEFAULT 0 NOT NULL,
  PRIMARY KEY ( id )
);

CREATE OR REPLACE TRIGGER ticketsystem_note_id_tr
BEFORE INSERT ON ticketsystem_note FOR EACH ROW WHEN (new.id IS NULL)
BEGIN
  SELECT se_ticketsystem_note.nextval INTO :new.id FROM dual;
END;
/

CREATE INDEX ticketsystem_note_ticket ON ticketsystem_note ( ticket_id );

BEGIN
  EXECUTE IMMEDIATE 'CREATE SEQUENCE se_ticketsystem_event';
EXCEPTION WHEN OTHERS THEN
  IF SQLCODE != -955 THEN RAISE; END IF;
END;
/

CREATE TABLE ticketsystem_event (
  id INTEGER NOT NULL,
  ticket_id INTEGER DEFAULT 0 NOT NULL,
  actor_id INTEGER DEFAULT 0 NOT NULL,
  kind VARCHAR2(32),
  old_value VARCHAR2(255),
  new_value VARCHAR2(255),
  created INTEGER DEFAULT 0 NOT NULL,
  PRIMARY KEY ( id )
);

CREATE OR REPLACE TRIGGER ticketsystem_event_id_tr
BEFORE INSERT ON ticketsystem_event FOR EACH ROW WHEN (new.id IS NULL)
BEGIN
  SELECT se_ticketsystem_event.nextval INTO :new.id FROM dual;
END;
/

CREATE INDEX ticketsystem_event_kind ON ticketsystem_event ( kind, created );

CREATE INDEX ticketsystem_event_ticket ON ticketsystem_event ( ticket_id, created );

BEGIN
  EXECUTE IMMEDIATE 'CREATE SEQUENCE se_ticketsystem_canned';
EXCEPTION WHEN OTHERS THEN
  IF SQLCODE != -955 THEN RAISE; END IF;
END;
/

CREATE TABLE ticketsystem_canned (
  id INTEGER NOT NULL,
  title VARCHAR2(200),
  body CLOB,
  category VARCHAR2(64),
  set_status VARCHAR2(32),
  set_priority VARCHAR2(32),
  assign_self INTEGER DEFAULT 0 NOT NULL,
  owner_id INTEGER DEFAULT 0 NOT NULL,
  created_by INTEGER DEFAULT 0 NOT NULL,
  created INTEGER DEFAULT 0 NOT NULL,
  modified INTEGER DEFAULT 0 NOT NULL,
  use_count INTEGER DEFAULT 0 NOT NULL,
  PRIMARY KEY ( id )
);

CREATE OR REPLACE TRIGGER ticketsystem_canned_id_tr
BEFORE INSERT ON ticketsystem_canned FOR EACH ROW WHEN (new.id IS NULL)
BEGIN
  SELECT se_ticketsystem_canned.nextval INTO :new.id FROM dual;
END;
/

CREATE INDEX ticketsystem_canned_owner ON ticketsystem_canned ( owner_id );

BEGIN
  EXECUTE IMMEDIATE 'CREATE SEQUENCE se_ticketsystem_attachment';
EXCEPTION WHEN OTHERS THEN
  IF SQLCODE != -955 THEN RAISE; END IF;
END;
/

CREATE TABLE ticketsystem_attachment (
  id INTEGER NOT NULL,
  ticket_id INTEGER DEFAULT 0 NOT NULL,
  post_id INTEGER DEFAULT 0 NOT NULL,
  note_id INTEGER DEFAULT 0 NOT NULL,
  uploader_id INTEGER DEFAULT 0 NOT NULL,
  name VARCHAR2(255),
  mime VARCHAR2(128),
  filesize INTEGER DEFAULT 0 NOT NULL,
  sha256 VARCHAR2(64),
  storage_key VARCHAR2(128),
  created INTEGER DEFAULT 0 NOT NULL,
  PRIMARY KEY ( id )
);

CREATE OR REPLACE TRIGGER ticketsystem_attachment_id_tr
BEFORE INSERT ON ticketsystem_attachment FOR EACH ROW WHEN (new.id IS NULL)
BEGIN
  SELECT se_ticketsystem_attachment.nextval INTO :new.id FROM dual;
END;
/

CREATE INDEX ticketsystem_attach_ticket ON ticketsystem_attachment ( ticket_id );

BEGIN
  EXECUTE IMMEDIATE 'CREATE SEQUENCE se_ticketsystem_post';
EXCEPTION WHEN OTHERS THEN
  IF SQLCODE != -955 THEN RAISE; END IF;
END;
/

CREATE TABLE ticketsystem_post (
  id INTEGER NOT NULL,
  ticket_id INTEGER DEFAULT 0 NOT NULL,
  object_id INTEGER DEFAULT 0 NOT NULL,
  author_id INTEGER DEFAULT 0 NOT NULL,
  is_staff INTEGER DEFAULT 0 NOT NULL,
  created INTEGER DEFAULT 0 NOT NULL,
  body CLOB,
  PRIMARY KEY ( id )
);

CREATE OR REPLACE TRIGGER ticketsystem_post_id_tr
BEFORE INSERT ON ticketsystem_post FOR EACH ROW WHEN (new.id IS NULL)
BEGIN
  SELECT se_ticketsystem_post.nextval INTO :new.id FROM dual;
END;
/

CREATE UNIQUE INDEX ticketsystem_post_object ON ticketsystem_post ( object_id );

CREATE INDEX ticketsystem_post_ticket ON ticketsystem_post ( ticket_id );

BEGIN
  EXECUTE IMMEDIATE 'CREATE SEQUENCE se_ticketsystem_mailin';
EXCEPTION WHEN OTHERS THEN
  IF SQLCODE != -955 THEN RAISE; END IF;
END;
/

CREATE TABLE ticketsystem_mailin (
  id INTEGER NOT NULL,
  message_key VARCHAR2(64),
  ticket_id INTEGER DEFAULT 0 NOT NULL,
  user_id INTEGER DEFAULT 0 NOT NULL,
  result VARCHAR2(32),
  created INTEGER DEFAULT 0 NOT NULL,
  PRIMARY KEY ( id )
);

CREATE OR REPLACE TRIGGER ticketsystem_mailin_id_tr
BEFORE INSERT ON ticketsystem_mailin FOR EACH ROW WHEN (new.id IS NULL)
BEGIN
  SELECT se_ticketsystem_mailin.nextval INTO :new.id FROM dual;
END;
/

CREATE UNIQUE INDEX ticketsystem_mailin_key ON ticketsystem_mailin ( message_key );

BEGIN
  EXECUTE IMMEDIATE 'CREATE SEQUENCE se_ticketsystem_import';
EXCEPTION WHEN OTHERS THEN
  IF SQLCODE != -955 THEN RAISE; END IF;
END;
/

CREATE TABLE ticketsystem_import (
  id INTEGER NOT NULL,
  source VARCHAR2(16),
  kind VARCHAR2(16),
  external_id VARCHAR2(64),
  local_id INTEGER DEFAULT 0 NOT NULL,
  created INTEGER DEFAULT 0 NOT NULL,
  PRIMARY KEY ( id )
);

CREATE OR REPLACE TRIGGER ticketsystem_import_id_tr
BEFORE INSERT ON ticketsystem_import FOR EACH ROW WHEN (new.id IS NULL)
BEGIN
  SELECT se_ticketsystem_import.nextval INTO :new.id FROM dual;
END;
/

CREATE UNIQUE INDEX ticketsystem_import_ext ON ticketsystem_import ( source, kind, external_id );

BEGIN
  EXECUTE IMMEDIATE 'CREATE SEQUENCE se_ticketsystem_escalation';
EXCEPTION WHEN OTHERS THEN
  IF SQLCODE != -955 THEN RAISE; END IF;
END;
/

CREATE TABLE ticketsystem_escalation (
  id INTEGER NOT NULL,
  ticket_id INTEGER DEFAULT 0 NOT NULL,
  step_key VARCHAR2(48),
  action VARCHAR2(16),
  target VARCHAR2(16),
  percent INTEGER DEFAULT 0 NOT NULL,
  "LEVEL" INTEGER DEFAULT 0 NOT NULL,
  actor_id INTEGER DEFAULT 0 NOT NULL,
  recipients INTEGER DEFAULT 0 NOT NULL,
  sent INTEGER DEFAULT 0 NOT NULL,
  failed INTEGER DEFAULT 0 NOT NULL,
  created INTEGER DEFAULT 0 NOT NULL,
  PRIMARY KEY ( id )
);

CREATE OR REPLACE TRIGGER ticketsystem_escalation_id_tr
BEFORE INSERT ON ticketsystem_escalation FOR EACH ROW WHEN (new.id IS NULL)
BEGIN
  SELECT se_ticketsystem_escalation.nextval INTO :new.id FROM dual;
END;
/

CREATE INDEX ticketsystem_escal_created ON ticketsystem_escalation ( created );

CREATE UNIQUE INDEX ticketsystem_escal_step ON ticketsystem_escalation ( ticket_id, step_key );

BEGIN
  EXECUTE IMMEDIATE 'CREATE SEQUENCE se_ticketsystem_cc';
EXCEPTION WHEN OTHERS THEN
  IF SQLCODE != -955 THEN RAISE; END IF;
END;
/

CREATE TABLE ticketsystem_cc (
  id INTEGER NOT NULL,
  ticket_id INTEGER DEFAULT 0 NOT NULL,
  email VARCHAR2(255),
  added_by INTEGER DEFAULT 0 NOT NULL,
  created INTEGER DEFAULT 0 NOT NULL,
  PRIMARY KEY ( id )
);

CREATE OR REPLACE TRIGGER ticketsystem_cc_id_tr
BEFORE INSERT ON ticketsystem_cc FOR EACH ROW WHEN (new.id IS NULL)
BEGIN
  SELECT se_ticketsystem_cc.nextval INTO :new.id FROM dual;
END;
/

CREATE INDEX ticketsystem_cc_ticket ON ticketsystem_cc ( ticket_id );

BEGIN
  EXECUTE IMMEDIATE 'CREATE SEQUENCE se_ticketsystem_time';
EXCEPTION WHEN OTHERS THEN
  IF SQLCODE != -955 THEN RAISE; END IF;
END;
/

CREATE TABLE ticketsystem_time (
  id INTEGER NOT NULL,
  ticket_id INTEGER DEFAULT 0 NOT NULL,
  post_id INTEGER DEFAULT 0 NOT NULL,
  note_id INTEGER DEFAULT 0 NOT NULL,
  user_id INTEGER DEFAULT 0 NOT NULL,
  minutes INTEGER DEFAULT 0 NOT NULL,
  billable INTEGER DEFAULT 0 NOT NULL,
  covered VARCHAR2(32),
  work_date INTEGER DEFAULT 0 NOT NULL,
  remark VARCHAR2(255),
  account_key VARCHAR2(64),
  created INTEGER DEFAULT 0 NOT NULL,
  billed INTEGER DEFAULT 0 NOT NULL,
  billing_ref VARCHAR2(64),
  PRIMARY KEY ( id )
);

CREATE OR REPLACE TRIGGER ticketsystem_time_id_tr
BEFORE INSERT ON ticketsystem_time FOR EACH ROW WHEN (new.id IS NULL)
BEGIN
  SELECT se_ticketsystem_time.nextval INTO :new.id FROM dual;
END;
/

CREATE INDEX ticketsystem_time_bill ON ticketsystem_time ( billable, billed );

CREATE INDEX ticketsystem_time_ticket ON ticketsystem_time ( ticket_id );

CREATE INDEX ticketsystem_time_user ON ticketsystem_time ( user_id, work_date );

