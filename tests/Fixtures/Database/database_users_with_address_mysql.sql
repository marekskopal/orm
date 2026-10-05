DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `created_at` TIMESTAMP NOT NULL,
    `first_name` VARCHAR(255) NOT NULL,
    `middle_name` VARCHAR(255) NULL,
    `last_name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `is_active` TINYINT(1) NOT NULL,
    `type` TEXT NOT NULL,
    `address_id` INT NOT NULL,
    `second_address_id` INT NULL
);

DROP TABLE IF EXISTS `addresses`;
CREATE TABLE `addresses` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `street` VARCHAR(255) NOT NULL,
    `city` VARCHAR(255) NOT NULL,
    `country` VARCHAR(255) NOT NULL
);

INSERT INTO `addresses` (`id`, `street`, `city`, `country`) VALUES
    (1, '123 Main St', 'Springfield', 'USA'),
    (2, '456 Elm St', 'Shelbyville', 'USA');

INSERT INTO `users` (`id`, `created_at`, `first_name`, `middle_name`, `last_name`, `email`, `is_active`, `type`, `address_id`, `second_address_id`) VALUES
    (1, '2024-01-01 00:00:00', 'John', NULL, 'Doe', 'john.doe@example.com', 1, 'admin', 1, NULL),
    (2, '2024-01-01 00:00:00', 'Jane', 'Janet', 'Doe', 'jane.doe@example.com', 0, 'user', 2, NULL);
