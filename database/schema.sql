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
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS labour (
    id INT AUTO_INCREMENT PRIMARY KEY,
    worker_name VARCHAR(150) NOT NULL,
    role ENUM('permanent','casual','contractor') NOT NULL DEFAULT 'casual',
    task VARCHAR(200) NOT NULL,
    work_date DATE NOT NULL,
    hours_worked DECIMAL(5,2) NOT NULL DEFAULT 0,
    hourly_rate DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_pay DECIMAL(10,2) GENERATED ALWAYS AS (hours_worked * hourly_rate) STORED,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- --------------------------------------------------------
-- MODULE: Pest & Disease
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS pest_disease (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    type ENUM('pest','disease') NOT NULL,
    affected_crop VARCHAR(150) NOT NULL,
    severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'low',
    date_observed DATE NOT NULL,
    treatment VARCHAR(255) DEFAULT NULL,
    treatment_date DATE DEFAULT NULL,
    status ENUM('active','treated','resolved') NOT NULL DEFAULT 'active',
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- --------------------------------------------------------
-- MODULE: Weather
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS weather (
    id INT AUTO_INCREMENT PRIMARY KEY,
    record_date DATE NOT NULL,
    temperature_min DECIMAL(5,2) DEFAULT NULL,
    temperature_max DECIMAL(5,2) DEFAULT NULL,
    rainfall_mm DECIMAL(7,2) DEFAULT NULL,
    humidity_percent DECIMAL(5,2) DEFAULT NULL,
    wind_speed_kmh DECIMAL(6,2) DEFAULT NULL,
    condition ENUM('sunny','cloudy','rainy','stormy','windy','foggy','other') NOT NULL DEFAULT 'sunny',
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- --------------------------------------------------------
-- MODULE: Harvest
-- Add harvest-related tables here
-- --------------------------------------------------------
