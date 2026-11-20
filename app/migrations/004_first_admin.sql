-- Identifier le premier administrateur des installations v1 sans modifier les comptes.
CREATE TABLE first_admin (
    slot INTEGER PRIMARY KEY CHECK (slot = 1),
    user_id INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE RESTRICT
);

INSERT INTO first_admin (slot, user_id)
SELECT 1, id FROM users WHERE role = 'admin' AND state = 'active' ORDER BY id LIMIT 1;

CREATE TRIGGER users_record_first_admin
AFTER INSERT ON users WHEN NEW.role = 'admin' AND NEW.state = 'active'
BEGIN
    INSERT OR IGNORE INTO first_admin (slot, user_id) VALUES (1, NEW.id);
END;

PRAGMA user_version = 4;
