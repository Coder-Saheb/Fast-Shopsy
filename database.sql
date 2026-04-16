-- ============================================
-- FAST SHOPSY - Complete Database Schema
-- Import this file in phpMyAdmin
-- ============================================

USE if0_41502847_fastshopsy;

-- ============================================
-- USERS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) DEFAULT NULL,
    gender ENUM('male', 'female') DEFAULT 'male',
    dob DATE DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT 'assets/male.png',
    google_id VARCHAR(255) DEFAULT NULL,
    auth_provider ENUM('local', 'google') DEFAULT 'local',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================
-- ADMIN TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default admin: username=admin, password=admin123
INSERT INTO admin (username, password) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi')
ON DUPLICATE KEY UPDATE id=id;

-- ============================================
-- PRODUCTS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    old_price DECIMAL(10,2) DEFAULT NULL,
    images TEXT NOT NULL COMMENT 'JSON array of image URLs',
    category VARCHAR(100) DEFAULT 'General',
    stock INT DEFAULT 100,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Sample products
INSERT INTO products (name, description, price, old_price, images, category) VALUES
('Floral Maxi Dress', 'Beautiful floral print maxi dress, perfect for summer outings. Lightweight fabric with comfortable fit.', 899, 1799, '["https://images.unsplash.com/photo-1572804013309-59a88b7e92f1?w=400","https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?w=400"]', 'Dresses'),
('Casual Denim Jacket', 'Classic denim jacket with modern cut. Versatile piece for any wardrobe.', 1299, 2499, '["https://images.unsplash.com/photo-1551028719-00167b16eac5?w=400","https://images.unsplash.com/photo-1548126032-079a0fb0099d?w=400"]', 'Jackets'),
('Boho Crop Top', 'Trendy bohemian crop top with embroidery details. Pair with high-waist jeans.', 499, 999, '["https://images.unsplash.com/photo-1554568218-0f1715e72254?w=400","https://images.unsplash.com/photo-1485462537746-965f33f4a5a2?w=400"]', 'Tops'),
('Wide Leg Trousers', 'Elegant wide-leg trousers in solid color. Office to evening wear.', 1099, 1899, '["https://images.unsplash.com/photo-1594938298603-c8148c4b4a5c?w=400","https://images.unsplash.com/photo-1506629082955-511b1aa562c8?w=400"]', 'Bottoms'),
('Silk Blouse', 'Luxurious silk blend blouse, soft and breathable. Perfect for formal occasions.', 1599, 2999, '["https://images.unsplash.com/photo-1564584217132-2271feaeb3c5?w=400","https://images.unsplash.com/photo-1503342217505-b0a15ec3261c?w=400"]', 'Tops'),
('Mini Skirt', 'Chic mini skirt with pleated design. Modern and stylish for everyday wear.', 699, 1299, '["https://images.unsplash.com/photo-1583496661160-fb5886a0aaaa?w=400","https://images.unsplash.com/photo-1619603364930-fb50df64c64c?w=400"]', 'Bottoms'),
('Knit Sweater', 'Cozy knit sweater in pastel tones. Warm and fashionable for cooler days.', 1199, 2199, '["https://images.unsplash.com/photo-1434389677669-e08b4cac3105?w=400","https://images.unsplash.com/photo-1576566588028-4147f3842f27?w=400"]', 'Sweaters'),
('Linen Co-ord Set', 'Matching linen top and trouser set. Breathable and effortlessly chic.', 1899, 3499, '["https://images.unsplash.com/photo-1509631179647-0177331693ae?w=400","https://images.unsplash.com/photo-1581044777550-4cfa60707c03?w=400"]', 'Sets');

-- ============================================
-- ORDERS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    address TEXT NOT NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL,
    postal_code VARCHAR(20) NOT NULL,
    payment_method VARCHAR(50) DEFAULT 'Cash On Delivery',
    quantity INT DEFAULT 1,
    total_price DECIMAL(10,2) NOT NULL,
    status ENUM('Order Placed','Packed','Shipped','Out For Delivery','Delivered') DEFAULT 'Order Placed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- ============================================
-- CART TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_cart_item (user_id, product_id)
);

-- ============================================
-- WISHLIST TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS wishlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_wishlist_item (user_id, product_id)
);

-- ============================================
-- BROADCASTS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS broadcasts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- BROADCAST READS TABLE (track who read what)
-- ============================================
CREATE TABLE IF NOT EXISTS broadcast_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    broadcast_id INT NOT NULL,
    user_id INT NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (broadcast_id) REFERENCES broadcasts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_read (broadcast_id, user_id)
);

-- ============================================
-- SUPPORT SETTINGS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS support_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    whatsapp_number VARCHAR(20) NOT NULL DEFAULT '917718570357',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO support_settings (whatsapp_number) VALUES ('917718570357')
ON DUPLICATE KEY UPDATE id=id;
