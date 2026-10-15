-- AgriElv Smart AI - Base MySQL 8 / MariaDB 10.5+
-- Import : choisir d'abord la base cible (phpMyAdmin > clic sur la base > Importer).
-- En local, créer la base avant : CREATE DATABASE agrielv CHARACTER SET utf8mb4; puis mysql agrielv < database.sql

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(80) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  type_exploitation ENUM('agriculture','elevage','mixte') NOT NULL DEFAULT 'agriculture',
  role ENUM('user','admin') NOT NULL DEFAULT 'user',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE settings (
  user_id INT UNSIGNED PRIMARY KEY,
  alert_temp_max DECIMAL(4,1) NOT NULL DEFAULT 32.0,
  alert_humidity_min TINYINT UNSIGNED NOT NULL DEFAULT 40,
  alert_motion TINYINT(1) NOT NULL DEFAULT 1,
  theme ENUM('light','dark') NOT NULL DEFAULT 'light',
  auto_irrigation TINYINT(1) NOT NULL DEFAULT 0,
  ventilation TINYINT UNSIGNED NOT NULL DEFAULT 50,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE zones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  name VARCHAR(60) NOT NULL,
  culture VARCHAR(60) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE devices (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  zone_id INT UNSIGNED NULL,
  kind ENUM('mixte','temperature','humidite','air') NOT NULL DEFAULT 'mixte',
  name VARCHAR(80) NOT NULL DEFAULT 'Capteur principal',
  api_key_hash CHAR(64) NOT NULL UNIQUE,
  last_seen DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE sensor_readings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  device_id INT UNSIGNED NULL,
  temperature DECIMAL(4,1) NOT NULL,
  soil_humidity DECIMAL(4,1) NOT NULL,
  air_quality TINYINT UNSIGNED NOT NULL,
  camera_online TINYINT(1) NOT NULL DEFAULT 1,
  motion TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_date (user_id, created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE alerts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  type VARCHAR(20) NOT NULL,
  message VARCHAR(255) NOT NULL,
  severity ENUM('faible','moyenne','critique') NOT NULL DEFAULT 'faible',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_date (user_id, created_at),
  INDEX idx_user_type (user_id, type, created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE reviews (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  comment VARCHAR(500) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_date (created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE chat_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  role ENUM('user','assistant') NOT NULL,
  content TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_date (user_id, created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ip (ip, created_at),
  INDEX idx_email (email, created_at)
) ENGINE=InnoDB;

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
