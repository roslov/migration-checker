CREATE TABLE info (id INTEGER PRIMARY KEY, name TEXT NOT NULL);
CREATE TABLE owner (
    id INTEGER PRIMARY KEY AUTOINCREMENT, first_name TEXT NOT NULL, last_name TEXT NOT NULL, code TEXT NOT NULL
);
CREATE TABLE pet (
    id INTEGER PRIMARY KEY,
    type TEXT NOT NULL,
    owner_id INTEGER NOT NULL,
    info_id INTEGER NOT NULL,
    CONSTRAINT fk_owner FOREIGN KEY(owner_id) REFERENCES owner(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_info FOREIGN KEY(info_id) REFERENCES info(id)
        ON DELETE CASCADE ON UPDATE CASCADE
);
CREATE TABLE pet_tag (pet_id INTEGER NOT NULL, tag TEXT NOT NULL, PRIMARY KEY (pet_id, tag)) WITHOUT ROWID;
CREATE UNIQUE INDEX idx_owner_name ON owner(first_name, last_name);
CREATE INDEX idx_pet_type ON pet(type) WHERE type IS NOT NULL;
CREATE VIEW vw_pet AS SELECT id, type FROM pet;
CREATE TRIGGER before_owner_update
BEFORE UPDATE ON owner
BEGIN
    UPDATE owner SET last_name = 'Doe' WHERE id = NEW.id AND NEW.first_name = 'John';
END;
