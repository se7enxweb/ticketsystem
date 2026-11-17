CREATE TABLE ticketsystem_ticket (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  contentobject_id integer NOT NULL DEFAULT 0,
  node_id integer NOT NULL DEFAULT 0,
  number varchar(32) NOT NULL DEFAULT '',
  prefix varchar(16) NOT NULL DEFAULT '',
  year integer NOT NULL DEFAULT 0,
  seq integer NOT NULL DEFAULT 0,
  account_key varchar(64) NOT NULL DEFAULT '',
  opener_id integer NOT NULL DEFAULT 0,
  subject varchar(255) NOT NULL DEFAULT '',
  status varchar(32) NOT NULL DEFAULT '',
  category varchar(64) NOT NULL DEFAULT '',
  priority varchar(32) NOT NULL DEFAULT '',
  subscription_id integer NOT NULL DEFAULT 0,
  post_count integer NOT NULL DEFAULT 0,
  last_post integer NOT NULL DEFAULT 0,
  last_post_by integer NOT NULL DEFAULT 0,
  last_post_staff integer NOT NULL DEFAULT 0,
  created integer NOT NULL DEFAULT 0,
  modified integer NOT NULL DEFAULT 0,
  resolved integer NOT NULL DEFAULT 0,
  closed integer NOT NULL DEFAULT 0
);
CREATE UNIQUE INDEX ticketsystem_ticket_number ON ticketsystem_ticket ( number );
CREATE UNIQUE INDEX ticketsystem_ticket_object ON ticketsystem_ticket ( contentobject_id );
CREATE INDEX ticketsystem_ticket_account ON ticketsystem_ticket ( account_key, status );
CREATE INDEX ticketsystem_ticket_opener ON ticketsystem_ticket ( opener_id );
CREATE INDEX ticketsystem_ticket_status ON ticketsystem_ticket ( status, modified );

CREATE TABLE ticketsystem_counter (
  prefix varchar(16) NOT NULL DEFAULT '',
  year integer NOT NULL DEFAULT 0,
  last_value integer NOT NULL DEFAULT 0,
  PRIMARY KEY ( prefix, year )
);

