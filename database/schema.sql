
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

CREATE TABLE expenses(
id INT AUTO_INCREMENT PRIMARY KEY,
title VARCHAR(200),
amount DECIMAL(10,2),
expense_date DATE
);
