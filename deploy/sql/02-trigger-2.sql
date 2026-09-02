-- Officina dei Talenti — Passo 2.2: trigger trg_tsd_locked_ins
-- Generato il 02/09/2026 09:56
-- Incolla in phpMyAdmin (scheda SQL) e premi Esegui.

-- IMPORTANTE: incolla SOLO questo blocco, da solo, e premi Esegui.
-- Le righe DELIMITER servono perche' il corpo del trigger contiene punti e virgola.

DELIMITER $$
-- Le giornate seguono lo stato della settimana: scrivibili solo in bozza.
CREATE TRIGGER trg_tsd_locked_ins BEFORE INSERT ON timesheet_days
FOR EACH ROW
BEGIN
  IF (SELECT status FROM timesheets WHERE id = NEW.timesheet_id) <> 'DRAFT'
     AND (SELECT admin_override FROM runtime_flags WHERE id = 1) = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La settimana non e'' in bozza: giornate non modificabili.';
  END IF;
END$$
DELIMITER ;
