-- ticketsystem (the help desk): the tables ticketsystem_ticket and ticketsystem_counter (oracle).
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
  PRIMARY KEY ( id )
);

CREATE OR REPLACE TRIGGER ticketsystem_ticket_id_tr
BEFORE INSERT ON ticketsystem_ticket FOR EACH ROW WHEN (new.id IS NULL)
BEGIN
  SELECT se_ticketsystem_ticket.nextval INTO :new.id FROM dual;
END;
/

CREATE INDEX ticketsystem_ticket_account ON ticketsystem_ticket ( account_key, status );

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

