# Messa online su Aruba — istruzioni passo passo

Tempo indicativo: **circa 2 ore**, di cui buona parte di attesa per l'attivazione del servizio.
Serve solo un browser e un programma FTP (FileZilla va benissimo). Niente riga di comando.

> Le voci del pannello Aruba cambiano nome nel tempo e fra i piani. Dove non sono certo del nome
> esatto indico **cosa cercare**, non un percorso da seguire alla lettera.

---

## Fase 1 — Attivare l'hosting (20 minuti + attesa)

### Passo 1.1 — Acquistare l'hosting

Dalla schermata che avevi aperto (*Modifica Servizio* su `tallerconsulting.it`), seleziona
**"Passare a Hosting Basic Linux" — 10 Euro** e conferma l'ordine.

Il dominio passa da "Dominio con Email" a "Hosting Linux": **la casella email resta**, si aggiunge
lo spazio web.

⏳ L'attivazione richiede da pochi minuti a qualche ora. Ricevi un'email da Aruba con i **dati FTP**
(host, username, password): conservala, servono al passo 3.2.

### Passo 1.2 — Impostare PHP 8.1 o superiore

Nel pannello, area del dominio → cerca **"Impostazioni PHP"** o **"Versione PHP"**.
Seleziona **PHP 8.2** (o 8.1/8.3). Salva.

> ⚠️ Questo passo non è facoltativo. Con PHP 8.0 o inferiore l'applicazione non parte: usa
> costrutti introdotti in 8.1.

### Passo 1.3 — Attivare il certificato SSL

Cerca **"SSL"** o **"Certificati"** → attiva il **Let's Encrypt gratuito** incluso.
Può richiedere fino a un'ora. Senza HTTPS il login viaggerebbe in chiaro.

### Passo 1.4 — Creare il database MySQL

Cerca **"Database"** → **crea un nuovo database MySQL**.

Annota queste quattro cose, servono al passo 2.2:

| Dato | Esempio |
|---|---|
| Host | `sql.tallerconsulting.it` |
| Nome database | `Sql1234567_1` |
| Utente | `Sql1234567` |
| Password | quella che hai scelto |

---

## Fase 2 — Preparare i file sul tuo computer (15 minuti)

### Passo 2.1 — Scaricare il codice

Vai su GitHub, branch `claude/resource-skill-sharing-portal-o6o7zt` → pulsante verde **Code** →
**Download ZIP**. Scompatta la cartella sul desktop.

### Passo 2.2 — Scrivere la configurazione

Nella cartella `config/`, **duplica** il file `config.example.php` e chiama la copia
**`config.local.php`**. Aprilo con un editor di testo (Blocco note va bene, non Word) e modifica
due sezioni:

```php
'app' => [
    'name'      => 'Officina dei Talenti',
    'env'       => 'production',        // ← era 'production', lascialo così
    'base_path' => '',
    'timezone'  => 'Europe/Rome',
],
'db' => [
    'driver'   => 'mysql',
    'host'     => 'sql.tallerconsulting.it',   // ← dal passo 1.4
    'port'     => 3306,
    'database' => 'Sql1234567_1',              // ← dal passo 1.4
    'username' => 'Sql1234567',                // ← dal passo 1.4
    'password' => 'la-tua-password-db',        // ← dal passo 1.4
    'charset'  => 'utf8mb4',
],
```

Salva. **Non caricare mai questo file su GitHub**: contiene la password del database.

---

## Fase 3 — Caricare i file (20 minuti)

### Passo 3.1 — Capire dove va cosa

Collegati in FTP e guarda la cartella in cui atterri: di solito
`/web/htdocs/www.tallerconsulting.it/home`. **Quella cartella è il sito.**

Cerca nel pannello Aruba se esiste un'opzione tipo *"cartella radice del sito"* o *"document root"*:

- **Se puoi cambiarla** → impostala su `public` e usa il **Layout A** (più sicuro).
- **Se non esiste** → usa il **Layout B**. Funziona ugualmente.

### Passo 3.2 — Layout A (document root su `public/`)

Carica **tutte** le cartelle così come sono:

```
home/
├── public/          ← document root
├── src/
├── views/
├── migrations/
├── bin/
├── config/          ← con dentro config.local.php
└── storage/
```

### Passo 3.2-bis — Layout B (document root fissa)

Carica il **contenuto** di `public/` direttamente nella cartella principale, e le altre cartelle
accanto:

```
home/
├── index.php            ← preso da public/
├── .htaccess            ← preso da public/
├── sw.js                ← preso da public/
├── manifest.webmanifest ← preso da public/
├── assets/              ← presa da public/
├── src/  views/  migrations/  bin/  config/  storage/
```

Il codice riconosce da solo il layout: non devi modificare nulla.

> Nel Layout B il codice sta sotto la cartella pubblica ed è protetto dai file `.htaccess`
> inclusi. Funziona, ma se un giorno il server smettesse di leggerli quei file diventerebbero
> raggiungibili. Se puoi scegliere, scegli il Layout A.

### Passo 3.3 — Permessi delle cartelle

Nel programma FTP, tasto destro su `storage/documents` e `storage/logs` → **Permessi file** →
imposta **755** (se il caricamento dei PDF poi fallisce, prova 775).

Verifica che dentro `storage/` sia arrivato anche il file **`.htaccess`**: molti programmi FTP
nascondono i file che iniziano con un punto. In FileZilla: *Server → Forza visualizzazione file
nascosti*.

---

## Fase 4 — Creare il database (25 minuti)

Apri **phpMyAdmin** dal pannello Aruba e seleziona il tuo database a sinistra.
Tutti i file da incollare sono nella cartella **`deploy/sql/`** del progetto.

### Passo 4.1 — Le tabelle

Apri `deploy/sql/01-schema.sql` con un editor di testo, **seleziona tutto, copia**.
In phpMyAdmin → scheda **SQL** → incolla → **Esegui**.

✅ Deve comparire "query eseguita correttamente". A sinistra vedrai **16 tabelle**.

### Passo 4.2 — I quattro trigger, uno alla volta

Questa è la parte in cui è facile sbagliare, quindi i trigger sono in file separati.

Per **ciascuno** dei file `02-trigger-1.sql`, `02-trigger-2.sql`, `02-trigger-3.sql`,
`02-trigger-4.sql`:

1. apri il file, seleziona tutto, copia;
2. in phpMyAdmin → scheda **SQL** → **cancella quello che c'è nel riquadro**;
3. incolla → **Esegui**;
4. passa al file successivo.

**Non incollarne due insieme.** I file contengono già le righe `DELIMITER` necessarie: lasciale.

✅ Verifica: phpMyAdmin → scheda **Trigger** (o *Struttura* → *Trigger*) → devono essercene **4**.

> A cosa servono: sono ciò che impedisce di modificare un time-sheet già approvato. Senza di
> essi l'applicazione funziona lo stesso, ma perde la garanzia che rende i rendiconti
> attendibili per la fatturazione. Non saltarli.

### Passo 4.3 — Le competenze

Apri `deploy/sql/03-skills.sql` → copia → incolla in phpMyAdmin → **Esegui**.
Inserisce le 42 competenze selezionabili (33 tecniche + 9 trasversali).

### Passo 4.4 — L'utente amministratore

Apri `deploy/sql/04-admin.sql`. In cima trovi email e password in chiaro.
Copia **tutto il file** → incolla in phpMyAdmin → **Esegui**.

📝 Annota le credenziali, poi **cancella il file dal tuo computer**.

---

## Fase 5 — Primo accesso (10 minuti)

### Passo 5.1 — Entrare

Apri `https://tallerconsulting.it/login` e accedi con le credenziali del passo 4.4.
Devi atterrare sul **back-office**.

### Passo 5.2 — Cambiare subito la password

In alto a destra, icona **👤** → sezione *Cambia password*.
La password generata era pensata per il file SQL, non per l'uso quotidiano.

### Passo 5.3 — Il cron giornaliero

Pannello Aruba → cerca **"Cron job"** o **"Operazioni pianificate"** → nuova attività:

| Campo | Valore |
|---|---|
| Comando | `/usr/bin/php /web/htdocs/www.tallerconsulting.it/home/bin/cron.php` |
| Frequenza | una volta al giorno, ore 03:00 |

Adatta il percorso a quello reale (lo vedi in FTP) e il percorso di `php` a quello indicato da
Aruba. Nel **Layout A** il percorso resta identico: `bin/` sta fuori da `public/`.

Il cron fa scadere gli account oltre la data che hai impostato, marca le fatture scadute e crea i
solleciti. Se non lo configuri, l'applicazione funziona comunque: semplicemente quelle tre cose non
avvengono da sole.

---

## Fase 6 — Verifiche (10 minuti)

Spunta una per una:

- [ ] `https://tallerconsulting.it` si apre e il lucchetto HTTPS è verde
- [ ] `http://` (senza s) reindirizza automaticamente a `https://`
- [ ] il login amministratore funziona
- [ ] `https://tallerconsulting.it/config/config.local.php` restituisce **403 o 404**
      — se mostra del testo, fermati: la configurazione è esposta (rivedi il passo 3.3)
- [ ] `https://tallerconsulting.it/storage/documents/` restituisce **403 o 404**
- [ ] dal telefono, il browser propone **"Aggiungi a schermata Home"**
- [ ] il giorno dopo, il cron risulta eseguito nel pannello

---

## Fase 7 — Primo utilizzo reale

L'ordine conta: ogni passo abilita il successivo.

1. **Registra il primo offerente.** Fagli aprire `https://tallerconsulting.it/registrati?tipo=offerente`.
   Resta in attesa di attivazione: è previsto.
2. **Attivalo.** Back-office → *Organizzazioni* → apri la scheda → imposta **"Attivo fino al"**
   (es. un anno) e il riferimento al contratto cartaceo → *Attiva account*.
3. **L'offerente carica le risorse** e le invia in approvazione.
4. **Tu le moderi.** Back-office → *Moderazione* → approva o rifiuta con motivazione.
   Solo dopo l'approvazione il profilo compare nelle ricerche.
5. **Registra e attiva il primo richiedente** allo stesso modo (`?tipo=richiedente`).
6. **Il richiedente cerca e invia una richiesta**; l'offerente accetta e le identità si svelano.
7. **Crea il contratto** (dall'area offerente o dal back-office) con la **tariffa concordata**,
   mettilo in stato **Attivo** e carica il PDF firmato.
8. **Da qui parte la rendicontazione**: ogni settimana l'offerente compila, il richiedente approva.
9. **A fine mese**, l'offerente apre *Da fatturare*, seleziona le settimane approvate, crea la
   fattura e carica il PDF. Tu aggiorni lo stato del pagamento quando incassi.

---

## Se qualcosa non funziona

| Sintomo | Causa quasi certa | Rimedio |
|---|---|---|
| Pagina bianca | Errore PHP nascosto | Apri `storage/logs/php-error.log` via FTP |
| "Configurazione non trovata" | Manca `config/config.local.php` | Passo 2.2 |
| Errore di connessione al database | Credenziali sbagliate | Ricontrolla i 4 valori del passo 1.4 |
| Errore 500 su ogni pagina | Versione PHP troppo vecchia | Passo 1.2, imposta 8.1+ |
| Errore 404 su ogni pagina tranne la home | `mod_rewrite` o `.htaccess` mancante | Verifica che `.htaccess` sia stato caricato (file nascosto) |
| Il caricamento dei PDF fallisce | Permessi cartella | `storage/documents` a 775 |
| "La settimana non è in bozza" | Comportamento corretto | Il time-sheet è già stato inviato o approvato |
| Il file SQL dà errore sui trigger | Incollati insieme | Uno per volta, passo 4.2 |

---

## Cosa non c'è ancora

Detto chiaramente, così non lo scopri dopo:

- **Nessun invio email automatico.** Le notifiche sono in-app (campanella). Per le email serve
  configurare lo SMTP della casella del dominio in `config.local.php` (`mail.enabled`).
- **Nessuna notifica push.** Richiede un servizio esterno o un VPS.
- **Nessuna schermata per creare gli utenti "risorsa"** (la persona che compila solo le proprie
  ore): il ruolo esiste ed è funzionante, ma l'account va creato da phpMyAdmin.
- **Nessun recupero password self-service.** Se un utente la dimentica, gliela reimposti tu da
  phpMyAdmin generando un nuovo hash.

Sono tutte cose aggiungibili in un secondo momento senza toccare l'impianto.

---

## Sviluppo in locale (facoltativo)

Se vuoi provare le modifiche prima di metterle online, ti serve PHP sul computer:

```bash
cp config/config.example.php config/config.local.php   # driver 'sqlite', env 'local'
php bin/migrate.php --fresh
php bin/seed.php --demo --admin=tuo@indirizzo.it --password='...'
php -S 127.0.0.1:8000 -t public public/index.php
```

SQLite serve solo allo sviluppo: la produzione usa MySQL, e le due migrazioni mantengono gli stessi
vincoli. Per rigenerare i file di `deploy/sql/`:
`php bin/make-deploy-sql.php --admin=tua@email.it`
