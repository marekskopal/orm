DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `name` varchar(255) NOT NULL,
    `profile_id` int(11) NOT NULL
);

DROP TABLE IF EXISTS `profiles`;
CREATE TABLE `profiles` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `bio` varchar(255) NOT NULL
);

INSERT INTO `profiles` (`id`, `bio`) VALUES
    (1, 'Hello, I am John'),
    (2, 'Hello, I am Jane');

INSERT INTO `users` (`id`, `name`, `profile_id`) VALUES
    (1, 'John', 1),
    (2, 'Jane', 2)
