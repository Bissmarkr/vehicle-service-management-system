CREATE DATABASE IF NOT EXISTS vsms_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE vsms_db;

CREATE TABLE users (
 user_id INT AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(100) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('ADMIN','CUSTOMER','MECHANIC') NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE customers (
 customer_id INT AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNIQUE,
 full_name VARCHAR(150) NOT NULL,
 email VARCHAR(150) NOT NULL UNIQUE,
 phone VARCHAR(30) NOT NULL,
 address VARCHAR(255),
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE SET NULL
);

CREATE TABLE vehicles (
 vehicle_id INT AUTO_INCREMENT PRIMARY KEY,
 customer_id INT NOT NULL,
 registration_number VARCHAR(50) NOT NULL UNIQUE,
 make VARCHAR(100) NOT NULL,
 model VARCHAR(100) NOT NULL,
 manufacture_year YEAR,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE service_types (
 service_type_id INT AUTO_INCREMENT PRIMARY KEY,
 service_name VARCHAR(150) NOT NULL UNIQUE,
 description TEXT,
 price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
 estimated_duration_minutes INT,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE mechanics (
 mechanic_id INT AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNIQUE,
 full_name VARCHAR(150) NOT NULL,
 phone VARCHAR(30),
 email VARCHAR(150),
 specialization VARCHAR(150),
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE SET NULL
);

CREATE TABLE service_bookings (
 booking_id INT AUTO_INCREMENT PRIMARY KEY,
 customer_id INT NOT NULL,
 vehicle_id INT NOT NULL,
 service_type_id INT NOT NULL,
 preferred_date DATE NOT NULL,
 booking_status ENUM('PENDING','CONFIRMED','ASSIGNED','IN_PROGRESS','COMPLETED','CANCELLED') NOT NULL DEFAULT 'PENDING',
 customer_notes TEXT,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON UPDATE CASCADE ON DELETE RESTRICT,
 FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id) ON UPDATE CASCADE ON DELETE RESTRICT,
 FOREIGN KEY (service_type_id) REFERENCES service_types(service_type_id) ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE service_assignments (
 assignment_id INT AUTO_INCREMENT PRIMARY KEY,
 booking_id INT NOT NULL,
 mechanic_id INT NOT NULL,
 assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (booking_id) REFERENCES service_bookings(booking_id) ON UPDATE CASCADE ON DELETE CASCADE,
 FOREIGN KEY (mechanic_id) REFERENCES mechanics(mechanic_id) ON UPDATE CASCADE ON DELETE RESTRICT,
 UNIQUE KEY uq_booking_mechanic (booking_id, mechanic_id)
);

CREATE TABLE service_progress (
 progress_id INT AUTO_INCREMENT PRIMARY KEY,
 booking_id INT NOT NULL,
 mechanic_id INT,
 status ENUM('PENDING','IN_PROGRESS','COMPLETED') NOT NULL,
 work_description TEXT,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (booking_id) REFERENCES service_bookings(booking_id) ON UPDATE CASCADE ON DELETE CASCADE,
 FOREIGN KEY (mechanic_id) REFERENCES mechanics(mechanic_id) ON UPDATE CASCADE ON DELETE SET NULL
);

CREATE TABLE spare_parts (
 part_id INT AUTO_INCREMENT PRIMARY KEY,
 part_name VARCHAR(150) NOT NULL,
 stock_quantity INT NOT NULL DEFAULT 0,
 unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE service_parts_used (
 service_part_id INT AUTO_INCREMENT PRIMARY KEY,
 booking_id INT NOT NULL,
 part_id INT NOT NULL,
 quantity INT NOT NULL,
 unit_price DECIMAL(10,2) NOT NULL,
 total_price DECIMAL(10,2) GENERATED ALWAYS AS (quantity * unit_price) STORED,
 FOREIGN KEY (booking_id) REFERENCES service_bookings(booking_id) ON UPDATE CASCADE ON DELETE CASCADE,
 FOREIGN KEY (part_id) REFERENCES spare_parts(part_id) ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE service_history (
 history_id INT AUTO_INCREMENT PRIMARY KEY,
 vehicle_id INT NOT NULL,
 booking_id INT NOT NULL UNIQUE,
 service_date DATE NOT NULL,
 service_summary TEXT,
 final_status VARCHAR(50) NOT NULL DEFAULT 'COMPLETED',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id) ON UPDATE CASCADE ON DELETE RESTRICT,
 FOREIGN KEY (booking_id) REFERENCES service_bookings(booking_id) ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE invoices (
 invoice_id INT AUTO_INCREMENT PRIMARY KEY,
 booking_id INT NOT NULL UNIQUE,
 service_charge DECIMAL(10,2) NOT NULL DEFAULT 0.00,
 parts_charge DECIMAL(10,2) NOT NULL DEFAULT 0.00,
 total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
 invoice_status ENUM('UNPAID','PARTIALLY_PAID','PAID','CANCELLED') NOT NULL DEFAULT 'UNPAID',
 invoice_date DATE NOT NULL,
 FOREIGN KEY (booking_id) REFERENCES service_bookings(booking_id) ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE payments (
 payment_id INT AUTO_INCREMENT PRIMARY KEY,
 invoice_id INT NOT NULL,
 payment_amount DECIMAL(10,2) NOT NULL,
 payment_method ENUM('CASH','CARD','BANK_TRANSFER','OTHER') NOT NULL,
 payment_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 payment_status ENUM('PENDING','COMPLETED','FAILED','REFUNDED') NOT NULL DEFAULT 'COMPLETED',
 FOREIGN KEY (invoice_id) REFERENCES invoices(invoice_id) ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE INDEX idx_vehicle_customer ON vehicles(customer_id);
CREATE INDEX idx_booking_customer ON service_bookings(customer_id);
CREATE INDEX idx_booking_vehicle ON service_bookings(vehicle_id);
CREATE INDEX idx_booking_date ON service_bookings(preferred_date);
CREATE INDEX idx_booking_status ON service_bookings(booking_status);
CREATE INDEX idx_assignment_mechanic ON service_assignments(mechanic_id);
CREATE INDEX idx_progress_booking ON service_progress(booking_id);
CREATE INDEX idx_history_vehicle ON service_history(vehicle_id);
CREATE INDEX idx_payment_invoice ON payments(invoice_id);

-- Optional sample service types
INSERT INTO service_types (service_name, description, price, estimated_duration_minutes) VALUES
('Oil Change','Engine oil and oil filter replacement',5000.00,60),
('Full Service','Complete vehicle service',15000.00,180),
('Brake Service','Brake inspection and service',8000.00,120);
