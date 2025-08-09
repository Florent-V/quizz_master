-- Vider la table user
DELETE FROM user;

-- Ajout des utilisateurs avec rôles
INSERT INTO user (email, password, roles, user_name, first_name, last_name, is_verified, created_at, updated_at)
VALUES
    ('user@omega.com', '$2y$13$iUb2JqiZ3CnQGwOAJpJjIebgCe5Aata1XXW88D6ZvyjWdXoBUIY.6', '["ROLE_USER"]', 'user', 'user', 'user', 1, NOW(), NOW()),
    ('admin@omega.com', '$2y$13$iUb2JqiZ3CnQGwOAJpJjIebgCe5Aata1XXW88D6ZvyjWdXoBUIY.6', '["ROLE_USER", "ROLE_ADMIN"]', 'admin', 'admin', 'admin', 1, NOW(), NOW()),
    ('superadmin@omega.com', '$2y$13$iUb2JqiZ3CnQGwOAJpJjIebgCe5Aata1XXW88D6ZvyjWdXoBUIY.6', '["ROLE_USER", "ROLE_ADMIN", "ROLE_SUPER_ADMIN"]', 'superadmin', 'superadmin', 'superadmin', 1, NOW(), NOW()),
    ('test@omega.com', '$2y$13$iUb2JqiZ3CnQGwOAJpJjIebgCe5Aata1XXW88D6ZvyjWdXoBUIY.6', '["ROLE_USER"]', 'user', 'user', 'user', 0, NOW(), NOW());

-- Vider la table difficulty
DELETE FROM difficulty;

-- Insertion des niveaux de difficulté
INSERT INTO difficulty (name, level, created_at, updated_at)
VALUES
    ('Très facile', 1, NOW(), NOW()),
    ('Facile', 2, NOW(), NOW()),
    ('Moyen', 3, NOW(), NOW()),
    ('Difficile', 4, NOW(), NOW()),
    ('Très difficile', 5, NOW(), NOW());
