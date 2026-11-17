-- ticketsystem (the help desk): its tables on postgresql.
-- Generated from ticketsystemSchema::definition() by bin/php/install.php --write-schema-files with the kernel's
-- schema handler; bin/php/install.php creates the same tables in a running installation.

CREATE SEQUENCE IF NOT EXISTS ticketsystem_ticket_id_seq
  START 1
  INCREMENT 1
  MAXVALUE 9223372036854775807
  MINVALUE 1
  CACHE 1;

CREATE TABLE IF NOT EXISTS ticketsystem_ticket (
  id integer DEFAULT nextval('ticketsystem_ticket_id_seq'::text) NOT NULL,
  contentobject_id integer DEFAULT 0 NOT NULL,
  node_id integer DEFAULT 0 NOT NULL,
  number character varying(32) DEFAULT ''::character varying NOT NULL,
  prefix character varying(16) DEFAULT ''::character varying NOT NULL,
  "year" integer DEFAULT 0 NOT NULL,
  seq integer DEFAULT 0 NOT NULL,
  account_key character varying(64) DEFAULT ''::character varying NOT NULL,
  opener_id integer DEFAULT 0 NOT NULL,
  subject character varying(255) DEFAULT ''::character varying NOT NULL,
  status character varying(32) DEFAULT ''::character varying NOT NULL,
  category character varying(64) DEFAULT ''::character varying NOT NULL,
  priority character varying(32) DEFAULT ''::character varying NOT NULL,
  subscription_id integer DEFAULT 0 NOT NULL,
  post_count integer DEFAULT 0 NOT NULL,
  last_post integer DEFAULT 0 NOT NULL,
  last_post_by integer DEFAULT 0 NOT NULL,
  last_post_staff integer DEFAULT 0 NOT NULL,
  created integer DEFAULT 0 NOT NULL,
  modified integer DEFAULT 0 NOT NULL,
  resolved integer DEFAULT 0 NOT NULL,
  closed integer DEFAULT 0 NOT NULL,
  priority_rank integer DEFAULT 0 NOT NULL,
  assignee_id integer DEFAULT 0 NOT NULL,
  assigned integer DEFAULT 0 NOT NULL,
  source character varying(16) DEFAULT 'web'::character varying NOT NULL,
  external_id character varying(64) DEFAULT ''::character varying NOT NULL,
  sla_level character varying(32) DEFAULT ''::character varying NOT NULL,
  response_due integer DEFAULT 0 NOT NULL,
  resolve_due integer DEFAULT 0 NOT NULL,
  responded integer DEFAULT 0 NOT NULL,
  paused_since integer DEFAULT 0 NOT NULL,
  escalation integer DEFAULT 0 NOT NULL,
  escalated integer DEFAULT 0 NOT NULL,
  breached integer DEFAULT 0 NOT NULL,
  rating integer DEFAULT 0 NOT NULL,
  rating_comment character varying(1000) DEFAULT ''::character varying NOT NULL,
  rated integer DEFAULT 0 NOT NULL,
  note_count integer DEFAULT 0 NOT NULL,
  attachment_count integer DEFAULT 0 NOT NULL,
  merged_into integer DEFAULT 0 NOT NULL,
  time_minutes integer DEFAULT 0 NOT NULL,
  billable_minutes integer DEFAULT 0 NOT NULL
);

CREATE UNIQUE INDEX ticketsystem_ticket_number ON ticketsystem_ticket USING btree ( number );

CREATE UNIQUE INDEX ticketsystem_ticket_object ON ticketsystem_ticket USING btree ( contentobject_id );

CREATE INDEX ticketsystem_ticket_account ON ticketsystem_ticket USING btree ( account_key, status );

CREATE INDEX ticketsystem_ticket_opener ON ticketsystem_ticket USING btree ( opener_id );

CREATE INDEX ticketsystem_ticket_status ON ticketsystem_ticket USING btree ( status, modified );

CREATE INDEX ticketsystem_ticket_assignee ON ticketsystem_ticket USING btree ( assignee_id, status );

CREATE INDEX ticketsystem_ticket_escal ON ticketsystem_ticket USING btree ( escalation, status );

ALTER TABLE ONLY ticketsystem_ticket ADD CONSTRAINT ticketsystem_ticket_pkey PRIMARY KEY ( id );

CREATE TABLE IF NOT EXISTS ticketsystem_counter (
  prefix character varying(16) DEFAULT ''::character varying NOT NULL,
  "year" integer DEFAULT 0 NOT NULL,
  last_value integer DEFAULT 0 NOT NULL
);

ALTER TABLE ONLY ticketsystem_counter ADD CONSTRAINT ticketsystem_counter_pkey PRIMARY KEY ( prefix, "year" );

CREATE SEQUENCE IF NOT EXISTS ticketsystem_note_id_seq
  START 1
  INCREMENT 1
  MAXVALUE 9223372036854775807
  MINVALUE 1
  CACHE 1;

CREATE TABLE IF NOT EXISTS ticketsystem_note (
  id integer DEFAULT nextval('ticketsystem_note_id_seq'::text) NOT NULL,
  ticket_id integer DEFAULT 0 NOT NULL,
  author_id integer DEFAULT 0 NOT NULL,
  body text DEFAULT NULL,
  created integer DEFAULT 0 NOT NULL,
  attachment_count integer DEFAULT 0 NOT NULL
);

CREATE INDEX ticketsystem_note_ticket ON ticketsystem_note USING btree ( ticket_id );

ALTER TABLE ONLY ticketsystem_note ADD CONSTRAINT ticketsystem_note_pkey PRIMARY KEY ( id );

CREATE SEQUENCE IF NOT EXISTS ticketsystem_event_id_seq
  START 1
  INCREMENT 1
  MAXVALUE 9223372036854775807
  MINVALUE 1
  CACHE 1;

CREATE TABLE IF NOT EXISTS ticketsystem_event (
  id integer DEFAULT nextval('ticketsystem_event_id_seq'::text) NOT NULL,
  ticket_id integer DEFAULT 0 NOT NULL,
  actor_id integer DEFAULT 0 NOT NULL,
  kind character varying(32) DEFAULT ''::character varying NOT NULL,
  old_value character varying(255) DEFAULT ''::character varying NOT NULL,
  new_value character varying(255) DEFAULT ''::character varying NOT NULL,
  created integer DEFAULT 0 NOT NULL
);

CREATE INDEX ticketsystem_event_ticket ON ticketsystem_event USING btree ( ticket_id, created );

CREATE INDEX ticketsystem_event_kind ON ticketsystem_event USING btree ( kind, created );

ALTER TABLE ONLY ticketsystem_event ADD CONSTRAINT ticketsystem_event_pkey PRIMARY KEY ( id );

CREATE SEQUENCE IF NOT EXISTS ticketsystem_canned_id_seq
  START 1
  INCREMENT 1
  MAXVALUE 9223372036854775807
  MINVALUE 1
  CACHE 1;

CREATE TABLE IF NOT EXISTS ticketsystem_canned (
  id integer DEFAULT nextval('ticketsystem_canned_id_seq'::text) NOT NULL,
  title character varying(200) DEFAULT ''::character varying NOT NULL,
  body text DEFAULT NULL,
  category character varying(64) DEFAULT ''::character varying NOT NULL,
  set_status character varying(32) DEFAULT ''::character varying NOT NULL,
  set_priority character varying(32) DEFAULT ''::character varying NOT NULL,
  assign_self integer DEFAULT 0 NOT NULL,
  owner_id integer DEFAULT 0 NOT NULL,
  created_by integer DEFAULT 0 NOT NULL,
  created integer DEFAULT 0 NOT NULL,
  modified integer DEFAULT 0 NOT NULL,
  use_count integer DEFAULT 0 NOT NULL
);

CREATE INDEX ticketsystem_canned_owner ON ticketsystem_canned USING btree ( owner_id );

ALTER TABLE ONLY ticketsystem_canned ADD CONSTRAINT ticketsystem_canned_pkey PRIMARY KEY ( id );

CREATE SEQUENCE IF NOT EXISTS ticketsystem_attachment_id_seq
  START 1
  INCREMENT 1
  MAXVALUE 9223372036854775807
  MINVALUE 1
  CACHE 1;

CREATE TABLE IF NOT EXISTS ticketsystem_attachment (
  id integer DEFAULT nextval('ticketsystem_attachment_id_seq'::text) NOT NULL,
  ticket_id integer DEFAULT 0 NOT NULL,
  post_id integer DEFAULT 0 NOT NULL,
  note_id integer DEFAULT 0 NOT NULL,
  uploader_id integer DEFAULT 0 NOT NULL,
  name character varying(255) DEFAULT ''::character varying NOT NULL,
  mime character varying(128) DEFAULT ''::character varying NOT NULL,
  filesize integer DEFAULT 0 NOT NULL,
  sha256 character varying(64) DEFAULT ''::character varying NOT NULL,
  storage_key character varying(128) DEFAULT ''::character varying NOT NULL,
  created integer DEFAULT 0 NOT NULL
);

CREATE INDEX ticketsystem_attach_ticket ON ticketsystem_attachment USING btree ( ticket_id );

ALTER TABLE ONLY ticketsystem_attachment ADD CONSTRAINT ticketsystem_attachment_pkey PRIMARY KEY ( id );

CREATE SEQUENCE IF NOT EXISTS ticketsystem_post_id_seq
  START 1
  INCREMENT 1
  MAXVALUE 9223372036854775807
  MINVALUE 1
  CACHE 1;

CREATE TABLE IF NOT EXISTS ticketsystem_post (
  id integer DEFAULT nextval('ticketsystem_post_id_seq'::text) NOT NULL,
  ticket_id integer DEFAULT 0 NOT NULL,
  object_id integer DEFAULT 0 NOT NULL,
  author_id integer DEFAULT 0 NOT NULL,
  is_staff integer DEFAULT 0 NOT NULL,
  created integer DEFAULT 0 NOT NULL,
  body text DEFAULT NULL
);

CREATE INDEX ticketsystem_post_ticket ON ticketsystem_post USING btree ( ticket_id );

CREATE UNIQUE INDEX ticketsystem_post_object ON ticketsystem_post USING btree ( object_id );

ALTER TABLE ONLY ticketsystem_post ADD CONSTRAINT ticketsystem_post_pkey PRIMARY KEY ( id );

CREATE SEQUENCE IF NOT EXISTS ticketsystem_mailin_id_seq
  START 1
  INCREMENT 1
  MAXVALUE 9223372036854775807
  MINVALUE 1
  CACHE 1;

CREATE TABLE IF NOT EXISTS ticketsystem_mailin (
  id integer DEFAULT nextval('ticketsystem_mailin_id_seq'::text) NOT NULL,
  message_key character varying(64) DEFAULT ''::character varying NOT NULL,
  ticket_id integer DEFAULT 0 NOT NULL,
  user_id integer DEFAULT 0 NOT NULL,
  result character varying(32) DEFAULT ''::character varying NOT NULL,
  created integer DEFAULT 0 NOT NULL
);

CREATE UNIQUE INDEX ticketsystem_mailin_key ON ticketsystem_mailin USING btree ( message_key );

ALTER TABLE ONLY ticketsystem_mailin ADD CONSTRAINT ticketsystem_mailin_pkey PRIMARY KEY ( id );

CREATE SEQUENCE IF NOT EXISTS ticketsystem_import_id_seq
  START 1
  INCREMENT 1
  MAXVALUE 9223372036854775807
  MINVALUE 1
  CACHE 1;

CREATE TABLE IF NOT EXISTS ticketsystem_import (
  id integer DEFAULT nextval('ticketsystem_import_id_seq'::text) NOT NULL,
  source character varying(16) DEFAULT ''::character varying NOT NULL,
  kind character varying(16) DEFAULT ''::character varying NOT NULL,
  external_id character varying(64) DEFAULT ''::character varying NOT NULL,
  local_id integer DEFAULT 0 NOT NULL,
  created integer DEFAULT 0 NOT NULL
);

CREATE UNIQUE INDEX ticketsystem_import_ext ON ticketsystem_import USING btree ( source, kind, external_id );

ALTER TABLE ONLY ticketsystem_import ADD CONSTRAINT ticketsystem_import_pkey PRIMARY KEY ( id );

CREATE SEQUENCE IF NOT EXISTS ticketsystem_escalation_id_seq
  START 1
  INCREMENT 1
  MAXVALUE 9223372036854775807
  MINVALUE 1
  CACHE 1;

CREATE TABLE IF NOT EXISTS ticketsystem_escalation (
  id integer DEFAULT nextval('ticketsystem_escalation_id_seq'::text) NOT NULL,
  ticket_id integer DEFAULT 0 NOT NULL,
  step_key character varying(48) DEFAULT ''::character varying NOT NULL,
  "action" character varying(16) DEFAULT ''::character varying NOT NULL,
  target character varying(16) DEFAULT ''::character varying NOT NULL,
  percent integer DEFAULT 0 NOT NULL,
  "level" integer DEFAULT 0 NOT NULL,
  actor_id integer DEFAULT 0 NOT NULL,
  recipients integer DEFAULT 0 NOT NULL,
  sent integer DEFAULT 0 NOT NULL,
  failed integer DEFAULT 0 NOT NULL,
  created integer DEFAULT 0 NOT NULL
);

CREATE UNIQUE INDEX ticketsystem_escal_step ON ticketsystem_escalation USING btree ( ticket_id, step_key );

CREATE INDEX ticketsystem_escal_created ON ticketsystem_escalation USING btree ( created );

ALTER TABLE ONLY ticketsystem_escalation ADD CONSTRAINT ticketsystem_escalation_pkey PRIMARY KEY ( id );

CREATE SEQUENCE IF NOT EXISTS ticketsystem_cc_id_seq
  START 1
  INCREMENT 1
  MAXVALUE 9223372036854775807
  MINVALUE 1
  CACHE 1;

CREATE TABLE IF NOT EXISTS ticketsystem_cc (
  id integer DEFAULT nextval('ticketsystem_cc_id_seq'::text) NOT NULL,
  ticket_id integer DEFAULT 0 NOT NULL,
  email character varying(255) DEFAULT ''::character varying NOT NULL,
  added_by integer DEFAULT 0 NOT NULL,
  created integer DEFAULT 0 NOT NULL
);

CREATE INDEX ticketsystem_cc_ticket ON ticketsystem_cc USING btree ( ticket_id );

ALTER TABLE ONLY ticketsystem_cc ADD CONSTRAINT ticketsystem_cc_pkey PRIMARY KEY ( id );

CREATE SEQUENCE IF NOT EXISTS ticketsystem_time_id_seq
  START 1
  INCREMENT 1
  MAXVALUE 9223372036854775807
  MINVALUE 1
  CACHE 1;

CREATE TABLE IF NOT EXISTS ticketsystem_time (
  id integer DEFAULT nextval('ticketsystem_time_id_seq'::text) NOT NULL,
  ticket_id integer DEFAULT 0 NOT NULL,
  post_id integer DEFAULT 0 NOT NULL,
  note_id integer DEFAULT 0 NOT NULL,
  user_id integer DEFAULT 0 NOT NULL,
  minutes integer DEFAULT 0 NOT NULL,
  billable integer DEFAULT 0 NOT NULL,
  covered character varying(32) DEFAULT ''::character varying NOT NULL,
  work_date integer DEFAULT 0 NOT NULL,
  remark character varying(255) DEFAULT ''::character varying NOT NULL,
  account_key character varying(64) DEFAULT ''::character varying NOT NULL,
  created integer DEFAULT 0 NOT NULL,
  billed integer DEFAULT 0 NOT NULL,
  billing_ref character varying(64) DEFAULT ''::character varying NOT NULL
);

CREATE INDEX ticketsystem_time_ticket ON ticketsystem_time USING btree ( ticket_id );

CREATE INDEX ticketsystem_time_user ON ticketsystem_time USING btree ( user_id, work_date );

CREATE INDEX ticketsystem_time_bill ON ticketsystem_time USING btree ( billable, billed );

ALTER TABLE ONLY ticketsystem_time ADD CONSTRAINT ticketsystem_time_pkey PRIMARY KEY ( id );

