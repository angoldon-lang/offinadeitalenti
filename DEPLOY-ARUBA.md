# Deploy su Aruba Hosting Basic Linux

L'applicazione e' PHP puro **senza Composer, senza Node, senza build step**: il deploy e' una
copia FTP. Gira sul piano *Hosting Basic Linux* da 10 €/anno.

## 1. Requisiti sul pannello Aruba

| Voce | Valore | Dove |
|---|---|---|
| **PHP** | **8.1 o superiore** (consigliato 8.2/8.3) | Gestione Hosting → Impostazioni PHP |
| **Database** | MySQL (incluso nel piano) | Gestione Database → crea DB e utente |
| **SSL** | Let's Encrypt attivo | Gestione SSL |
| **mod_rewrite** | attivo di default | — |
| **Cron** | 1 esecuzione giornaliera | Gestione Hosting → Cron job |

> Il codice usa `readonly` e `never` (PHP 8.1). Con PHP 8.0 non parte: la versione si cambia dal
> pannello in un clic.

## 2. Scegliere il layout

**Layout A — consigliato.** Se il pannello consente di puntare il dominio a una sottocartella,
imposta la document root su `public/`. Il codice, la configurazione e i PDF restano **fuori
dalla cartella pubblica**: e' la sistemazione piu' sicura.

```
/web/htdocs/www.tallerconsulting.it/home/
├── public/          ← document root del dominio
├── src/  views/  migrations/  bin/  config/
└── storage/         ← PDF e log, mai raggiungibili dal web
```

**Layout B — fallback.** Se la document root e' fissa e coincide con la cartella FTP:

```
/web/htdocs/www.tallerconsulting.it/home/   ← document root
├── index.php  .htaccess  sw.js  manifest.webmanifest  assets/
├── src/  views/  migrations/  bin/  config/     (+ .htaccess "Deny from all")
└── storage/                                     (+ .htaccess "Deny from all")
```

Qui i file applicativi stanno sotto la radice web e sono protetti solo dagli `.htaccess` inclusi
nel pacchetto. Funziona, ma va detto con chiarezza: **se un giorno il server smettesse di leggere
gli `.htaccess`, quei file diventerebbero raggiungibili.** I nomi dei PDF sono comunque casuali a
32 caratteri e non indovinabili. Se puoi scegliere, scegli il layout A.

Genera l'archivio giusto:

```bash
php bin/package.php          # layout A
php bin/package.php --flat   # layout B
```

## 3. Passi di installazione

1. **Database.** Dal pannello Aruba crea il database e annota host (`sql.tallerconsulting.it`),
   nome, utente e password.
2. **Configurazione.** Copia `config/config.example.php` in `config/config.local.php` e compila la
   sezione `db`. Metti `'env' => 'production'`. Nel layout B, se il sito sta in una sottocartella,
   valorizza `base_path`.
3. **Upload.** Carica via FTP il contenuto dell'archivio.
4. **Permessi.** `storage/documents` e `storage/logs` devono essere scrivibili (755, o 775 se il
   gestore FTP lo richiede).
5. **Schema.** Aruba non da' accesso SSH, quindi lo schema si applica da phpMyAdmin:
   apri `migrations/mysql/001_schema.sql`, **rimuovi le righe separatore `-- ;; --`** e incolla il
   contenuto nella scheda SQL. Esegui i `CREATE TRIGGER` uno per uno: phpMyAdmin non gestisce bene
   piu' trigger in un unico invio.
6. **Primo utente.** Sempre da phpMyAdmin, oppure — se il tuo piano espone la CLI PHP nel cron —
   pianificando una tantum `php bin/seed.php --admin=tuo@indirizzo.it --password='...'`.
   Per generare l'hash a mano: `password_hash('la-password', PASSWORD_DEFAULT)`.
7. **Cron.** Aggiungi un'esecuzione giornaliera (indicativamente alle 03:00):

   ```
   /usr/bin/php /web/htdocs/www.tallerconsulting.it/home/bin/cron.php
   ```

   Fa scadere gli account oltre la data impostata a mano, marca le fatture scadute e crea i
   solleciti. Il percorso esatto di `php` e' indicato nel pannello Aruba.

## 4. Verifica dopo il deploy

- [ ] `https://tallerconsulting.it/` risponde e reindirizza a HTTPS
- [ ] `/login` funziona e l'accesso amministratore entra in `/admin`
- [ ] richiamare direttamente `https://.../storage/documents/...` restituisce **403**
- [ ] `https://.../config/config.local.php` restituisce **403** (nel layout B)
- [ ] il caricamento di un PDF di prova va a buon fine e il download passa da `/documenti/{id}`
- [ ] su smartphone il browser propone "Aggiungi a schermata Home" (PWA)
- [ ] il cron compare fra le esecuzioni riuscite il giorno dopo

## 5. Limiti noti di questo hosting

Vanno messi in conto: sono i vincoli del piano da 10 €/anno, non difetti dell'applicazione.

| Limite | Effetto | Come conviverci |
|---|---|---|
| **Cron una volta al giorno** | Solleciti e scadenze si aggiornano a orario fisso | Sufficiente: sono processi giornalieri per natura |
| **Niente notifiche push** | Le notifiche sono in-app (campanella) | La push richiede un servizio esterno (es. OneSignal) o un backend Node |
| **`mail()` poco affidabile** | Le email possono finire in spam | Configurare lo SMTP della casella del dominio quando si abilita `mail.enabled` |
| **Nessun accesso SSH** | Migrazioni e seed da phpMyAdmin | Documentato al punto 5 |
| **Spazio disco limitato** | I PDF crescono nel tempo | Monitorare `storage/documents`, archiviare i contratti chiusi |
| **Nessun antivirus sugli upload** | Un PDF malevolo resta tale | Il tipo MIME e' verificato; i file non sono mai eseguibili perche' serviti da PHP |
| **Backup Aruba di base** | Ripristino lento | Esportare il DB da phpMyAdmin con cadenza settimanale |

Quando questi limiti iniziano a pesare — tipicamente oltre le 30-40 collaborazioni attive — il
passo successivo e' un VPS (Aruba Cloud Server da ~5 €/mese) con lo stesso codice, cron al minuto,
PostgreSQL e le notifiche push. **Non serve riscrivere nulla:** cambia solo dove gira.

## 6. Sviluppo in locale

```bash
cp config/config.example.php config/config.local.php   # driver 'sqlite', env 'local'
php bin/migrate.php --fresh
php bin/seed.php --demo --admin=admin@example.com --password='...'
php -S 127.0.0.1:8000 -t public public/index.php
```

SQLite serve solo allo sviluppo: la produzione usa MySQL, e le due migrazioni mantengono gli stessi
vincoli (unicita' settimanale e trigger di immutabilita').
