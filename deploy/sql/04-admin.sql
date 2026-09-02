-- Officina dei Talenti — Passo 4: utente amministratore
-- Generato il 02/09/2026 09:52
-- Incolla in phpMyAdmin (scheda SQL) e premi Esegui.

-- Email:    angoldon@gmail.com
-- Password: RyyS7-oLGQN-EngED-em2Sy
-- Cambiala dopo il primo accesso e cancella questo file dal server.

INSERT INTO users (id, organization_id, email, password_hash, full_name, platform_role, org_role, is_active, created_at, updated_at)
VALUES ('dcc2f39d-d4d0-45f3-b2b7-d9f0e5dd94b3', NULL, 'angoldon@gmail.com', '$2y$12$d/v5sLi.Wr.kz7gmvMZ7tud.snLZsJXXb7hnYTA45vHUHetdb5nde', 'Amministratore', 'ADMIN', 'OWNER', 1, '2026-09-02 09:52:00', '2026-09-02 09:52:00');
