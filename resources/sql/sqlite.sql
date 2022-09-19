-- #! sqlite

-- #{ minecraft
-- #    { init
CREATE TABLE IF NOT EXISTS minecraft(
    uuid BINARY(16) NOT NULL UNIQUE PRIMARY KEY,
    username VARCHAR(64) NOT NULL,
    last_online DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
-- #    }
-- #    { insert
-- #      :uuid string
-- #      :username string
INSERT OR IGNORE INTO minecraft(uuid, username) VALUES(:uuid, :username);
-- #    }
-- #    { update
-- #      :uuid string
-- #      :username string
-- #      :last_online int
UPDATE minecraft SET
    last_online = :last_online,
    username = :username
    WHERE uuid = :uuid;
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
INSERT OR IGNORE INTO links(dcid, uuid) VALUES(:dcid, :uuid);
-- #    }
-- #    { delete_dcid
-- #      :dcid string
DELETE FROM links WHERE dcid = :dcid;
-- #    }
-- #    { delete_uuid
-- #      :uuid string
DELETE FROM links WHERE uuid = :uuid;
-- #    }
-- #    { get_uuid
-- #      :uuid string
SELECT dcid FROM links WHERE uuid = :uuid;
-- #    }
-- #    { get_username
-- #      :username string
SELECT links.dcid FROM links INNER JOIN minecraft on links.uuid = minecraft.uuid WHERE minecraft.username = :username;
-- #    }
-- #    { get_dcid
-- #      :dcid string
SELECT minecraft.username, minecraft.uuid FROM links INNER JOIN minecraft on minecraft.uuid = links.uuid WHERE links.dcid = :dcid;
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
-- #      :expiry int
INSERT OR REPLACE INTO codes(code, uuid, expiry) VALUES(:code, :uuid, :expiry);
-- #    }
-- #    { get
-- #      :code string
SELECT uuid, expiry FROM codes WHERE code = :code;
-- #    }
-- #    { delete
-- #      :code string
DELETE FROM codes WHERE code = :code;
-- #    }
-- #    { clean
-- #      :now int
DELETE FROM codes WHERE expiry < :now;
-- #    }
-- #}