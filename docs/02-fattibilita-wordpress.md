# 02 — Analisi di fattibilità su WordPress (approccio low-code)

## 1. La domanda vera

Non è "si può fare un portale di annunci in WordPress" — sì, ovviamente. La domanda è:

> **è fattibile la logica di rendicontazione settimanale con approvazione, in mobile-responsive?**

Risposta breve: **sì, ma è la parte in cui WordPress smette di essere low-code.** Anagrafiche,
catalogo, ricerca filtrata e repository documentale si assemblano con i plugin. Il time-sheet no:
è un workflow transazionale a due parti, con stati, calcoli, immutabilità e una UI a griglia
touch-friendly. In WordPress lo si costruisce a mano dentro un sistema che non ha nessuno dei
mattoni necessari.

## 2. Stack di plugin consigliato

| Layer | Scelta | Ruolo | Costo indicativo/anno |
|---|---|---|---|
| Tema/builder | **Bricks Builder** (o GeneratePress + Elementor Pro) | Output HTML pulito, controlli responsive per breakpoint | ~200 € lifetime |
| Dati e relazioni | **JetEngine** (Crocoblock) | CPT, tassonomie, meta, **relazioni many-to-many**, query builder, listing grid | incluso in Crocoblock ~150 € |
| Form frontend | **JetFormBuilder** | Inserimento risorse, richieste, **compilazione time-sheet**, azioni post-submit | free / pro |
| Filtri | **JetSmartFilters** | Filtri AJAX in tempo reale, range slider, checkbox | incluso in Crocoblock |
| Utenti e profili | **Ultimate Member** (+ addon) | Registrazione/login separati per ruolo, profili frontend, restrizioni contenuto, moderazione utenti | free + addon 50–250 € |
| Alternativa membership | **MemberPress** / **Paid Memberships Pro** | Se serve una gestione più strutturata di livelli e scadenze | 180–350 € |
| Ricerca | **SearchWP** o **FacetWP** | Indice serio e faccette performanti | 99–249 $ |
| File privati | **Prevent Direct Access Gold** o codice custom | PDF fuori dalla webroot con controllo permessi | ~100 $ |
| Sicurezza | **Wordfence Premium** / Patchstack | WAF, virtual patching dei plugin | 99–150 $ |
| Log | **WP Activity Log** | Audit delle azioni | ~100 $ |
| Backup | **UpdraftPlus Premium** / Blogvault | Backup offsite + restore | ~100 $ |

Totale licenze realistico: **900–1.500 €/anno ricorrenti**, più hosting gestito 40–100 €/mese
(serve un hosting che regga query meta pesanti: object cache Redis praticamente obbligatoria).

### Nota su Ultimate Member

Copre bene: registrazione con form diversi per ruolo, approvazione manuale dell'iscritto
(`awaiting admin review` è nativo — utile per il nostro `PENDING_APPROVAL`), profili frontend,
restrizione dei contenuti per ruolo, directory utenti.

Non copre: la **durata dell'account** con scadenza impostata a mano. Va aggiunta come user meta
`access_expires_at` + un cron che, al superamento, cambia ruolo (`offerente` → `offerente_scaduto`)
e de-indicizza le risorse. Sono ~150 righe di PHP, ma vanno scritte, testate e mantenute a ogni
aggiornamento di UM.

Alternativa: MemberPress gestisce nativamente le scadenze — ma è costruito attorno al pagamento
online, che qui è esplicitamente escluso. Si finisce per usare il 20% del plugin creando abbonamenti
"gratuiti" a scadenza manuale: funziona, ma è uno strumento usato controverso. Con Ultimate Member +
cron custom il modello resta più onesto.

### Plugin "job board" verticali (WP Job Manager, Workreap, JobMonster)

**Sconsigliati.** Modellano *azienda pubblica offerta → candidato si candida*, che è l'inverso del
nostro flusso (*offerente pubblica risorsa → azienda cerca*). Adattarli costa più che partire da CPT
nudi e ogni loro update rischia di rompere gli override.

## 3. Come si modella la rendicontazione in WordPress

Struttura minima con JetEngine:

| Entità | Implementazione |
|---|---|
| Risorsa | CPT `risorsa` + tassonomie `hard_skill`, `soft_skill` + meta (seniority, costo, modalità, stato) |
| Contratto | CPT `contratto` + meta (date, tariffa, unità) + **relazioni JetEngine** verso offerente, richiedente, risorsa |
| Time-sheet settimanale | CPT `timesheet` + meta (`contract_id`, `anno`, `settimana_iso`, `lun`…`dom`, `totale`, `stato`) + relazione al contratto |
| Fattura | CPT `fattura` + meta (numero, importo, stato pagamento, data pagamento) + relazione ai time-sheet |

E il flusso:

1. **Compilazione** — form JetFormBuilder con 7 campi numerici (lun–dom) + tipo giornata, azione
   "Insert/Update Post", pre-popolato via query. Il totale si calcola in JS lato client **e va
   ricalcolato in PHP lato server**, altrimenti chiunque può inviare un totale arbitrario dal
   DevTools.
2. **Cambio stato** — hook `jet-form-builder/custom-action` che scrive `stato = SUBMITTED` e invia
   la notifica.
3. **Approvazione** — due pulsanti che chiamano un endpoint `admin-post.php` o REST custom; nel
   callback: verifica nonce, verifica che l'utente sia **davvero il richiedente di quel contratto**,
   scrittura dello stato, notifica.
4. **Blocco post-approvazione** — filtro su `user_has_cap` / `map_meta_cap` che nega `edit_post` sui
   time-sheet in stato `APPROVED`/`SUBMITTED`. Da fare bene, altrimenti il dato approvato resta
   modificabile e il modulo perde ogni valore contrattuale.
5. **Riepilogo di fatturazione** — query custom che somma i time-sheet approvati per contratto e
   periodo, moltiplica per la tariffa e genera il PDF (dompdf/mPDF).

## 4. Mappatura requisito → difficoltà

| # | Requisito | In WordPress | Difficoltà |
|---|---|---|---|
| 1 | Registrazione/login separati per ruolo | Ultimate Member, 2 form + 2 ruoli custom | 🟢 |
| 1 | Approvazione manuale del nuovo iscritto | Nativa in UM (`awaiting admin review`) | 🟢 |
| 1 | Durata account impostata a mano dall'Admin | user meta + cron custom + cambio ruolo | 🟡 codice |
| 2 | CPT risorsa con tutti i campi | JetEngine (skill come **tassonomie**, non meta) | 🟢 |
| 2 | Inserimento risorse da frontend | JetFormBuilder | 🟢 |
| 2 | Vedere solo le proprie risorse | query su `author` + capability check su **ogni** endpoint | 🟡 **qui si sbaglia** |
| 3 | Filtri real-time multi-criterio | JetSmartFilters / FacetWP | 🟡 |
| 3 | Slider budget | JetSmartFilters "Range" | 🟢 |
| 3 | Filtro geografico con raggio km | ❌ non nativo: lat/lng + haversine custom | 🔴 |
| 3 | Match score / rilevanza | ❌ query SQL custom | 🔴 |
| 3 | Anonimizzazione pre-match | template + **filtri sulla REST API** (`/wp-json` espone tutto di default) | 🔴 facile da sbagliare |
| 4 | Upload PDF contratti | Media Library + CPT `contratto` | 🟢 |
| 4 | PDF **davvero** privati | ❌ la Media Library serve file da URL pubblici e indovinabili | 🔴 rischio breach |
| 4 | Date + alert scadenza | meta date + cron + WP Mail SMTP | 🟡 |
| 5 | **Griglia time-sheet lun–dom touch** | ❌ nessun componente: JS custom dentro il builder | 🔴 |
| 5 | **Un solo time-sheet per settimana/contratto** | ❌ nessun vincolo di unicità sui post: check applicativo, race condition possibili | 🔴 |
| 5 | **Workflow di approvazione a due parti** | endpoint custom + capability + notifiche | 🔴 |
| 5 | **Immutabilità del dato approvato** | `map_meta_cap` custom | 🔴 critico |
| 5 | **Salvataggio offline** | ❌ non praticabile | 🔴 |
| 5 | Calcolo importi e riepilogo fatturazione | query + PHP + dompdf | 🟡 |
| 5 | Stato pagamento manuale | meta + colonna admin | 🟢 |
| 6 | Moderazione profili | stato `pending` nativo + bulk action | 🟢 |
| 6 | Moderazione di una *modifica* a profilo già online | ❌ serve doppia versione | 🔴 |
| 6 | **Monitor settimane × contratti** | ❌ nessuna vista a matrice: tabella custom | 🔴 |
| 6 | Report e statistiche | query custom + Chart.js o export CSV | 🟡 |

Il pattern è netto: **tutto ciò che è "gestione di contenuti" è facile; tutto ciò che è "regola di
business" o "controllo di accesso" è custom.** E il modulo 5 è quasi interamente regola di business.

## 5. I quattro limiti specifici della rendicontazione su WordPress

**1. Il modello dati è sbagliato per il caso d'uso.**
Ogni settimana di ogni contratto è un *post* con ~20 meta-campi in `wp_postmeta`, tabella EAV
`(post_id, meta_key, meta_value)` con valori `LONGTEXT`. 30 collaborazioni × 52 settimane = 1.560
post/anno con ~31.000 righe di meta. Il monitor admin "tutte le settimane di tutti i contratti"
diventa una query con 8+ `JOIN` sulla stessa tabella e confronti numerici su colonne testuali. Con
30 contratti è lento ma sopportabile; con 200 va riscritto con tabelle custom — cioè ricostruendo a
mano il database relazionale che si era evitato.

Peggio: **non esiste un vincolo di unicità**. In un DB relazionale `UNIQUE(contract_id, year, week)`
rende impossibile il doppio rendiconto. In WordPress il controllo è applicativo e due submit
ravvicinati (rete mobile instabile, doppio tap) possono creare due post per la stessa settimana.
Si scopre in fase di fatturazione, cioè nel momento peggiore.

**2. L'immutabilità del dato approvato è affidata alla disciplina, non al sistema.**
Un time-sheet approvato è la base di una fattura: deve essere non modificabile. In WordPress
qualunque plugin, snippet o cron con privilegi può fare `wp_update_post()` e cambiarlo, senza
lasciare traccia se il log non è configurato. Non esiste un equivalente di un trigger di database o
di una policy RLS che lo impedisca a livello di dato. Su un modulo che genera importi da fatturare,
è una debolezza sostanziale.

**3. La UI mobile della griglia va costruita a mano.**
Nessun plugin offre un time-sheet settimanale touch. Con JetFormBuilder si ottengono 7 campi
numerici impilati: funzionante, ma è un modulo, non un'interfaccia. Per avere l'esperienza descritta
in [`06-ui-ux-mobile.md`](06-ui-ux-mobile.md) — card giornaliere, stepper ±0,5, swipe fra settimane,
totale che si aggiorna in tempo reale, salvataggio automatico — si scrive JavaScript custom dentro
il builder, senza framework a componenti, con lo stato tenuto a mano nel DOM. È il lavoro di
frontend più costoso del progetto, fatto nell'ambiente meno adatto a farlo.

**4. Niente offline, e le notifiche push sono un'installazione a parte.**
Il caso d'uso reale è il venerdì sera, in mobilità, con rete incerta. WordPress è request/response
server-rendered: se la connessione cade durante il submit, la settimana è persa e va ricompilata.
Rendere una pagina WP offline-capable significa aggiungere un service worker che riscrive il modo in
cui il sito funziona — tecnicamente possibile, di fatto fuori scala per un progetto low-code. Le
push richiedono OneSignal o simile (altro servizio, altro costo, altro consenso GDPR da gestire).

## 6. Pro e contro

### ✅ Pro

- **Time-to-market**: MVP dimostrabile in 6–8 settimane (contro 12–16 custom).
- **Costo iniziale**: 10–20k € contro 40–75k €.
- **Back-office gratis**: l'admin di WordPress copre moderazione, utenti e media senza scrivere UI.
  In un progetto custom il back-office è il 25–30% dell'effort: non è poco.
- **Competenze reperibili**: chiunque può manutenerlo, nessun lock-in sul fornitore.
- **Ecosistema risolto**: SEO, multilingua (WPML/Polylang), email, form, analytics già integrati.
- **Autonomia del cliente** su testi e pagine di contenuto.

### ❌ Contro

- **La rendicontazione — il cuore del prodotto — è quasi tutta custom** (vedi §5). Il vantaggio
  low-code si applica alla metà meno strategica dell'applicazione.
- **Mobile-first solo apparente**: i builder sono desktop-first con override per breakpoint. Si
  ottiene un sito responsive, non un'app che si usa con il pollice. La differenza si sente
  esattamente sulla schermata che gli utenti apriranno 52 volte l'anno.
- **Superficie di attacco**: 8–10 plugin commerciali = 8–10 codebase terze con privilegi pieni sul
  DB. Quasi tutte le compromissioni WordPress passano dai plugin. Con contratti, fatture e dati di
  persone fisiche a bordo, l'esposizione è concreta.
- **File privati**: la Media Library nasce per file pubblici. Servire PDF contrattuali in sicurezza
  richiede lavoro custom e disciplina; l'errore produce un data breach silenzioso.
- **Isolamento multi-tenant fragile**: le capability WP sono pensate per redattori, non per separare
  tenant. "L'offerente A non deve leggere i dati di B né via UI né via `/wp-json`" richiede filtri
  espliciti su REST, admin-ajax e query, ripetuti per ogni plugin aggiunto.
- **Aggiornamenti fragili**: la logica vive dentro hook di plugin di terzi; un major update può
  rompere un flusso in silenzio. Servono staging e test di regressione manuali ogni volta.
- **L'applicazione non è tutta in git**: gran parte della configurazione (builder, JetEngine, form)
  vive nel database. Non è versionabile, testabile né replicabile in modo affidabile fra ambienti.
- **Costo ricorrente**: 900–1.500 €/anno di sole licenze, per sempre.

## 7. Verdetto

| Se… | Allora |
|---|---|
| < 30 collaborazioni attive, budget < 15k€, obiettivo = validare il modello | **WordPress è razionale**, come prototipo con vita attesa 12–18 mesi |
| la rendicontazione deve reggere il valore contrattuale e crescere | **PWA custom** ([`03-stack-pwa.md`](03-stack-pwa.md)) |

Se si sceglie WordPress, due mitigazioni obbligatorie:

1. **Semplificare volutamente il time-sheet**: una riga per settimana (totale giorni + note), niente
   griglia giornaliera. Riduce l'80% del lavoro custom mantenendo il 90% del valore, e il dettaglio
   giornaliero si aggiunge dopo — sulla piattaforma definitiva.
2. **Tabelle custom per time-sheet e fatture**, non CPT: `wp_timesheets` e `wp_invoices` create con
   `dbDelta()`, con vincoli `UNIQUE` reali. Si perde l'admin automatico ma si guadagna integrità del
   dato e query 10–50× più veloci. È il compromesso migliore dentro WordPress.

In entrambi i casi vale la pena essere espliciti: **il risparmio iniziale si restituisce con gli
interessi** in manutenzione, incidenti e ottimizzazioni, nel momento in cui il portale funziona
davvero.
