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
  PRIMARY KEY ( id ),
  UNIQUE KEY ticketsystem_ticket_number ( number ),
  UNIQUE KEY ticketsystem_ticket_object ( contentobject_id ),
  KEY ticketsystem_ticket_account ( account_key, status ),
  KEY ticketsystem_ticket_opener ( opener_id ),
  KEY ticketsystem_ticket_status ( status, modified )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ticketsystem_counter (
  prefix varchar(16) NOT NULL DEFAULT '',
  year int(11) NOT NULL DEFAULT '0',
  last_value int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY ( prefix, year )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
