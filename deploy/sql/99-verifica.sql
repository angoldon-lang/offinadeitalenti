-- Officina dei Talenti — Verifica: cosa esiste davvero nel database
-- Generato il 02/09/2026 23:31
-- Incolla in phpMyAdmin (scheda SQL) e premi Esegui.

-- Esegui questa query per sapere a che punto sei. Funziona anche se il
-- database e' completamente vuoto.

SELECT
  (SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema = DATABASE())                                AS tabelle_trovate,
  16                                                                 AS tabelle_attese,
  (SELECT COUNT(*) FROM information_schema.triggers
     WHERE trigger_schema = DATABASE())                              AS trigger_trovati,
  4                                                                  AS trigger_attesi,
  (SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_name = 'skills')      AS tabella_skills_esiste;

-- Se tabella_skills_esiste vale 1, questa dice quante competenze ci sono
-- (attese: 42). Se vale 0, salta questa riga: il passo 1 non e' andato.
-- SELECT COUNT(*) AS competenze FROM skills;
