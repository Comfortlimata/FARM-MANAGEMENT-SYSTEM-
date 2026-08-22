CREATE DATABASE IF NOT EXISTS farm_management;
USE farm_management;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- --------------------------------------------------------
-- MODULE: Inventory
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS inventory_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    category ENUM('seed','fertilizer','chemical','feed','veterinary','packaging','fuel','tool','other') NOT NULL,
    unit VARCHAR(50),
    quantity DECIMAL(10,2) NOT NULL DEFAULT 0,
    reorder_level DECIMAL(10,2) NOT NULL DEFAULT 0,
    expiry_date DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- --------------------------------------------------------
-- MODULE: Equipment
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS equipment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    type ENUM('tractor','harvester','irrigation','sprayer','vehicle','hand_tool','power_tool','other') NOT NULL,
    status ENUM('operational','under_maintenance','out_of_service') NOT NULL DEFAULT 'operational',
    purchase_date DATE DEFAULT NULL,
    last_service_date DATE DEFAULT NULL,
    next_service_date DATE DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- --------------------------------------------------------
-- MODULE: Labour
-- Add labour-related tables here
-- --------------------------------------------------------

-- --------------------------------------------------------
-- MODULE: Pest & Disease
-- Add pest and disease-related tables here
-- --------------------------------------------------------

-- --------------------------------------------------------
-- MODULE: Weather
-- Add weather-related tables here
-- --------------------------------------------------------

-- --------------------------------------------------------
-- MODULE: Harvest
-- Add harvest-related tables here
-- --------------------------------------------------------
