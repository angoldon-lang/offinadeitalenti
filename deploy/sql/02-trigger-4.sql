-- Officina dei Talenti — Passo 2.4: trigger trg_tsd_locked_del
-- Generato il 02/09/2026 23:18
-- Incolla in phpMyAdmin (scheda SQL) e premi Esegui.

-- IMPORTANTE: incolla SOLO questo blocco, da solo, e premi Esegui.
-- Le righe DELIMITER servono perche' il corpo del trigger contiene punti e virgola.

DELIMITER $$
CREATE TRIGGER trg_tsd_locked_del BEFORE DELETE ON timesheet_days
FOR EACH ROW
BEGIN
  IF (SELECT status FROM timesheets WHERE id = OLD.timesheet_id) <> 'DRAFT'
     AND (SELECT admin_override FROM runtime_flags WHERE id = 1) = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La settimana non e'' in bozza: giornate non cancellabili.';
  END IF;
END$$
DELIMITER ;
