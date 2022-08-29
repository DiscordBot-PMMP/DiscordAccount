-- #! sqlite

-- #{ minecraft
-- #    { init
CREATE TABLE IF NOT EXISTS minecraft(
    uuid BINARY(16) NOT NULL UNIQUE PRIMARY KEY,
    username VARCHAR(64) NOT NULL,
    last_login DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
-- #    }
-- #    { insert
-- #      :uuid string
-- #      :username string
INSERT INTO minecraft(uuid, username) VALUES(:uuid, :username);
-- #    }
-- #}

-- #{ links
-- #    { init
CREATE TABLE IF NOT EXISTS links(
    dcid VARCHAR(20) NOT NULL UNIQUE PRIMARY KEY,
    uuid BINARY(16) NOT NULL UNIQUE REFERENCES minecraft(uuid) ON DELETE CASCADE,
    created_on DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
-- #    }
-- #    { insert
-- #      :dcid string
-- #      :uuid string
INSERT INTO links(dcid, uuid) VALUES(:dcid, :uuid);
-- #    }
-- #}

-- #{ codes
-- #    { init
CREATE TABLE IF NOT EXISTS codes(
    code VARCHAR(16) NOT NULL UNIQUE PRIMARY KEY,
    uuid BINARY(16) NOT NULL UNIQUE REFERENCES minecraft(uuid) ON DELETE CASCADE,
    expiry DATETIME NOT NULL
);
-- #    }
-- #    { insert
-- #      :code string
-- #      :uuid string
-- #      :expiry timestamp
INSERT INTO codes(code, uuid, expiry) VALUES(:code, :uuid, :expiry);
-- #    }
-- #}