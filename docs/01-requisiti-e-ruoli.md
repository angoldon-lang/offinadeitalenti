# 01 — Requisiti funzionali, ruoli e permessi

## 1. Modello dei ruoli

Il sistema distingue **account** (chi fa login) da **organizzazione** (l'entità che ha un contratto e
una scadenza). È la scelta strutturale più importante del progetto: un Offerente non è "un utente",
è un'azienda che può avere più persone (commerciale, resource manager, amministrazione) che
condividono lo stesso catalogo di risorse, gli stessi contratti e la stessa scadenza account.

```
Organization (tipo: OFFERENTE | RICHIEDENTE)
   ├── User[] (ruolo interno: OWNER | MEMBER)
   ├── access_expires_at        ← impostata a mano dall'Admin
   ├── Contract[]               ← PDF + data inizio/fine + tariffa concordata
   ├── Resource[]               (solo se OFFERENTE)
   └── Timesheet[] / Invoice[]  (generati sui contratti attivi)
```

I macro-ruoli di piattaforma sono tre (`OFFERENTE`, `RICHIEDENTE`, `ADMIN`), con un secondo livello
interno all'organizzazione (`OWNER` / `MEMBER`) che regola chi può firmare contratti e chi può solo
compilare o consultare. Esiste inoltre un ruolo operativo opzionale, `RESOURCE_USER`: la singola
persona tecnica a cui l'Offerente può dare un accesso limitato **al solo time-sheet** delle proprie
giornate. È il requisito "l'Offerente *(o la risorsa)* compila i giorni lavorati".

### Aree applicative

| Ruolo | Entry point | Home della dashboard |
|---|---|---|
| `OFFERENTE` | `/offerente` | Le mie risorse · Richieste ricevute · **Time-sheet da compilare** · Contratti · Fatture |
| `RICHIEDENTE` | `/richiedente` | Ricerca risorse · Richieste inviate · **Time-sheet da approvare** · Contratti · Fatture ricevute |
| `RESOURCE_USER` | `/risorsa` | Solo la settimana corrente da compilare |
| `ADMIN` | `/admin` | Moderazione · Organizzazioni e scadenze · Contratti · **Monitor time-sheet** · **Stato pagamenti** |

Le aree sono **applicazioni separate a livello di routing e autorizzazione**, non una dashboard
unica con blocchi nascosti via CSS. Ogni chiamata API è autorizzata lato server su ruolo *e*
proprietà del dato (`organization_id`), mai su ciò che il frontend mostra.

## 2. Matrice dei permessi (RBAC)

Legenda: ✅ consentito · 🔒 solo sui propri dati · ❌ negato

| Azione | Offerente | Richiedente | Risorsa | Admin |
|---|:---:|:---:|:---:|:---:|
| Registrarsi / autenticarsi | ✅ | ✅ | invito | invito |
| Creare/modificare una risorsa | 🔒 | ❌ | ❌ | ✅ |
| Pubblicare una risorsa | ❌ (serve approvazione) | ❌ | ❌ | ✅ |
| Ricerca e filtri sul catalogo | ❌ | ✅ | ❌ | ✅ |
| Vedere l'anagrafica completa dell'offerente | — | ⚠️ solo dopo richiesta accettata | ❌ | ✅ |
| Inviare "Richiedi risorsa" | ❌ | 🔒 (se account attivo) | ❌ | ✅ |
| Accettare/rifiutare una richiesta | 🔒 | ❌ | ❌ | ✅ |
| Caricare un contratto PDF | 🔒 | 🔒 | ❌ | ✅ |
| Scaricare un contratto | 🔒 (se parte) | 🔒 (se parte) | ❌ | ✅ |
| Impostare/modificare la scadenza account | ❌ | ❌ | ❌ | ✅ |
| **Compilare il time-sheet settimanale** | 🔒 | ❌ | 🔒 (proprie giornate) | ✅ |
| **Inviare il time-sheet in approvazione** | 🔒 | ❌ | 🔒 | ✅ |
| **Approvare / rifiutare il time-sheet** | ❌ | 🔒 | ❌ | ✅ (override tracciato) |
| Modificare un time-sheet approvato | ❌ | ❌ | ❌ | ✅ (con motivazione a log) |
| Caricare una fattura PDF | 🔒 | ❌ | ❌ | ✅ |
| **Aggiornare lo stato del pagamento** | ❌ | ❌ | ❌ | ✅ |
| Vedere report e statistiche globali | ❌ | ❌ | ❌ | ✅ |
| Audit log | ❌ | ❌ | ❌ | ✅ |

Tre regole trasversali che non vanno lasciate implicite:

1. **Anonimizzazione del catalogo.** Il Richiedente naviga profili tecnici, non aziende. Finché la
   richiesta non è accettata, la card mostra competenze, seniority, disponibilità, tariffa e
   località, ma **non** ragione sociale, nome della persona, CV o contatti. È ciò che impedisce la
   disintermediazione, cioè il fallimento tipico di questi marketplace.
2. **Scadenza = perdita di capability, non cancellazione.** Alla scadenza i dati restano; cambia
   solo cosa si può fare (§4).
3. **Chi approva non è chi compila.** L'Offerente compila, il Richiedente approva. Un time-sheet
   approvato diventa **immutabile** e vale come base della fattura: modificarlo richiede un
   intervento admin motivato e tracciato. Senza questa regola il modulo non ha valore contrattuale.

## 3. Requisiti per modulo

### 3.1 Utenti, accessi e durata account

- Registrazione separata Offerente/Richiedente, con campi diversi (ragione sociale, P.IVA, settore,
  referente). Verifica email obbligatoria.
- Login email + password (Argon2id/bcrypt gestito dal provider auth), 2FA TOTP opzionale,
  **obbligatorio per gli Admin**. Magic link consigliato su mobile: meno attrito della password.
- Nuova registrazione → stato `PENDING_APPROVAL`. Nessun catalogo visibile prima dell'attivazione
  manuale da parte dell'Admin.
- **Durata del profilo**: campo `access_expires_at` (data) sull'organizzazione, impostato e
  modificato **solo dall'Admin**, con campo note (riferimento al contratto cartaceo) e storico delle
  proroghe. Nessun pagamento, nessun rinnovo automatico, nessun gateway.
- Job notturno che valuta le scadenze e degrada gli stati; email di preavviso a T-30 / T-7 / T-0
  all'organizzazione e all'Admin.

### 3.2 Vista Offerente — catalogo risorse

Campi obbligatori della risorsa:

| Campo | Tipo | Note |
|---|---|---|
| Nome/Ruolo | testo | es. "Senior React Developer" — è il titolo pubblico |
| Hard skills | tag multipli | tassonomia controllata, con livello 1–5 e anni di esperienza |
| Soft skills | tag multipli | tassonomia controllata |
| Livello di esperienza | enum | `JUNIOR` `MID` `SENIOR` `TECH_LEAD` |
| Disponibilità | enum + impegno | `IMMEDIATA` `ENTRO_1_MESE` `ENTRO_3_MESI` + `PART_TIME`/`FULL_TIME` |
| Range di costo | min/max + unità | `DAILY` o `HOURLY`, valuta, flag "trattabile" |
| Modalità di lavoro | enum | `ONSITE` `REMOTO` `IBRIDO` |
| Località | città/provincia/paese (+ raggio km) | obbligatoria se modalità ≠ `REMOTO` |
| Stato risorsa | enum | `ATTIVA` (prenotabile) / `OCCUPATA` (visibile, non prenotabile) |

Opzionali ad alto valore: lingue, settori verticali (fintech, retail, PA), certificazioni, anno di
inizio carriera, CV PDF (visibile solo dopo richiesta accettata).

Funzionalità: CRUD con salvataggio in bozza, **duplicazione profilo** (i profili tecnici si
somigliano: è il risparmio di tempo più apprezzato), cambio rapido Attiva/Occupata dalla lista,
inbox richieste, contratti, time-sheet, fatture.

### 3.3 Vista Richiedente — ricerca mobile

La dashboard **atterra sulla ricerca**, non su una home. Su mobile:

- barra di ricerca sticky in alto + riga di **chip filtro orizzontali** scrollabili (Skill,
  Esperienza, Budget, Modalità, Disponibilità);
- il tap su un chip apre un **bottom sheet** con le opzioni; i filtri attivi restano visibili come
  chip colorati con la "x" per rimuoverli;
- risultati in **card** verticali con match score, aggiornati in tempo reale (debounce 250 ms) e
  contatore risultati sempre visibile sul pulsante "Mostra N risorse";
- filtri sincronizzati con l'URL → ricerca condivisibile e salvabile.

Filtri: skill multiple (AND/OR), livello, **slider budget** con normalizzazione oraria↔giornaliera
(1 gg = 8 h, altrimenti si confrontano mele con pere), disponibilità, modalità, località + raggio,
lingua, settore, includi/escludi risorse `OCCUPATE`.

Azioni sulla card: salva in shortlist, confronta (fino a 4), **"Richiedi risorsa"** → form breve
(progetto, durata stimata, data inizio, budget, note). Da prevedere subito le **ricerche salvate con
alert**: è la funzione che riporta il Richiedente sulla app dopo la prima visita.

### 3.4 Contratti

Repository documentale con visibilità per contratto: `PRIVATO_OFFERENTE`, `PRIVATO_RICHIEDENTE`,
`CONDIVISO` (entrambe le parti + Admin), `SOLO_ADMIN`.

- Upload PDF (max 20 MB), **versionato**: la v2 non sovrascrive la v1.
- Metadati obbligatori: tipo (NDA, quadro, ordine, SOW), controparte, **data inizio**, **data fine**,
  **tariffa concordata** (valore + unità DAILY/HOURLY), risorsa collegata.
- La tariffa concordata sul contratto è la sorgente di verità per il calcolo degli importi: **non**
  si usa il range di costo del profilo, che è solo indicativo e può cambiare.
- Notifiche di scadenza a T-60 / T-30 / T-7.
- Storage privato: i PDF non stanno in cartelle pubbliche. Download via URL firmati a scadenza breve
  generati **dopo** il controllo di autorizzazione, con riga di audit.
- Firma: MVP con upload del PDF controfirmato. V2 con e-signature (Yousign, Namirial, Dropbox Sign).

Un contratto in stato `ACTIVE` è ciò che **abilita la sezione Rendicontazione** per quella coppia
Offerente–Richiedente.

### 3.5 Rendicontazione settimanale, fatture e pagamenti

Il modulo si attiva automaticamente quando esiste un contratto `ACTIVE` con `start_date` ≤ oggi.

**Time-sheet (compilazione — Offerente o Risorsa)**
- Unità di lavoro: **una settimana ISO** (lun–dom) per **un contratto**. Chiave unica
  `(contract_id, year, iso_week)`: non possono esistere due rendiconti per la stessa settimana.
- Per ogni giorno: quantità lavorata (giorni `0` / `0,5` / `1` se il contratto è a giornata; ore
  `0–24` se è a ore), tipo (`LAVORO`, `TRASFERTA`, `FERIE`, `PERMESSO`, `FESTIVO`, `MALATTIA`) e
  nota facoltativa.
- Le righe si generano precompilate a 0 sul calendario, con weekend e **festività italiane**
  marcate; il totale settimanale si aggiorna live.
- Salvataggio automatico in bozza, **anche offline** (il caso d'uso è il venerdì sera in mobilità).
- Invio in approvazione → lo stato passa a `SUBMITTED` e la settimana si blocca in scrittura.
- Promemoria push/email automatico il venerdì e il lunedì mattina sulle settimane non inviate.

**Approvazione (Richiedente)**
- Notifica push + email + badge sulla dashboard.
- Vista dedicata con riepilogo settimana, dettaglio giorni, totale e **importo calcolato**
  (`totale × tariffa contratto`), storico delle settimane precedenti.
- Azioni: **Approva** (un tap) oppure **Rifiuta con motivazione obbligatoria** → torna in `DRAFT`
  all'Offerente, che corregge e reinvia. Il ciclo di rifiuto/reinvio è tracciato per intero.
- **Auto-approvazione opzionale** configurabile per contratto (es. dopo 7 giorni di silenzio):
  utile a evitare che la fatturazione si blocchi, ma va disattivata di default e resa esplicita.

**Fatturazione e pagamenti**
- Un time-sheet `APPROVED` genera una **riga fatturabile**; una fattura può raggruppare più
  settimane (tipicamente il mese).
- Il sistema produce il **riepilogo di fatturazione** (giorni/ore × tariffa, imponibile, note),
  esportabile in PDF/CSV. **Non emette fatture fiscali**: quelle restano nel gestionale del cliente.
- L'Offerente (o l'Admin) carica la **fattura PDF** e la collega alle settimane incluse.
- Stato pagamento aggiornato **manualmente**: `DA_EMETTERE` → `EMESSA` → `INVIATA` → `PAGATA`
  (oppure `SCADUTA` / `CONTESTATA`), con data pagamento, importo incassato e note. Nessun gateway.
- Ogni cambio di stato è tracciato con attore e timestamp.

### 3.6 Vista Admin — back-office

- **Coda di moderazione** risorse in `IN_REVIEW`, con diff rispetto alla versione già pubblicata:
  una modifica a un profilo online non deve farlo sparire dall'indice mentre è in review.
- Azioni: approva, rifiuta con motivazione, richiedi modifiche.
- **Organizzazioni e scadenze**: tabella con `access_expires_at`, giorni residui, evidenza dei
  profili in scadenza a 30/7 giorni, proroga rapida con nota, sospensione/riattivazione.
- **Contratti**: caricamento per conto delle parti, scadenzario, tariffe.
- **Monitor rendicontazione**: matrice settimane × contratti con stato a colpo d'occhio
  (`da compilare` / `in attesa di approvazione` / `approvato` / `rifiutato`), filtri per periodo,
  cliente e fornitore; sollecito manuale in un tap.
- **Stato pagamenti**: elenco fatture con stato, importi, scaduto per cliente, aggiornamento
  manuale, export CSV per la contabilità.
- Impersonation tracciata a log (indispensabile per il supporto, pericolosa se non auditata).
- Report: risorse per skill/seniority, richieste inviate vs. accettate, tempo medio di approvazione
  dei time-sheet, giorni fatturati per periodo, e **skill cercate senza risultati** — il dato più
  prezioso: dice cosa manca a catalogo.

## 4. Macchine a stati

### Organizzazione (durata account, gestita a mano)

```
PENDING_APPROVAL ──attivazione admin (+ access_expires_at)──► ACTIVE
                                                                │
                            ┌──── access_expires_at < oggi ─────┤
                            ▼                                   │
                    GRACE (15 gg, banner + sola lettura)   proroga admin
                            │                                   │
                    nessuna proroga                             │
                            ▼                                   │
                        EXPIRED ───────────────────────────────►┘
                            │
                  sospensione manuale admin
                            ▼
                        SUSPENDED
```

| Stato | Offerente | Richiedente |
|---|---|---|
| `ACTIVE` | risorse indicizzate, tutto attivo | ricerca completa, richieste illimitate |
| `GRACE` | risorse visibili + banner di sollecito | ricerca ok, nuove richieste bloccate |
| `EXPIRED` | risorse **de-indicizzate**, dati conservati | ricerca in sola anteprima, richieste bloccate |
| `SUSPENDED` | login consentito, tutto in sola lettura | idem |

**Eccezione non negoziabile:** contratti, time-sheet e fatture restano **sempre** consultabili e
scaricabili anche da account scaduto. Sono documenti amministrativi della controparte; negarne
l'accesso crea un problema legale, non una leva commerciale. E un time-sheet già approvato deve
poter essere fatturato anche se nel frattempo l'account è scaduto.

### Risorsa

```
DRAFT ──invia──► IN_REVIEW ──approva──► PUBLISHED ──► ARCHIVED
                     │                      │
                     └── REJECTED ──► DRAFT └── stato operativo: ATTIVA | OCCUPATA
```

`PUBLISHED` (visibilità, decisa dall'Admin) e `ATTIVA/OCCUPATA` (disponibilità, decisa
dall'Offerente) sono **due assi indipendenti**: fonderli obbliga a ri-moderare un profilo ogni volta
che si libera. È l'errore di modellazione più comune in questi portali.

### Richiesta risorsa (match)

```
REQUESTED ──► ACCEPTED ──► IN_NEGOTIATION ──► CONTRACTED ──► CLOSED
     │             │
     └─ DECLINED   └─ EXPIRED (nessuna risposta entro 7 gg)
```

### Time-sheet settimanale

```
      (generato)
         │
       DRAFT ──invia──► SUBMITTED ──approva──► APPROVED ──► INVOICED ──► PAID
         ▲                  │
         └──── REJECTED ◄───┘  (motivazione obbligatoria)
```

- `DRAFT`: scrivibile da Offerente/Risorsa.
- `SUBMITTED`: **read-only per l'Offerente**, in attesa del Richiedente.
- `APPROVED`: **immutabile**. Sblocca la fatturazione. Modificabile solo da Admin con motivazione.
- `REJECTED`: torna scrivibile, con la motivazione in evidenza.
- `INVOICED` / `PAID`: derivati dallo stato della fattura collegata.

### Fattura

```
DA_EMETTERE ──► EMESSA ──► INVIATA ──► PAGATA
                              │
                              ├──► SCADUTA (due_date superata, job notturno)
                              └──► CONTESTATA ──► INVIATA
```

## 5. Requisiti non funzionali

| Ambito | Target |
|---|---|
| Mobile | progettazione a 375 px, target tap ≥ 44 px, PWA installabile, LCP < 2,5 s su 4G |
| Offline | compilazione time-sheet disponibile offline con sync automatica al ritorno online |
| Performance ricerca | p95 < 300 ms con 5 filtri attivi su 50k risorse |
| Disponibilità | 99,5% (SLA interno) |
| GDPR | dati di persone fisiche: base giuridica, minimizzazione, retention 24 mesi post-contratto, export e cancellazione su richiesta |
| Audit | ogni azione admin, ogni approvazione/rifiuto time-sheet, ogni accesso a contratto o fattura |
| Backup | giornaliero, retention 30 gg, **restore testato** trimestralmente |
| Accessibilità | WCAG 2.1 AA; stati mai comunicati dal solo colore (icona + etichetta testuale) |
| i18n | IT/EN sulle stringhe di interfaccia dal giorno uno |
