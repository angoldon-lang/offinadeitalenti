-- Officina dei Talenti — Passo 2.1: trigger trg_ts_immutable
-- Generato il 02/09/2026 09:52
-- Incolla in phpMyAdmin (scheda SQL) e premi Esegui.

-- IMPORTANTE: incolla SOLO questo blocco, da solo, e premi Esegui.
-- Le righe DELIMITER servono perche' il corpo del trigger contiene punti e virgola.

DELIMITER $$
-- IMMUTABILITA' DEL DATO APPROVATO ------------------------------------------
-- Un time-sheet approvato e' la base di una fattura: non deve essere
-- modificabile da nessun percorso applicativo. L'unico varco e' il flag
-- runtime_flags.admin_override, che l'app alza solo per l'admin e traccia.
CREATE TRIGGER trg_ts_immutable BEFORE UPDATE ON timesheets
FOR EACH ROW
BEGIN
  IF OLD.status IN ('APPROVED','INVOICED','PAID')
     AND (SELECT admin_override FROM runtime_flags WHERE id = 1) = 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Time-sheet approvato: modifica non consentita senza override amministrativo.';
  END IF;
END$$
DELIMITER ;
