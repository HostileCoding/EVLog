CREATE DATABASE IF NOT EXISTS volvo_trips
CHARACTER SET utf8mb4
COLLATE utf8mb4_general_ci;

USE volvo_trips;

--
-- Table: charges
--

CREATE TABLE charges (
  id INT NOT NULL AUTO_INCREMENT,
  charge_date DATETIME NOT NULL,
  kwh_amount DECIMAL(10,2) NOT NULL,
  cost DECIMAL(10,2) NOT NULL,
  notes TEXT DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;

--
-- Table: trips
--

CREATE TABLE trips (
  id INT NOT NULL AUTO_INCREMENT,
  category VARCHAR(50) DEFAULT NULL,
  start_time DATETIME NOT NULL,
  start_odometer INT DEFAULT NULL,
  start_address TEXT DEFAULT NULL,
  end_time DATETIME DEFAULT NULL,
  end_odometer INT DEFAULT NULL,
  end_address TEXT DEFAULT NULL,
  duration_minutes INT DEFAULT NULL,
  distance_km FLOAT DEFAULT NULL,
  consumption_kwh FLOAT DEFAULT NULL,
  title VARCHAR(255) DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  is_favorite TINYINT(1) DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;