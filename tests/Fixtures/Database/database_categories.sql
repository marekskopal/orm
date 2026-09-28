DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `name` varchar(255) NOT NULL,
    `parent_id` int(11) NULL
);

INSERT INTO `categories` (`id`, `name`, `parent_id`) VALUES
    (1, 'Root', NULL),
    (2, 'Books', 1),
    (3, 'Novels', 2),
    (4, 'Music', 1);
