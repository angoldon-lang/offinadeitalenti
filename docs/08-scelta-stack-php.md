# 08 — La scelta finale: PHP 8 + MySQL su Aruba Hosting Basic

## Perché non lo stack del documento 03

Il vincolo di hosting ha deciso. **"Hosting Basic Linux" di Aruba non è una macchina virtuale**: è
hosting condiviso con PHP e MySQL, accesso via FTP e pannello, niente root, niente SSH, niente
Node.js. Lo stack Next.js + Supabase del documento [03](03-stack-pwa.md) su quel piano non può
girare — richiederebbe Vercel + Supabase (altri servizi) oppure un VPS Aruba Cloud Server.

Restavano quindi due strade dentro i 10 €/anno: WordPress, o un'applicazione PHP scritta su misura.
È stata scelta la seconda, e la ragione è tutta nel modulo 5.

## Cosa si conserva rispetto alla proposta originale

Le tre garanzie che rendono la rendicontazione un documento e non un promemoria **sopravvivono
intatte**, perché non dipendono dal framework ma dal database:

| Garanzia | Come è implementata | Perché WordPress non la darebbe |
|---|---|---|
| Una sola settimana per contratto | `UNIQUE (contract_id, iso_year, iso_week)` | I CPT non hanno vincoli di unicità: due submit ravvicinati creano due post |
| Il dato approvato è immutabile | Trigger `BEFORE UPDATE` con `SIGNAL SQLSTATE '45000'` | Qualunque plugin può fare `wp_update_post()` su un time-sheet approvato |
| Gli importi non li decide il client | Totali ricalcolati in PHP dalle giornate, tariffa congelata in `rate_snapshot` all'approvazione | Il totale calcolato in JS lato client è modificabile dal DevTools |

E si conserva anche la parte mobile: la PWA (manifest + service worker) è fatta di **file statici**,
che l'hosting condiviso serve senza alcun problema. La schermata di rendicontazione descritta in
[`06-ui-ux-mobile.md`](06-ui-ux-mobile.md) è implementata così com'era progettata — segmented
control `0 · ½ · 1`, barra totale che si aggiorna con animazione, swipe fra settimane, salvataggio
automatico, coda offline in `localStorage`.

## Cosa si perde

Va detto con onestà, perché sono rinunce reali:

| Perso | Conseguenza pratica |
|---|---|
| **Notifiche push** | Le notifiche restano in-app (campanella) ed email. La push richiede VAPID e un service worker con backend, o un servizio terzo |
| **Cron al minuto** | Il pannello Aruba consente un'esecuzione giornaliera: solleciti e scadenze si aggiornano una volta al giorno. Per questi processi è sufficiente |
| **Row Level Security** | L'isolamento fra organizzazioni è applicato dal codice (`OwnershipGuard` su ogni rotta), non dal database. È verificato dai test ma resta una garanzia più debole della RLS di Postgres |
| **Sync offline robusta** | La coda in `localStorage` copre il caso d'uso reale, ma non è la Background Sync API |
| **Ricerca oltre i grandi numeri** | I filtri usano `JOIN` + `HAVING`: perfetti fino a qualche migliaio di risorse, da rivedere oltre |

Nessuna di queste rinunce tocca la correttezza dei dati. Toccano la comodità e la scala.

## Il percorso di uscita

Il codice non è legato ad Aruba. Quando i limiti iniziano a pesare — indicativamente oltre le 30–40
collaborazioni attive — il passaggio a un **VPS Aruba Cloud Server** (~5 €/mese) porta cron al
minuto, PostgreSQL, notifiche push e HTTPS gestito, **con lo stesso identico codice**: cambia solo
dove gira e il driver nel file di configurazione. Lo schema esiste già in due varianti
(`migrations/mysql/` e `migrations/sqlite/`), e aggiungerne una PostgreSQL è lavoro di poche ore.

È la differenza fondamentale rispetto al percorso WordPress: qui il risparmio iniziale non produce
codice da buttare.

## Struttura del progetto

```
public/            document root: front controller, assets, service worker, manifest
├── index.php      unico ingresso: sessione, ruoli e CSRF passano sempre da qui
├── assets/        CSS scritto a mano (16 KB, nessun build step) e JS
└── sw.js          service worker

src/
├── Core/          Config, Database, Router, Auth, Csrf, Storage, Validator, View, Audit
├── Domain/        elenchi controllati con etichette italiane
├── Repository/    accesso ai dati, una classe per aggregato
├── Controller/    una classe per area
└── Support/       settimane ISO, festività, helper di presentazione

views/             template PHP, uno per schermata
migrations/        mysql/ (produzione Aruba) e sqlite/ (sviluppo)
bin/               migrate, seed, cron, package
storage/           PDF e log, mai serviti direttamente dal web
```

Nessuna dipendenza da Composer: l'autoloader PSR-4 sta in dieci righe dentro `src/bootstrap.php`.
Su hosting condiviso questa è una scelta deliberata — significa che il deploy è una copia di file e
che non esiste una `vendor/` da tenere aggiornata.

## Verifiche eseguite

Il flusso è stato percorso end-to-end su server reale prima della consegna:

- catena completa `DRAFT → SUBMITTED → APPROVED → INVOICED → PAID`;
- modifica di una settimana già inviata → **409**, con messaggio esplicito e nessuna sovrascrittura
  silenziosa;
- rinegoziazione del contratto da 450 a 600 €/gg **dopo** l'approvazione → la settimana approvata
  resta a 450 €/gg;
- rifiuto senza motivazione → bloccato;
- accesso di una terza azienda al contratto e alla settimana altrui → **404 / 403**;
- POST senza token CSRF → **419**;
- upload di un file non-PDF → respinto sulla verifica del contenuto, non dell'estensione;
- account scaduto → risorse de-indicizzate e sola lettura, ma **contratti e fatture sempre
  scaricabili**;
- zero warning PHP nell'intera sessione di test.

Un bug reale è emerso ed è stato corretto durante i test: nel filtro skill in AND il conteggio
dell'`HAVING` veniva legato come parametro e quindi confrontato come stringa. Su MySQL sarebbe
passato inosservato per coercizione di tipo; su SQLite restituiva zero risultati.
