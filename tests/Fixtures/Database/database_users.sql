DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `first_name` varchar(255) NOT NULL,
    `middle_name` varchar(255) NULL,
    `last_name` varchar(255) NOT NULL,
    `email` varchar(255) NOT NULL,
    `is_active` tinyint(1) NOT NULL

);

INSERT INTO `users` (`id`, `first_name`, `middle_name`, `last_name`, `email`, `is_active`) VALUES
    (1, 'John', null, 'Doe', 'john.doe@example.com', 1),
    (2, 'Jane', 'Janet', 'Doe', 'jane.doe@example.com', 0);
