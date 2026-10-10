DROP TABLE IF EXISTS `uuid_children`;
DROP TABLE IF EXISTS `uuid_items`;
CREATE TABLE `uuid_items` (
    `id` CHAR(36) NOT NULL PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL
);

CREATE TABLE `uuid_children` (
    `id` CHAR(36) NOT NULL PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `item_id` CHAR(36) NOT NULL
)
