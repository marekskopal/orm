DROP TABLE IF EXISTS users;
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    created_at TIMESTAMP NOT NULL,
    first_name VARCHAR(255) NOT NULL,
    middle_name VARCHAR(255) NULL,
    last_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    is_active SMALLINT NOT NULL,
    type TEXT NOT NULL,
    address_id INTEGER NOT NULL,
    second_address_id INTEGER NULL
);

DROP TABLE IF EXISTS addresses;
CREATE TABLE addresses (
    id SERIAL PRIMARY KEY,
    street VARCHAR(255) NOT NULL,
    city VARCHAR(255) NOT NULL,
    country VARCHAR(255) NOT NULL
);

INSERT INTO addresses (id, street, city, country) VALUES
    (1, '123 Main St', 'Springfield', 'USA'),
    (2, '456 Elm St', 'Shelbyville', 'USA');

SELECT setval(pg_get_serial_sequence('addresses', 'id'), 2);

INSERT INTO users (id, created_at, first_name, middle_name, last_name, email, is_active, type, address_id, second_address_id) VALUES
    (1, '2024-01-01 00:00:00', 'John', null, 'Doe', 'john.doe@example.com', 1, 'admin', 1, NULL),
    (2, '2024-01-01 00:00:00', 'Jane', 'Janet', 'Doe', 'jane.doe@example.com', 0, 'user', 2, NULL);

SELECT setval(pg_get_serial_sequence('users', 'id'), 2);
