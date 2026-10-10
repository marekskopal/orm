DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS profiles;
CREATE TABLE profiles (
    id SERIAL PRIMARY KEY,
    bio VARCHAR(255) NOT NULL
);

CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    profile_id INT NOT NULL
);

INSERT INTO profiles (id, bio) VALUES
    (1, 'Hello, I am John'),
    (2, 'Hello, I am Jane'),
    (3, 'Nobody');

INSERT INTO users (id, name, profile_id) VALUES
    (1, 'John', 1),
    (2, 'Jane', 2);

SELECT setval(pg_get_serial_sequence('profiles', 'id'), 3);
SELECT setval(pg_get_serial_sequence('users', 'id'), 2)
