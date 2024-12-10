DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `created_at` timestamp NOT NULL,
    `first_name` varchar(255) NOT NULL,
    `middle_name` varchar(255) NULL,
    `last_name` varchar(255) NOT NULL,
    `email` varchar(255) NOT NULL,
    `is_active` tinyint(1) NOT NULL,
    `type` TEXT NOT NULL,
    `address_id` tinyint(1) NOT NULL,
    `second_address_id` tinyint(1) NULL
);

DROP TABLE IF EXISTS `addresses`;
CREATE TABLE `addresses` (
    `id` int(11) NOT NULL,
    `street` varchar(255) NOT NULL,
    `city` varchar(255) NOT NULL,
    `country` varchar(255) NOT NULL,
    PRIMARY KEY (`id`)
);

INSERT INTO `addresses` (`id`, `street`, `city`, `country`) VALUES
    (1, '123 Main St', 'Springfield', 'USA'),
    (2, '456 Elm St', 'Shelbyville', 'USA');

INSERT INTO `users` (`id`, `created_at`, `first_name`, `middle_name`, `last_name`, `email`, `is_active`, `type`, `address_id`, `second_address_id`) VALUES
    (1, 1704067200, 'John', null, 'Doe', 'john.doe@example.com', 1, 'admin', 1, NULL),
    (2, 1704067200, 'Jane', 'Janet', 'Doe', 'jane.doe@example.com', 0, 'user', 2, NULL);
