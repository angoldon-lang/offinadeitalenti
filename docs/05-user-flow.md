# 05 — User Flow

## 1. Vista d'insieme

```
OFFERENTE                    PIATTAFORMA / ADMIN                RICHIEDENTE
─────────                    ───────────────────                ───────────
registrazione ─────────────► verifica email
                             attivazione manuale
                             + access_expires_at ◄───────────── registrazione
       │                                                              │
carica risorsa ────────────► moderazione ──► pubblicata               │
       │                                        │                     │
       │                                        └──► compare in ricerca
       │                                                              │
richiesta ricevuta ◄───────────────────────────────── "Richiedi risorsa"
       │                                                              │
   accetta ──────────────► identità svelata ─────────────► negoziazione
       │                                                              │
       └────────────────► CONTRATTO firmato + caricato ◄──────────────┘
                                    │
                          si attiva la RENDICONTAZIONE
                                    │
   compila settimana ──────────► SUBMITTED ──────────► approva / rifiuta
       │                                                              │
   carica fattura ◄──── riepilogo di fatturazione ◄─── APPROVED       │
       │                                                              │
       └──────────► Admin aggiorna stato pagamento ──────────► PAGATA
```

## 2. Flow OFFERENTE

### 2.1 Registrazione e attivazione (giorno 0–2)

1. Landing → **"Offro competenze"** → form: ragione sociale, P.IVA, referente, email, password.
   Su mobile il form è in **3 step da 3 campi** con progress bar, non un modulo unico da 12 campi:
   il tasso di abbandono si dimezza.
2. Verifica email (link).
3. Account in `PENDING_APPROVAL`: si accede alla dashboard ma si vede solo un banner
   *"Profilo in attivazione — ti scriviamo entro 24 h"*, e la sola azione possibile è preparare le
   risorse in bozza. Non è tempo perso: quando l'Admin attiva, il catalogo è già pronto.
4. **L'Admin verifica e attiva**, impostando a mano `access_expires_at` sulla base del contratto
   cartaceo firmato (es. 12 mesi) e annotando il riferimento.
5. Email di benvenuto con scadenza indicata esplicitamente.

### 2.2 Caricamento di una risorsa (5–8 minuti)

Wizard in 4 step, uno per schermata su mobile, con salvataggio automatico ad ogni passo:

| Step | Contenuto |
|---|---|
| 1. Identità | Nome/ruolo, seniority, anni di esperienza, breve descrizione |
| 2. Competenze | Hard skill e soft skill da elenco con ricerca; per ciascuna livello 1–5 e anni. Suggerimenti automatici in base al titolo ("Senior React Developer" → propone React, TypeScript, Redux) |
| 3. Condizioni | Disponibilità, part/full-time, range di costo (€/gg o €/h), modalità di lavoro, località se non remoto |
| 4. Riepilogo | Anteprima della card **così come la vedrà il Richiedente** — è il momento in cui l'offerente capisce cosa sta vendendo, e migliora il profilo da solo |

→ **Invia in approvazione** (`IN_REVIEW`). Da qui la risorsa è in sola lettura.
→ Approvazione admin (SLA dichiarato: 24 h lavorative) → `PUBLISHED` + notifica push.
→ In caso di rifiuto: motivazione in evidenza, la risorsa torna in `DRAFT`, si corregge e si reinvia.

Scorciatoia decisiva: **duplica risorsa**. Il secondo profilo React si crea in 90 secondi.

### 2.3 Gestione delle richieste

1. Push: *"Nuova richiesta per il tuo Senior React Developer"*.
2. Schermata richiesta: brief del progetto, durata, data inizio, budget indicativo, **azienda in
   forma anonima** (settore e dimensione, non il nome).
3. **Accetta** → le identità si svelano reciprocamente, si apre il canale di contatto,
   lo stato passa a `IN_NEGOTIATION`. **Rifiuta** → motivazione opzionale.
4. Negoziazione fuori piattaforma nell'MVP (telefono, email), chat in-app nella V2.

### 2.4 Contratto

1. Accordo raggiunto → l'Offerente (o l'Admin) crea il contratto: risorsa, cliente, date, **tariffa
   concordata**, unità (€/gg o €/h).
2. Carica il **PDF firmato**. Il file va in bucket privato; nessun URL pubblico.
3. Stato `ACTIVE` → **la sezione Rendicontazione si attiva automaticamente** e compaiono le settimane
   da compilare a partire da `start_date`.
4. La risorsa passa a `OCCUPATA` (proposta automatica, confermabile con un tap: alcune risorse
   lavorano su più commesse part-time).

### 2.5 Rendicontazione settimanale — il gesto ricorrente

**Venerdì, 17:00** → push: *"Compila la settimana 12 per Acme S.p.A."*

1. Tap sulla notifica → si apre direttamente la settimana giusta (deep link), già precompilata con
   il calendario, weekend e festività italiane marcate.
2. Compilazione: per ogni giorno un tap su `0` / `½` / `1` (o stepper a ore, se il contratto è a
   ore). Default intelligente: 1 giornata sui feriali del periodo contrattuale, così nella settimana
   standard si corregge solo l'eccezione. Tipo giornata e nota opzionali per trasferte e assenze.
3. Il **totale settimanale e l'importo si aggiornano in tempo reale** in una barra fissa in basso.
4. Salvataggio automatico continuo, **anche offline** (coda locale, sincronizzazione al ritorno
   della rete).
5. **Invia in approvazione** → la settimana si blocca in scrittura, stato `SUBMITTED`.

Se il Richiedente rifiuta: push con la motivazione, la settimana torna scrivibile, si corregge e si
reinvia. Tutto il ciclo resta tracciato.

### 2.6 Fattura e incasso

1. A fine mese (o al periodo previsto dal contratto), le settimane `APPROVED` alimentano il
   **riepilogo di fatturazione**: giorni/ore per contratto × tariffa = imponibile. Esportabile in
   PDF/CSV.
2. L'Offerente emette la fattura nel **proprio** gestionale — la piattaforma non emette documenti
   fiscali — e ne **carica il PDF**, collegandolo alle settimane incluse. Quelle passano a `INVOICED`.
3. Stato pagamento visibile e aggiornato **manualmente dall'Admin**: `EMESSA` → `INVIATA` → `PAGATA`.
4. La sezione "Fatture" mostra emesso, incassato e scaduto, con filtri per cliente e periodo.

## 3. Flow RICHIEDENTE

### 3.1 Registrazione e attivazione

Identica per struttura a quella dell'Offerente: form dedicato → verifica email →
`PENDING_APPROVAL` → attivazione manuale dell'Admin con `access_expires_at`. Fino all'attivazione
il catalogo **non è visibile**: è la barriera che protegge l'asset principale, cioè l'elenco delle
risorse.

### 3.2 Ricerca (primo minuto nell'app — il momento che decide tutto)

1. Login → si atterra **direttamente sulla ricerca**, non su una home di benvenuto.
2. Barra di ricerca sticky in alto, riga di chip filtro orizzontali sotto (Skill · Esperienza ·
   Budget · Modalità · Disponibilità).
3. Tap su un chip → **bottom sheet** con le opzioni; il conteggio dei risultati si aggiorna
   mentre si sceglie, e il pulsante di conferma dice *"Mostra 14 risorse"*.
4. Risultati in **card** con: titolo, seniority, top 4 skill, tariffa, modalità, città,
   disponibilità, **match score** e badge di stato (🟢 Attiva / 🟠 Occupata).
5. Azioni: 💛 salva in shortlist · ⚖️ confronta (fino a 4) · tap → dettaglio.
6. Filtri sincronizzati con l'URL → la ricerca è condivisibile con un collega e salvabile con alert.

### 3.3 Richiesta risorsa

1. Dal dettaglio (anonimo) → **"Richiedi risorsa"**.
2. Form breve: progetto, durata stimata, data inizio, budget, note. Precompilato con i filtri già
   usati in ricerca — non si chiede due volta la stessa informazione.
3. Invio → l'Offerente riceve la push. Stato tracciato in "Le mie richieste".
4. Accettazione → identità svelate, contatti disponibili, si negozia.
5. Nessuna risposta entro 7 giorni → la richiesta scade automaticamente e il Richiedente viene
   invitato a scegliere un profilo alternativo (con 3 suggerimenti simili già pronti).

### 3.4 Contratto

1. Il contratto viene caricato dall'Offerente o dall'Admin; il Richiedente lo trova nella propria
   sezione **Contratti**, con date, tariffa e PDF scaricabile.
2. Può caricare la propria copia controfirmata (versione 2 dello stesso documento).

### 3.5 Approvazione settimanale — il gesto ricorrente (30 secondi)

**Lunedì, 09:00** → push: *"3 settimane da approvare"*.

1. Tap → lista delle settimane in attesa, ordinate per data, ciascuna con fornitore, risorsa,
   totale e importo.
2. Tap su una → dettaglio giorno per giorno, con note e tipo giornata; totale e importo in evidenza.
3. **Approva** (un tap, con conferma) oppure **Rifiuta**, che richiede una **motivazione
   obbligatoria** — senza, il rifiuto è un vicolo cieco per l'Offerente.
4. Approvato → immutabile, importo congelato alla tariffa di contratto, pronto per la fatturazione.

Se il contratto prevede l'**auto-approvazione** (opzionale, disattivata di default), il silenzio
oltre N giorni vale approvazione, con notifica di preavviso il giorno prima. Da attivare solo con
accordo esplicito fra le parti.

### 3.6 Fatture ricevute

Elenco delle fatture con periodo, importo, settimane incluse (espandibili), PDF scaricabile e stato
del pagamento aggiornato dall'Admin. Il Richiedente non modifica lo stato: lo consulta.

## 4. Flow AMMINISTRATORE

**Routine quotidiana** (5 minuti da mobile):

1. **Coda di moderazione**: profili in attesa → apri, verifica coerenza tra titolo, skill e tariffe
   → approva o richiedi modifiche con motivazione.
2. **Scadenze**: elenco account in scadenza a 30/7 giorni → contatto commerciale → proroga in due
   tap, con nota e riferimento al nuovo contratto cartaceo.
3. **Monitor rendicontazione**: matrice settimane × contratti con i quattro stati a colpo d'occhio.
   Settimane non compilate da > 5 giorni → sollecito in un tap. Settimane in attesa di approvazione
   da > 5 giorni → sollecito al Richiedente.
4. **Pagamenti**: fatture scadute per cliente → aggiornamento stato → export CSV per la contabilità.

**Su richiesta**: caricamento contratti per conto delle parti, override di un time-sheet approvato
(con motivazione obbligatoria, tracciata in `audit_log`), sospensione account, impersonation per
supporto (tracciata, con banner permanente in UI).

**Mensile**: report su match creati, tempo medio di risposta degli offerenti, tempo medio di
approvazione dei time-sheet, giorni fatturati, e **skill cercate senza risultati** — il dato che
dice quali profili acquisire per primi.

## 5. Il ciclo di vita in una riga

```
Registrazione → Attivazione admin (+ scadenza manuale) → Profili caricati → Moderazione →
Pubblicazione → Ricerca → Richiesta → Accettazione → Negoziazione → CONTRATTO (PDF + tariffa) →
[ ogni settimana: compilazione → invio → approvazione ] → riepilogo → fattura PDF →
stato pagamento → PAGATA
```

Le due parentesi quadre sono il punto: tutto ciò che precede accade **una volta**, ciò che sta
dentro accade **52 volte l'anno per ogni collaborazione**. È lì che si gioca l'adozione del
prodotto, ed è per questo che la schermata della rendicontazione merita il design più curato di
tutta l'applicazione — vedi [`06-ui-ux-mobile.md`](06-ui-ux-mobile.md).
