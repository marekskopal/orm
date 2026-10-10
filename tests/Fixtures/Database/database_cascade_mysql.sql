DROP TABLE IF EXISTS `posts`;
DROP TABLE IF EXISTS `authors`;
CREATE TABLE `authors` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL
);

CREATE TABLE `posts` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `author_id` INT NOT NULL,
    FOREIGN KEY (`author_id`) REFERENCES `authors` (`id`)
)
