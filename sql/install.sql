CREATE TABLE {prefix}ib_players (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  alias_name varchar(41) NOT NULL DEFAULT '',
  real_name varchar(41) NOT NULL DEFAULT '',
  ship_name varchar(41) NOT NULL DEFAULT '',
  ship_type varchar(40) NOT NULL DEFAULT 'freetrader',
  sector_id int(11) NOT NULL DEFAULT 1,
  prev_sector_id int(11) NOT NULL DEFAULT 0,
  docked_port_id bigint(20) unsigned NOT NULL DEFAULT 0,
  landed_planet_id bigint(20) unsigned NOT NULL DEFAULT 0,
  fighters int(11) NOT NULL DEFAULT 0,
  shield_points int(11) NOT NULL DEFAULT 0,
  cargo_holds int(11) NOT NULL DEFAULT 20,
  ore int(11) NOT NULL DEFAULT 0,
  organics int(11) NOT NULL DEFAULT 0,
  equipment int(11) NOT NULL DEFAULT 0,
  colonists int(11) NOT NULL DEFAULT 0,
  worldseeds int(11) NOT NULL DEFAULT 0,
  survey_drones int(11) NOT NULL DEFAULT 0,
  credits bigint(20) NOT NULL DEFAULT 0,
  experience int(11) NOT NULL DEFAULT 0,
  alignment int(11) NOT NULL DEFAULT 0,
  team_id bigint(20) unsigned NOT NULL DEFAULT 0,
  turns_remaining int(11) NOT NULL DEFAULT 0,
  last_turn_reset date DEFAULT NULL,
  kills int(11) NOT NULL DEFAULT 0,
  deaths int(11) NOT NULL DEFAULT 0,
  created_at datetime DEFAULT NULL,
  last_seen datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY user_id (user_id),
  KEY sector_id (sector_id),
  KEY team_id (team_id)
) {charset_collate};
CREATE TABLE {prefix}ib_sectors (
  id int(11) NOT NULL,
  x float NOT NULL DEFAULT 0,
  y float NOT NULL DEFAULT 0,
  nebula varchar(60) NOT NULL DEFAULT '',
  is_core tinyint(1) NOT NULL DEFAULT 0,
  beacon varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY  (id)
) {charset_collate};
CREATE TABLE {prefix}ib_warps (
  from_sector int(11) NOT NULL,
  to_sector int(11) NOT NULL,
  PRIMARY KEY  (from_sector,to_sector),
  KEY to_sector (to_sector)
) {charset_collate};
CREATE TABLE {prefix}ib_ports (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  sector_id int(11) NOT NULL,
  port_name varchar(100) NOT NULL DEFAULT '',
  port_class tinyint(3) NOT NULL DEFAULT 1,
  ore_qty int(11) NOT NULL DEFAULT 0,
  ore_max int(11) NOT NULL DEFAULT 0,
  org_qty int(11) NOT NULL DEFAULT 0,
  org_max int(11) NOT NULL DEFAULT 0,
  equ_qty int(11) NOT NULL DEFAULT 0,
  equ_max int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  UNIQUE KEY sector_id (sector_id)
) {charset_collate};
CREATE TABLE {prefix}ib_planets (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  sector_id int(11) NOT NULL,
  planet_name varchar(100) NOT NULL DEFAULT '',
  planet_class char(1) NOT NULL DEFAULT 'V',
  owner_player_id bigint(20) unsigned NOT NULL DEFAULT 0,
  team_id bigint(20) unsigned NOT NULL DEFAULT 0,
  colonists int(11) NOT NULL DEFAULT 0,
  ore int(11) NOT NULL DEFAULT 0,
  organics int(11) NOT NULL DEFAULT 0,
  equipment int(11) NOT NULL DEFAULT 0,
  fighters int(11) NOT NULL DEFAULT 0,
  bastion_level tinyint(3) NOT NULL DEFAULT 0,
  bastion_vault bigint(20) NOT NULL DEFAULT 0,
  created_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY sector_id (sector_id),
  KEY owner_player_id (owner_player_id)
) {charset_collate};
CREATE TABLE {prefix}ib_teams (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  team_name varchar(41) NOT NULL DEFAULT '',
  password_hash varchar(255) NOT NULL DEFAULT '',
  captain_player_id bigint(20) unsigned NOT NULL DEFAULT 0,
  combat_medals int(11) NOT NULL DEFAULT 0,
  created_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY team_name (team_name)
) {charset_collate};
CREATE TABLE {prefix}ib_messages (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  sender_player_id bigint(20) unsigned NOT NULL DEFAULT 0,
  recipient_player_id bigint(20) unsigned NOT NULL DEFAULT 0,
  team_id bigint(20) unsigned NOT NULL DEFAULT 0,
  subject varchar(120) NOT NULL DEFAULT '',
  message_text text NOT NULL,
  is_read tinyint(1) NOT NULL DEFAULT 0,
  created_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY recipient_player_id (recipient_player_id),
  KEY team_id (team_id)
) {charset_collate};
CREATE TABLE {prefix}ib_fleets (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  faction varchar(50) NOT NULL DEFAULT '',
  owner_player_id bigint(20) unsigned NOT NULL DEFAULT 0,
  team_id bigint(20) unsigned NOT NULL DEFAULT 0,
  sector_id int(11) NOT NULL,
  fighter_count int(11) NOT NULL DEFAULT 0,
  fleet_mode varchar(20) NOT NULL DEFAULT 'offensive',
  created_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY sector_id (sector_id),
  KEY owner_player_id (owner_player_id)
) {charset_collate};
CREATE TABLE {prefix}ib_explored (
  player_id bigint(20) unsigned NOT NULL,
  sector_id int(11) NOT NULL,
  visited tinyint(1) NOT NULL DEFAULT 1,
  visited_at datetime DEFAULT NULL,
  PRIMARY KEY  (player_id,sector_id)
) {charset_collate};
CREATE TABLE {prefix}ib_news (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  event_type varchar(50) NOT NULL DEFAULT '',
  message text NOT NULL,
  created_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY created_at (created_at)
) {charset_collate};
CREATE TABLE {prefix}ib_admin_log (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  event_type varchar(50) NOT NULL DEFAULT '',
  message text NOT NULL,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime DEFAULT NULL,
  PRIMARY KEY  (id)
) {charset_collate};
