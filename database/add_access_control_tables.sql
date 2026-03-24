-- RBAC dla startowa (CBA)
-- Uruchom w phpMyAdmin na bazie, z której korzysta startowa.

CREATE TABLE IF NOT EXISTS startowa_roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(64) NOT NULL UNIQUE,
  `name` VARCHAR(120) NOT NULL,
  `description` VARCHAR(255) NULL,
  is_system TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS startowa_role_app_assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role_key VARCHAR(64) NOT NULL,
  app_key VARCHAR(64) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_startowa_role_app (role_key, app_key),
  KEY idx_startowa_app_role (app_key, role_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS startowa_user_app_assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  app_key VARCHAR(64) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_startowa_user_app (user_id, app_key),
  KEY idx_startowa_app_user (app_key, user_id),
  CONSTRAINT fk_startowa_user_app_user FOREIGN KEY (user_id) REFERENCES uzytkownicy(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO startowa_roles (`key`, `name`, `description`, is_system)
VALUES
  ('owner', 'Owner', 'Pelny dostep do panelu i aplikacji', 1),
  ('admin', 'Admin', 'Dostep administracyjny i zarzadzanie dostepami', 1),
  ('manager', 'Manager', 'Rozszerzony dostep operacyjny', 1),
  ('user', 'User', 'Standardowy dostep uzytkownika', 1),
  ('guest', 'Guest', 'Ograniczony dostep', 1)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`);

INSERT INTO startowa_role_app_assignments (role_key, app_key)
VALUES
  ('owner', 'dashboard'), ('owner', 'dj'), ('owner', 'optivio'), ('owner', 'taski'), ('owner', 'taskora'), ('owner', 'admin_panel'), ('owner', 'server_hub'),
  ('admin', 'dashboard'), ('admin', 'dj'), ('admin', 'optivio'), ('admin', 'taski'), ('admin', 'taskora'), ('admin', 'admin_panel'), ('admin', 'server_hub'),
  ('manager', 'dashboard'), ('manager', 'dj'), ('manager', 'optivio'), ('manager', 'taski'), ('manager', 'taskora'),
  ('user', 'dashboard'), ('user', 'optivio'), ('user', 'taski'),
  ('guest', 'dashboard')
ON DUPLICATE KEY UPDATE app_key = VALUES(app_key);
