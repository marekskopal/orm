DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` int(11) NOT NULL,
    `first_name` varchar(255) NOT NULL,
    `last_name` varchar(255) NOT NULL,
    `email` varchar(255) NOT NULL,
    `is_active` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`)
);

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `is_active`) VALUES
    (1, 'John', 'Doe', 'john.doe@example.com', 1),
    (2, 'Jane', 'Doe', 'jane.doe@example.com', 0);
