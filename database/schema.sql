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
-- Add inventory-related tables here
-- --------------------------------------------------------

-- --------------------------------------------------------
-- MODULE: Equipment
-- Add equipment-related tables here
-- --------------------------------------------------------

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
