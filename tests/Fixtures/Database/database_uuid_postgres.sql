DROP TABLE IF EXISTS uuid_children;
DROP TABLE IF EXISTS uuid_items;
CREATE TABLE uuid_items (
    id UUID NOT NULL PRIMARY KEY,
    name VARCHAR(255) NOT NULL
);

CREATE TABLE uuid_children (
    id UUID NOT NULL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    item_id UUID NOT NULL
)
