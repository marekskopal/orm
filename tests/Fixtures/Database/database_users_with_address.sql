DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` int(11) NOT NULL,
    `first_name` varchar(255) NOT NULL,
    `last_name` varchar(255) NOT NULL,
    `email` varchar(255) NOT NULL,
    `is_active` tinyint(1) NOT NULL,
    `address_id` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`)
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

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `is_active`, `address_id`) VALUES
    (1, 'John', 'Doe', 'john.doe@example.com', 1, 1),
    (2, 'Jane', 'Doe', 'jane.doe@example.com', 0, 2);
