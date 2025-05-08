-- Create database
CREATE DATABASE IF NOT EXISTS coffin_ordering_system
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE coffin_ordering_system;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Coffins table
CREATE TABLE IF NOT EXISTS coffins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
  description TEXT,
    price DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(255),
    stock INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_price (price),
    INDEX idx_stock (stock)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Orders table
CREATE TABLE IF NOT EXISTS orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    coffin_id INT NOT NULL,
    order_number VARCHAR(20) NOT NULL UNIQUE,
    quantity INT NOT NULL DEFAULT 1,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'processing', 'delivered', 'cancelled') DEFAULT 'pending',
    is_paid BOOLEAN DEFAULT FALSE,
    delivery_date DATE NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (coffin_id) REFERENCES coffins(id) ON DELETE RESTRICT,
    INDEX idx_order_number (order_number),
    INDEX idx_status (status),
    INDEX idx_delivery_date (delivery_date),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payments table
CREATE TABLE IF NOT EXISTS payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'credit_card', 'bank_transfer') NOT NULL,
    transaction_id VARCHAR(100),
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_status (status),
    INDEX idx_payment_date (payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password resets table
CREATE TABLE IF NOT EXISTS password_resets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(100) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin user
INSERT INTO users (name, email, password, role) VALUES 
('Admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insert sample coffins
INSERT INTO coffins (name, description, price, stock) VALUES
('Standard Wooden Coffin', 'Traditional wooden coffin with elegant finish', 999.99, 10),
('Premium Mahogany Coffin', 'High-quality mahogany coffin with brass handles', 1999.99, 5),
('Eco-Friendly Bamboo Coffin', 'Environmentally friendly bamboo coffin', 799.99, 15),
('Luxury Velvet Coffin', 'Premium velvet-lined coffin with gold accents', 2999.99, 3),
('Simple Pine Coffin', 'Affordable pine coffin with basic finish', 499.99, 20);

-- Create triggers for order number generation
DELIMITER //
CREATE TRIGGER before_order_insert
BEFORE INSERT ON orders
FOR EACH ROW
BEGIN
    SET NEW.order_number = CONCAT('ORD-', DATE_FORMAT(NOW(), '%Y%m%d'), '-', LPAD(COALESCE((SELECT MAX(SUBSTRING_INDEX(order_number, '-', -1)) + 1 FROM orders), 1), 4, '0'));
END//
DELIMITER ;

-- Create view for order details
CREATE VIEW order_details AS
SELECT 
    o.id,
    o.order_number,
    o.user_id,
    u.name as customer_name,
    u.email as customer_email,
    o.coffin_id,
    c.name as coffin_name,
    c.price as unit_price,
    o.quantity,
    o.total_amount,
    o.status,
    o.is_paid,
    o.delivery_date,
    o.notes,
    o.created_at,
    o.updated_at
FROM orders o
JOIN users u ON o.user_id = u.id
JOIN coffins c ON o.coffin_id = c.id;

-- Create view for payment details
CREATE VIEW payment_details AS
SELECT 
    p.id,
    p.order_id,
    o.order_number,
    o.user_id,
    u.name as customer_name,
    p.amount,
    p.payment_method,
    p.transaction_id,
    p.status,
    p.payment_date,
    p.notes
FROM payments p
JOIN orders o ON p.order_id = o.id
JOIN users u ON o.user_id = u.id;
