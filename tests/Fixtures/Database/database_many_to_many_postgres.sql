DROP TABLE IF EXISTS user_tags;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS tags;

CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL
);

CREATE TABLE tags (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL
);

CREATE TABLE user_tags (
    user_id INTEGER NOT NULL,
    tag_id INTEGER NOT NULL,
    PRIMARY KEY (user_id, tag_id)
);

INSERT INTO users (id, name) VALUES (1, 'John'), (2, 'Jane');
INSERT INTO tags (id, name) VALUES (1, 'php'), (2, 'orm'), (3, 'database');
INSERT INTO user_tags (user_id, tag_id) VALUES (1, 1), (1, 2), (2, 2), (2, 3);

SELECT setval(pg_get_serial_sequence('users', 'id'), 2);
SELECT setval(pg_get_serial_sequence('tags', 'id'), 3);
