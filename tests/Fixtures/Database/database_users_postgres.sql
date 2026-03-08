DROP TABLE IF EXISTS users;
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    created_at TIMESTAMP NOT NULL,
    first_name VARCHAR(255) NOT NULL,
    middle_name VARCHAR(255) NULL,
    last_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    is_active SMALLINT NOT NULL,
    type TEXT NOT NULL
);

INSERT INTO users (id, created_at, first_name, middle_name, last_name, email, is_active, type) VALUES
    (1, '2024-01-01 00:00:00', 'John', null, 'Doe', 'john.doe@example.com', 1, 'admin'),
    (2, '2024-01-01 00:00:00', 'Jane', 'Janet', 'Doe', 'jane.doe@example.com', 0, 'user');

SELECT setval(pg_get_serial_sequence('users', 'id'), 2);
