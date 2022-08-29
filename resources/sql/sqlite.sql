-- #! sqlite

-- #{ init
-- #    { minecraft
CREATE TABLE IF NOT EXISTS minecraft(
    uuid BINARY(16) NOT NULL UNIQUE PRIMARY KEY,
    username VARCHAR(64) NOT NULL,
    last_login DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
-- #    }
-- #    { links
CREATE TABLE IF NOT EXISTS links(
    dcid VARCHAR(20) NOT NULL UNIQUE PRIMARY KEY,
    uuid BINARY(16) NOT NULL UNIQUE REFERENCES minecraft(uuid) ON DELETE CASCADE,
    created_on DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
-- #    }
-- #    { codes
CREATE TABLE IF NOT EXISTS codes(
    code VARCHAR(16) NOT NULL UNIQUE PRIMARY KEY,
    uuid BINARY(16) NOT NULL UNIQUE REFERENCES minecraft(uuid) ON DELETE CASCADE,
    expiry DATETIME NOT NULL
);
-- #    }
-- #}