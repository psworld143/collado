-- Create database if not exists
CREATE DATABASE IF NOT EXISTS coffin_db;
USE coffin_db;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    UNIQUE KEY unique_email (email)
);

-- Coffin designs table
CREATE TABLE IF NOT EXISTS coffin_designs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    category ENUM('wood', 'metal', 'premium') NOT NULL,
    image VARCHAR(255),
    in_stock BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
);

-- Orders table
CREATE TABLE IF NOT EXISTS orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    coffin_id INT NOT NULL,
    customer_name VARCHAR(100) NOT NULL,
    delivery_date DATE NOT NULL,
    delivery_time ENUM('morning', 'afternoon', 'evening') NOT NULL,
    delivery_address TEXT NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    special_instructions TEXT,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
    order_status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (coffin_id) REFERENCES coffin_designs(id)
);

-- Insert sample coffin designs
INSERT INTO coffin_designs (name, description, price, category, in_stock) VALUES
('Classic Wooden Coffin', 'Traditional wooden coffin with elegant finish', 15000.00, 'wood', TRUE),
('Premium Metal Casket', 'High-quality metal casket with polished finish', 25000.00, 'metal', TRUE),
('Luxury Mahogany Coffin', 'Premium mahogany wood with intricate carvings', 35000.00, 'premium', TRUE),
('Standard Wooden Coffin', 'Simple and dignified wooden coffin', 12000.00, 'wood', TRUE),
('Modern Metal Casket', 'Contemporary design metal casket', 20000.00, 'metal', TRUE),
('Deluxe Premium Coffin', 'Exclusive design with premium materials', 40000.00, 'premium', TRUE);

-- Insert sample admin user (password: admin123)
INSERT INTO users (name, email, password, role) VALUES
('Admin User', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'); 