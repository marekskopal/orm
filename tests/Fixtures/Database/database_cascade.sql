DROP TABLE IF EXISTS `posts`;
DROP TABLE IF EXISTS `authors`;

CREATE TABLE `authors` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `name` varchar(255) NOT NULL
);

CREATE TABLE `posts` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `title` varchar(255) NOT NULL,
    `author_id` int(11) NOT NULL
)
