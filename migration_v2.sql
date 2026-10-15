-- Migration V1 -> V2 (à exécuter UNE SEULE FOIS sur une base existante)
ALTER TABLE users ADD COLUMN role ENUM('user','admin') NOT NULL DEFAULT 'user' AFTER type_exploitation,
                  ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role;
ALTER TABLE settings ADD COLUMN auto_irrigation TINYINT(1) NOT NULL DEFAULT 0,
                     ADD COLUMN ventilation TINYINT UNSIGNED NOT NULL DEFAULT 50;
CREATE TABLE zones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL,
  name VARCHAR(60) NOT NULL, culture VARCHAR(60) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_user (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB;
ALTER TABLE devices ADD COLUMN zone_id INT UNSIGNED NULL AFTER user_id,
                    ADD COLUMN kind ENUM('mixte','temperature','humidite','air') NOT NULL DEFAULT 'mixte' AFTER zone_id,
                    ADD FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE SET NULL;
CREATE TABLE cameras (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  zone_id INT UNSIGNED NULL,
  name VARCHAR(60) NOT NULL,
  type ENUM('mjpeg','snapshot','video','device') NOT NULL DEFAULT 'mjpeg',
  url VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE forum_rooms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL,
  icon VARCHAR(40) NOT NULL DEFAULT 'bi-chat-dots-fill',
  description VARCHAR(160) NOT NULL DEFAULT ''
) ENGINE=InnoDB;

CREATE TABLE forum_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  content VARCHAR(1000) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_room (room_id, id),
  INDEX idx_user_date (user_id, created_at),
  FOREIGN KEY (room_id) REFERENCES forum_rooms(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO forum_rooms (name, icon, description) VALUES
 ('Général', 'bi-chat-dots-fill', 'Discussions libres entre membres'),
 ('Cultures', 'bi-flower1', 'Cacao, manioc, maïs, maraîchage'),
 ('Élevage', 'bi-egg-fried', 'Volailles, porcs, bétail'),
 ('Marché & prix', 'bi-cash-coin', 'Prix, vente, achat, coopératives');
