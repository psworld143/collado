-- Create database
CREATE DATABASE IF NOT EXISTS collado;
USE collado;

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    role ENUM('user', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create coffins table
CREATE TABLE IF NOT EXISTS coffins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create orders table
CREATE TABLE IF NOT EXISTS orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    coffin_id INT NOT NULL,
    order_number VARCHAR(20) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    total_amount DECIMAL(10,2) NOT NULL,
    delivery_date DATE NOT NULL,
    delivery_address TEXT NOT NULL,
    notes TEXT,
    payment_status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
    delivery_status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (coffin_id) REFERENCES coffins(id)
);

-- Insert test admin user
INSERT INTO users (username, password, name, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin User', 'admin');

-- Insert test coffins
INSERT INTO coffins (name, description, price, stock_quantity, image) VALUES 
('Basic Wooden Coffin', 'Simple and elegant wooden coffin', 15000.00, 10, 'coffins/basic-wooden.jpg'),
('Premium Mahogany Coffin', 'High-quality mahogany coffin with intricate designs', 25000.00, 5, 'coffins/premium-mahogany.jpg'),
('Deluxe Metal Casket', 'Durable metal casket with premium finish', 35000.00, 3, 'coffins/deluxe-metal.jpg'); 