# Officina dei Talenti — Web App di Resource & Skill Sharing

Specifiche tecniche, architettura e design system per una **PWA mobile-first** di scambio/noleggio
di competenze tecniche, con tre macro-ruoli a dashboard separate:

| Ruolo | Chi è | Cosa fa |
|---|---|---|
| **OFFERENTE** | Società di consulenza, system integrator, freelance strutturati | Carica e gestisce le *risorse* (profili tecnici), compila il time-sheet settimanale, carica fatture |
| **RICHIEDENTE** | Azienda cliente che cerca competenze | Cerca e filtra risorse, richiede risorse, **approva o rifiuta** il time-sheet settimanale |
| **AMMINISTRATORE** | Il gestore del portale (noi) | Modera i profili, imposta manualmente le scadenze account, gestisce contratti, monitora time-sheet e stato pagamenti |

## Vincoli di progetto

- **Mobile-first, non mobile-responsive.** Il time-sheet si compila dal cantiere, dal treno, a fine
  giornata, con una mano. La UI si progetta a 375 px e poi si espande, non il contrario.
- **PWA installabile**, con funzionamento offline sulla compilazione del time-sheet.
- **Nessun pagamento automatico.** Niente Stripe, niente PayPal, niente checkout. La durata
  dell'account è un campo `access_expires_at` che l'Admin imposta a mano, legato a un contratto
  cartaceo/esterno. Le fatture sono PDF caricati e lo stato pagamento è un campo aggiornato a mano.

## Indice della documentazione

| Documento | Contenuto |
|---|---|
| [`docs/01-requisiti-e-ruoli.md`](docs/01-requisiti-e-ruoli.md) | Requisiti per modulo, matrice permessi (RBAC), macchine a stati |
| [`docs/02-fattibilita-wordpress.md`](docs/02-fattibilita-wordpress.md) | Approccio low-code: JetEngine, Ultimate Member, mobile-responsive e **i limiti reali sulla rendicontazione** |
| [`docs/03-stack-pwa.md`](docs/03-stack-pwa.md) | Stack consigliato: Next.js 15 + Tailwind + Supabase, PWA, offline, notifiche push |
| [`docs/04-database-schema.md`](docs/04-database-schema.md) | Schema DB, diagramma ER, focus su Utenti ↔ Risorse ↔ Contratti ↔ Rendicontazione settimanale |
| [`docs/05-user-flow.md`](docs/05-user-flow.md) | Flow end-to-end: registrazione → ricerca → contratto → time-sheet → fattura → pagamento |
| [`docs/06-ui-ux-mobile.md`](docs/06-ui-ux-mobile.md) | Design system mobile, wireframe della **Rendicontazione Settimanale**, micro-interazioni, colori di stato |
| [`docs/07-roadmap.md`](docs/07-roadmap.md) | Fasi di rilascio, MVP vs. V2, stime di effort e rischi |
| [`docs/08-scelta-stack-php.md`](docs/08-scelta-stack-php.md) | **La scelta finale**: perché PHP 8 + MySQL su Aruba, cosa si conserva e cosa si perde |
| [`DEPLOY-ARUBA.md`](DEPLOY-ARUBA.md) | Guida di installazione passo passo su Aruba Hosting Basic Linux |
| [`db/schema.sql`](db/schema.sql) | DDL PostgreSQL di riferimento (dai documenti di architettura) |
| [`migrations/`](migrations/) | Schema **effettivo** dell'applicazione: MySQL (produzione) e SQLite (sviluppo) |

## Sintesi della raccomandazione

**Il modulo di rendicontazione è ciò che decide la scelta tecnologica.**

Anagrafiche, catalogo e ricerca filtrata sono fattibili in WordPress con lo stack Crocoblock
(JetEngine + JetSmartFilters + JetFormBuilder + Ultimate Member): è "gestione di contenuti", il
terreno naturale di WordPress. Ma la rendicontazione settimanale è un'altra cosa: è un **workflow
transazionale con doppia parte, stati, approvazioni, calcoli e immutabilità del dato approvato**.
In WordPress ogni settimana di ogni contratto diventa un *post* con 20 meta-campi, la griglia
lun–dom si costruisce a mano in JavaScript, il blocco anti-modifica dopo l'approvazione va scritto
in PHP, e il tutto va reso usabile con una mano su uno schermo da 375 px partendo da un builder
pensato per il desktop. È il punto in cui il low-code smette di far risparmiare tempo.

**Raccomandazione: PWA custom** — Next.js 15 + Tailwind + shadcn/ui + **Supabase** (Postgres, Auth,
Storage, Realtime, Row Level Security). Nessuna licenza ricorrente, isolamento dei dati garantito a
livello di database, time-sheet compilabile offline e sincronizzato, notifiche push di approvazione,
e un'interfaccia progettata per il pollice invece che adattata dal desktop.

**Quando WordPress ha comunque senso:** budget sotto i 15k€, meno di ~30 collaborazioni attive
contemporaneamente e volontà di validare il modello prima di investire. In quel caso va trattato
come prototipo con vita attesa 12–18 mesi, e la rendicontazione va tenuta volutamente semplice
(una riga per settimana, niente griglia giornaliera).

Il dettaglio è in [`docs/02`](docs/02-fattibilita-wordpress.md) e [`docs/03`](docs/03-stack-pwa.md).

## Cosa è stato realizzato

Dato il vincolo di hosting scelto — **Aruba Hosting Basic Linux, 10 €/anno**, che è hosting
condiviso PHP/MySQL e non una macchina virtuale — lo stack Next.js + Supabase non era eseguibile.
L'applicazione è stata quindi realizzata come **PWA in PHP 8.1 + MySQL, senza Composer e senza build
step**, conservando le tre garanzie che contano davvero sulla rendicontazione: unicità della
settimana, immutabilità del dato approvato e calcolo degli importi solo lato server. Le ragioni, i
compromessi accettati e il percorso di uscita verso un VPS sono in
[`docs/08`](docs/08-scelta-stack-php.md).

```bash
cp config/config.example.php config/config.local.php   # sviluppo: driver 'sqlite'
php bin/migrate.php --fresh
php bin/seed.php --demo --admin=tuo@indirizzo.it --password='...'
php -S 127.0.0.1:8000 -t public public/index.php
```

Per la produzione: `php bin/package.php` genera l'archivio da caricare via FTP, e
[`DEPLOY-ARUBA.md`](DEPLOY-ARUBA.md) elenca i passi sul pannello Aruba (versione PHP, database,
schema da phpMyAdmin, cron giornaliero) e i limiti noti del piano.
