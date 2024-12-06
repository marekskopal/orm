DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `created_at` timestamp NOT NULL,
    `first_name` varchar(255) NOT NULL,
    `middle_name` varchar(255) NULL,
    `last_name` varchar(255) NOT NULL,
    `email` varchar(255) NOT NULL,
    `is_active` tinyint(1) NOT NULL

);

INSERT INTO `users` (`id`, `created_at`, `first_name`, `middle_name`, `last_name`, `email`, `is_active`) VALUES
    (1, 1704067200, 'John', null, 'Doe', 'john.doe@example.com', 1),
    (2, 1704067200, 'Jane', 'Janet', 'Doe', 'jane.doe@example.com', 0);
