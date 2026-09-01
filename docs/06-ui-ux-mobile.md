# 06 — UI/UX Mobile-First

## 1. Principi

1. **375 px è il progetto, non il caso limite.** Si disegna sul telefono e si espande verso il
   desktop, mai il contrario. Il desktop è una versione più larga della stessa app, non un'app
   diversa.
2. **Zona del pollice.** Le azioni primarie stanno **in basso**, dove il pollice arriva. In alto ci
   sono titoli e contesto, non pulsanti. Target tap minimo 44×44 px, spaziatura ≥ 8 px.
3. **Un compito per schermata.** Compilare la settimana, approvare, cercare: mai due insieme.
4. **Bottom sheet invece di modali.** Salgono dal basso, si chiudono con lo swipe, non nascondono il
   contesto. Sono il pattern nativo che l'utente conosce già.
5. **Feedback immediato.** Ogni tap ha una risposta entro 100 ms: colore, ridimensionamento,
   vibrazione leggera (`navigator.vibrate(10)`). Salvataggio ottimistico, con rollback visibile in
   caso di errore.
6. **Lo stato non è mai solo colore.** Sempre colore **+ icona + etichetta**: per i daltonici
   (8% degli uomini) e per chi guarda lo schermo al sole.

## 2. Design system

### Colori

```
BRAND        indigo-600  #4F46E5    azioni primarie, elementi attivi
             indigo-50   #EEF2FF    superfici selezionate

STATI (semantici, usati ovunque allo stesso modo)
  DA COMPILARE  slate-400  #94A3B8   ○  neutro, non urla
  IN ATTESA     amber-500  #F59E0B   ◔  "sta a qualcun altro"
  APPROVATO     emerald-500 #10B981  ✓  chiuso, positivo
  RIFIUTATO     rose-500   #F43F5E   ✕  richiede azione
  FATTURATO     violet-500 #8B5CF6   ▤  fase amministrativa
  PAGATO        emerald-700 #047857  €  fine del ciclo

NEUTRI       slate-50 → slate-900     fondo #F8FAFC, testo #0F172A
```

Ogni stato ha una coppia fissa **colore + icona** riusata identica in card, badge, filtri, grafici e
notifiche. Dopo due giorni d'uso l'utente riconosce lo stato dal solo colore periferico, senza
leggere — e questo è il vero obiettivo del sistema cromatico.

### Tipografia e spazi

Inter variable (o system font stack per risparmiare 100 KB). Scala: 12 / 14 / 16 / 20 / 24 / 32.
**Mai sotto i 16 px per gli input**, altrimenti iOS zooma automaticamente sul focus. Spaziatura su
griglia da 4 px; raggi 12 px per le card, 16 px per i bottom sheet, `full` per i chip.

### Navigazione

**Tab bar fissa in basso**, 4 voci al massimo, diverse per ruolo:

| Offerente | Richiedente | Admin |
|---|---|---|
| 🏠 Home · 👥 Risorse · 📅 **Ore** · 📄 Documenti | 🔍 **Cerca** · 💛 Shortlist · ✅ **Approvazioni** · 📄 Documenti | 📊 Home · ⏳ Moderazione · 📅 Monitor · 💶 Pagamenti |

La voce con azioni pendenti porta un **badge numerico**. Su desktop la tab bar diventa una sidebar
laterale, con le stesse voci nello stesso ordine.

## 3. La schermata "Rendicontazione Settimanale"

È la schermata che l'utente aprirà 52 volte l'anno. Merita il design più curato dell'app.
Obiettivo dichiarato: **compilare una settimana standard in meno di 15 secondi.**

### 3.1 Wireframe (375 px)

```
┌─────────────────────────────────────┐
│ ←   Acme S.p.A. · M. Rossi      ⋮   │  header compatto: cliente + risorsa
├─────────────────────────────────────┤
│  ‹   SETTIMANA 12                ›  │  swipe orizzontale per cambiare settimana
│      16 – 22 marzo 2026             │
│      ● Bozza · salvata ora          │  stato + autosave discreto
├─────────────────────────────────────┤
│                                     │
│  LUN 16    [ 0 ]  [ ½ ]  [ ●1 ]  ⋮ │  ← segmented control, 1 tap
│  MAR 17    [ 0 ]  [ ½ ]  [ ●1 ]  ⋮ │
│  MER 18    [ 0 ]  [ ●½]  [  1 ]  ⋮ │
│            ↳ 🚗 Trasferta Milano    │  nota inline se presente
│  GIO 19    [ 0 ]  [ ½ ]  [ ●1 ]  ⋮ │
│  VEN 20    [ ●0]  [ ½ ]  [  1 ]  ⋮ │
│            ↳ 🏖 Permesso            │
│                                     │
│  ─── weekend ───────────────────    │
│  SAB 21    [ ●0]  [ ½ ]  [  1 ]     │  righe attenuate, collassabili
│  DOM 22    [ ●0]  [ ½ ]  [  1 ]     │
│                                     │
├─────────────────────────────────────┤
│  TOTALE   3,5 giorni    ~ 1.575 €   │  barra sticky, si aggiorna live
│  ┌───────────────────────────────┐  │
│  │   INVIA IN APPROVAZIONE   →   │  │  CTA a piena larghezza, zona pollice
│  └───────────────────────────────┘  │
└─────────────────────────────────────┘
```

Se il contratto è **a ore**, la riga diventa uno stepper `−  8,0 h  +` con tastiera numerica al tap
sul valore. La struttura resta identica: cambia il controllo, non il layout.

### 3.2 Le sette decisioni di design che contano

**1. Una riga per giorno, non una griglia.**
La griglia settimanale a matrice è il pattern desktop dei gestionali. Su 375 px produce celle da
40 px, impossibili da centrare col pollice. L'elenco verticale di 7 righe alte 56 px si scorre con
naturalezza ed è leggibile a colpo d'occhio.

**2. Tre pulsanti, non un campo numerico.**
Nel 95% dei casi la risposta è 0, mezza giornata o una giornata intera. Un segmented control da tre
opzioni azzera la digitazione. Il caso residuo (0,25, ore precise) sta nel menu `⋮`. **Un tap invece
di aprire tastiera, digitare, chiudere**: è la differenza tra 15 secondi e 2 minuti.

**3. Precompilazione intelligente.**
All'apertura la settimana arriva già compilata: 1 giornata sui feriali coperti dal contratto, 0 su
weekend e festività italiane (evidenziate: *"25 aprile — Festa della Liberazione"*). L'utente
**corregge le eccezioni** invece di inserire tutto. La settimana normale si conferma senza toccare
nulla.

**4. Il totale è sempre visibile e sempre vivo.**
Barra sticky in basso con giorni e importo stimato. Ogni tap la aggiorna con una transizione
numerica animata (200 ms): è il feedback che dice *"ti ho capito"*. Mostrare l'importo in euro, non
solo i giorni, riduce gli errori — l'utente riconosce subito un totale sbagliato quando lo vede in
denaro.

**5. Swipe orizzontale fra le settimane.**
Trascinamento con transizione elastica (Motion, `spring`). Recuperare tre settimane arretrate
diventa un gesto continuo invece di tre navigazioni. Le settimane già inviate appaiono in sola
lettura, con il badge del loro stato in evidenza.

**6. Autosave, senza pulsante "Salva".**
Debounce 800 ms, indicatore testuale minimo (*"salvata ora"*). Offline: il testo diventa
*"salvata sul dispositivo · sincronizzo appena torna la rete"* con icona ☁️↑. Nessun blocco,
nessun alert modale: l'utente continua a lavorare.

**7. L'invio è deliberato, la modifica no.**
Compilare è leggero e reversibile; **inviare** è un impegno: bottom sheet di conferma con riepilogo
(*"3,5 giorni · 1.575 € · a Acme S.p.A."*), pulsante primario e possibilità di annullare. Dopo
l'invio la schermata passa in sola lettura con un'animazione di "chiusura" (le righe si compattano,
compare il badge ◔ In attesa): il cambio di stato deve **sentirsi**.

### 3.3 Micro-interazioni

| Interazione | Comportamento | Perché |
|---|---|---|
| Tap su 0 / ½ / 1 | pillola si riempie (scale 0,96 → 1, 150 ms) + vibrazione 10 ms | conferma tattile senza guardare |
| Aggiornamento totale | numero che scorre (count-up 200 ms) | l'occhio segue il cambiamento |
| Cambio settimana | slide elastico + skeleton se la rete è lenta | mai schermo bianco |
| Invio | pulsante → spinner → ✓ + confetti brevissimi (600 ms) | chiude il compito con soddisfazione |
| Approvazione (Richiedente) | swipe destro sulla card = approva, sinistro = rifiuta, con colore progressivo | approvare 5 settimane in 20 secondi |
| Rifiuto | il campo motivazione si apre automaticamente, con focus | il rifiuto senza spiegazione è inutile |
| Pull-to-refresh | ricarica lo stato | gesto atteso, costa nulla |
| Offline | banner ambra fisso in alto: *"Offline — le modifiche sono salvate"* | rassicura invece di allarmare |

L'animazione ha una funzione: dice cosa è successo e dove. Tutto ciò che non comunica uno stato va
tolto. Rispettare sempre `prefers-reduced-motion`.

### 3.4 Vista di approvazione (Richiedente)

```
┌─────────────────────────────────────┐
│  Approvazioni              3 ⬤      │
├─────────────────────────────────────┤
│ ┌─────────────────────────────────┐ │
│ │ ◔  Settimana 12 · 16–22 mar     │ │
│ │    M. Rossi · Senior React Dev  │ │
│ │    3,5 giorni        1.575,00 € │ │
│ │  ─────────────────────────────  │ │
│ │  [ Dettaglio ]   [ ✓ Approva ]  │ │
│ └─────────────────────────────────┘ │   ← swipe → per approvare al volo
│ ┌─────────────────────────────────┐ │
│ │ ◔  Settimana 12 · 16–22 mar     │ │
│ │    L. Bianchi · DevOps Engineer │ │
│ │    5,0 giorni        2.000,00 € │ │
│ └─────────────────────────────────┘ │
├─────────────────────────────────────┤
│  [ ✓ Approva tutte (3) ]            │  azione bulk, con conferma
└─────────────────────────────────────┘
```

"Approva tutte" è la funzione più richiesta dai responsabili con 10 fornitori — ma va protetta da un
riepilogo di conferma con l'importo totale, perché un tap distratto approva 15.000 €.

### 3.5 Stati vuoti ed errori

- **Nessuna settimana da compilare** → illustrazione leggera + *"Tutto in ordine. La prossima
  settimana si apre lunedì 23."* Uno stato vuoto conferma che il sistema funziona, non lascia un
  buco bianco.
- **Nessun risultato in ricerca** → *"Nessuna risorsa con questi filtri"* + i due filtri più
  restrittivi rimovibili con un tap + 3 alternative simili. Mai un vicolo cieco.
- **Errore di sincronizzazione** → messaggio esplicito con l'azione: *"Questa settimana è stata già
  approvata: le tue modifiche locali non sono state applicate."* Mai una sovrascrittura silenziosa,
  mai un "Errore 500".

## 4. Il resto dell'app in breve

**Ricerca (Richiedente)** — barra sticky + chip filtro orizzontali scrollabili; tap su un chip →
bottom sheet con conteggio live (*"Mostra 14 risorse"*); card verticali con match score e badge di
stato; toggle card/lista; filtri nell'URL.

**Card risorsa** — titolo e seniority in evidenza, top 4 skill come chip, tariffa in grande
(è il dato che si cerca per primo), modalità + città, badge 🟢 Attiva / 🟠 Occupata, e il pulsante
**"Richiedi risorsa"** a piena larghezza in fondo alla card. Nessuna informazione che identifichi
l'azienda offerente.

**Dashboard Offerente** — in cima *cosa devo fare oggi* (settimane da compilare, richieste da
evadere), poi le metriche. Le dashboard che aprono con i grafici falliscono su mobile: l'utente apre
l'app per fare una cosa, non per contemplare i numeri.

**Admin su mobile** — moderazione e solleciti sono perfettamente utilizzabili da telefono e vanno
progettati per esserlo. Le viste a matrice (monitor settimane × contratti) e i report restano
desktop-first, con su mobile una versione a elenco filtrabile: forzare una matrice 20×52 su 375 px
non aiuta nessuno.

## 5. Checklist di accettazione mobile

- [ ] Compilazione di una settimana standard in < 15 secondi, una mano sola
- [ ] Approvazione di 3 settimane in < 30 secondi
- [ ] Nessun target tap sotto 44×44 px
- [ ] Nessun input con `font-size` < 16 px (zoom automatico iOS)
- [ ] App installabile (manifest + service worker + icone) e apribile offline
- [ ] Time-sheet compilabile e salvabile senza rete
- [ ] Contrasto ≥ 4,5:1 su tutti i testi; nessuno stato comunicato dal solo colore
- [ ] LCP < 2,5 s su 4G simulata, bundle iniziale < 200 KB gzip
- [ ] Zero scroll orizzontale a 320 px
- [ ] `prefers-reduced-motion` rispettato ovunque
- [ ] Navigazione da tastiera e screen reader completa sulle schermate di time-sheet e approvazione
