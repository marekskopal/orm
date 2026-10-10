DROP TABLE IF EXISTS `passports`;
DROP TABLE IF EXISTS `citizens`;
CREATE TABLE `citizens` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `name` varchar(255) NOT NULL
);

CREATE TABLE `passports` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `number` varchar(255) NOT NULL,
    `citizen_id` int(11) NOT NULL
);

INSERT INTO `citizens` (`id`, `name`) VALUES
    (1, 'John'),
    (2, 'Jane'),
    (3, 'Stateless');

INSERT INTO `passports` (`id`, `number`, `citizen_id`) VALUES
    (1, 'P-001', 1),
    (2, 'P-002', 2)
