DROP TABLE IF EXISTS `user_tags`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `tags`;

CREATE TABLE `users` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `name` varchar(255) NOT NULL
);

CREATE TABLE `tags` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `name` varchar(255) NOT NULL
);

CREATE TABLE `user_tags` (
    `user_id` int(11) NOT NULL,
    `tag_id` int(11) NOT NULL,
    PRIMARY KEY (`user_id`, `tag_id`)
);

INSERT INTO `users` (`id`, `name`) VALUES
    (1, 'John'),
    (2, 'Jane');

INSERT INTO `tags` (`id`, `name`) VALUES
    (1, 'php'),
    (2, 'orm'),
    (3, 'database');

INSERT INTO `user_tags` (`user_id`, `tag_id`) VALUES
    (1, 1),
    (1, 2),
    (2, 2),
    (2, 3)
