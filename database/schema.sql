
CREATE TABLE clients(
id INT AUTO_INCREMENT PRIMARY KEY,
name VARCHAR(200),
phone VARCHAR(50),
address TEXT,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE equipment(
id INT AUTO_INCREMENT PRIMARY KEY,
name VARCHAR(200),
quantity INT,
price_day DECIMAL(10,2),
status VARCHAR(50) DEFAULT 'available'
);

CREATE TABLE contracts(
id INT AUTO_INCREMENT PRIMARY KEY,
client_id INT,
start_date DATE,
end_date DATE,
total DECIMAL(10,2),
status VARCHAR(50) DEFAULT 'active'
);

CREATE TABLE contract_items(
id INT AUTO_INCREMENT PRIMARY KEY,
contract_id INT,
equipment_id INT,
qty INT,
price DECIMAL(10,2)
);

CREATE TABLE payments(
id INT AUTO_INCREMENT PRIMARY KEY,
contract_id INT,
amount DECIMAL(10,2),
payment_date DATE,
method VARCHAR(50)
);

CREATE TABLE employees(
id INT AUTO_INCREMENT PRIMARY KEY,
name VARCHAR(200),
phone VARCHAR(50),
salary DECIMAL(10,2)
);

CREATE TABLE invoices(
id INT AUTO_INCREMENT PRIMARY KEY,
invoice_number VARCHAR(30) NOT NULL UNIQUE,
contract_id INT NOT NULL,
issue_date DATE NOT NULL,
total DECIMAL(12,2) NOT NULL DEFAULT 0,
status ENUM('active','cancelled') NOT NULL DEFAULT 'active',
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE expenses(
id INT AUTO_INCREMENT PRIMARY KEY,
category_id INT DEFAULT NULL,
title VARCHAR(200),
notes TEXT,
amount DECIMAL(10,2),
expense_date DATE
);

CREATE TABLE expense_categories(
id INT AUTO_INCREMENT PRIMARY KEY,
name VARCHAR(200) NOT NULL,
sort_order INT DEFAULT 0
);

CREATE TABLE settings(
name VARCHAR(100) PRIMARY KEY,
value TEXT
);
