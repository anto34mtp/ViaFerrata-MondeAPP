-- ============================================================
-- Script de migration pour le nouvel hébergeur
-- À exécuter UNE SEULE FOIS sur la nouvelle base de données
-- ============================================================

-- 1. Vue via_ratings_summary (nécessaire pour les pages favoris, dashboard, etc.)
CREATE OR REPLACE VIEW via_ratings_summary AS
SELECT
    via_id,
    COUNT(*)                                                                              AS total_ratings,
    ROUND(AVG(rating_general), 1)                                                         AS avg_general,
    ROUND(AVG(rating_beauty), 1)                                                          AS avg_beauty,
    ROUND(AVG(rating_difficulty), 1)                                                      AS avg_difficulty,
    ROUND((AVG(rating_general) + AVG(rating_beauty) + AVG(rating_difficulty)) / 3, 1)    AS avg_overall
FROM ratings
GROUP BY via_id;

-- 2. Table road_trips (fonctionnalité Road Trip)
CREATE TABLE IF NOT EXISTS road_trips (
    id          INT          NOT NULL AUTO_INCREMENT,
    user_id     INT          NOT NULL,
    name        VARCHAR(255) NOT NULL,
    description TEXT,
    start_date  DATE,
    end_date    DATE,
    nb_days     INT          NOT NULL DEFAULT 1,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user (user_id),
    CONSTRAINT fk_rt_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Table road_trip_vias (vias ajoutées aux road trips)
CREATE TABLE IF NOT EXISTS road_trip_vias (
    id         INT       NOT NULL AUTO_INCREMENT,
    trip_id    INT       NOT NULL,
    via_id     INT       NOT NULL,
    day_number INT       NOT NULL DEFAULT 1,
    position   INT       NOT NULL DEFAULT 0,
    notes      TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_trip (trip_id),
    CONSTRAINT fk_rtv_trip FOREIGN KEY (trip_id) REFERENCES road_trips (id) ON DELETE CASCADE,
    CONSTRAINT fk_rtv_via  FOREIGN KEY (via_id)  REFERENCES vias (id)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Table road_trip_shares (partage de road trips entre utilisateurs)
CREATE TABLE IF NOT EXISTS road_trip_shares (
    id           INT          NOT NULL AUTO_INCREMENT,
    trip_id      INT          NOT NULL,
    shared_by    INT          NOT NULL,
    shared_with  INT,
    invite_email VARCHAR(255),
    invite_token VARCHAR(64),
    accepted_at  TIMESTAMP    NULL DEFAULT NULL,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_share (trip_id, shared_with),
    UNIQUE KEY uq_invite_token (invite_token),
    CONSTRAINT fk_rts_trip   FOREIGN KEY (trip_id)     REFERENCES road_trips (id) ON DELETE CASCADE,
    CONSTRAINT fk_rts_by     FOREIGN KEY (shared_by)   REFERENCES users (id)      ON DELETE CASCADE,
    CONSTRAINT fk_rts_with   FOREIGN KEY (shared_with) REFERENCES users (id)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Table logbook_entries (journal de sorties personnelles)
CREATE TABLE IF NOT EXISTS logbook_entries (
    id          INT          NOT NULL AUTO_INCREMENT,
    user_id     INT          NOT NULL,
    via_id      INT          NOT NULL,
    done_date   DATE,
    conditions  VARCHAR(255),
    companion   VARCHAR(255),
    notes       TEXT,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_via (user_id, via_id),
    CONSTRAINT fk_le_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_le_via  FOREIGN KEY (via_id)  REFERENCES vias (id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Table via_submissions (propositions de nouvelles vias)
CREATE TABLE IF NOT EXISTS via_submissions (
    id               INT          NOT NULL AUTO_INCREMENT,
    user_id          INT,
    ip_address       VARCHAR(45),
    name             VARCHAR(255) NOT NULL,
    part_number      TINYINT,
    total_parts      TINYINT,
    group_token      VARCHAR(64),
    location         VARCHAR(255),
    latitude         DECIMAL(10,7),
    longitude        DECIMAL(10,7),
    difficulty       TINYINT,
    duration_hours   DECIMAL(4,1),
    approach_time    SMALLINT,
    return_time      SMALLINT,
    elevation_gain   SMALLINT,
    description      TEXT,
    author_email     VARCHAR(255),
    status           ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_status (status),
    CONSTRAINT fk_vs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Table via_translations (cache des traductions automatiques)
CREATE TABLE IF NOT EXISTS via_translations (
    id            INT          NOT NULL AUTO_INCREMENT,
    via_id        INT          NOT NULL,
    lang          CHAR(2)      NOT NULL,
    name          VARCHAR(255),
    description   TEXT,
    translated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_via_lang (via_id, lang),
    CONSTRAINT fk_vt_via FOREIGN KEY (via_id) REFERENCES vias (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
