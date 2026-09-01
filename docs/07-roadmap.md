# 07 — Roadmap, stime e rischi

Stime riferite alla PWA custom (Next.js + Supabase) con **1 full-stack senior + 1 designer
part-time**. Le settimane sono di calendario, non uomo/giorno.

## Fase 0 — Validazione (1–2 settimane)

Landing con proposta di valore e form di raccolta interesse, anche su strumenti no-code. Serve a
verificare che esista domanda su entrambi i lati, non a costruire il prodotto. Interviste a 5
offerenti e 5 richiedenti: **la tassonomia delle skill si progetta qui**, con loro. Una tassonomia
sbagliata rende i filtri inutili e non si corregge dopo, quando ci sono già 300 profili classificati.

## Fase 1 — MVP (10–12 settimane)

| Blocco | Contenuto | Settimane |
|---|---|---|
| Fondamenta | setup, design system, auth, ruoli, RLS, layout delle 3 aree | 2 |
| Organizzazioni e utenti | registrazione doppia, attivazione admin, `access_expires_at`, job di scadenza | 1,5 |
| Catalogo risorse | wizard 4 step, tassonomia skill, moderazione admin | 2 |
| Ricerca mobile | filtri, chip, bottom sheet, card, match score, URL state | 2 |
| Richieste risorsa | invio, accettazione, svelamento identità | 1 |
| Contratti | upload PDF privato, versioni, date, tariffa concordata | 1 |
| **Rendicontazione** | settimane, griglia mobile, invio, approvazione/rifiuto, trigger di integrità | 2,5 |
| Admin | moderazione, scadenze, monitor time-sheet | 1,5 |

Fuori dall'MVP di proposito: offline, push, fatture, report, chat. **Dentro l'MVP di proposito:** la
rendicontazione completa, perché è ciò che rende il prodotto diverso da una directory.

## Fase 2 — Consolidamento (4–6 settimane)

PWA installabile e time-sheet offline · notifiche push · fatture e stato pagamenti · riepiloghi PDF
· dashboard admin di monitoraggio · report ed export CSV · promemoria automatici.

## Fase 3 — Crescita (a seguire)

Ricerche salvate con alert · chat di negoziazione in-app · e-signature (Yousign/Namirial) · API
pubblica · SSO enterprise · app store wrapper se richiesto dai clienti (Capacitor sulla stessa PWA).

## Confronto di percorso

| | WordPress | PWA custom |
|---|---|---|
| MVP | 6–8 settimane · 10–20k € | 12–16 settimane · 40–75k € |
| Licenze ricorrenti | 900–1.500 €/anno | 0 € |
| Infrastruttura | 40–100 €/mese | 40–80 €/mese |
| Vita utile attesa | 12–18 mesi | 5+ anni |
| Riuso in caso di migrazione | ~0% | 100% |

## Rischi principali

| Rischio | Impatto | Mitigazione |
|---|---|---|
| **Disintermediazione** (le parti si accordano fuori piattaforma) | 🔴 esistenziale per il modello | catalogo anonimo fino all'accettazione; contratto quadro con penale; il valore ricorrente è la rendicontazione, non il primo contatto |
| **Cold start** (catalogo vuoto → nessun richiedente → nessun offerente) | 🔴 alto | partire da un lato solo: 20 offerenti selezionati a mano prima di aprire ai richiedenti |
| **Tassonomia skill sbagliata** | 🟠 medio-alto | definirla in Fase 0 con utenti reali; alias e merge amministrativo; monitorare le ricerche senza risultati |
| **Adozione del time-sheet** ("continuiamo con Excel") | 🟠 medio-alto | < 15 secondi a settimana, promemoria push, e il fatto che senza time-sheet non parte la fattura |
| **Contestazioni sugli importi** | 🟠 medio | `rate_snapshot`, immutabilità del dato approvato, `timesheet_events` completo |
| **Dati di persone fisiche (GDPR)** | 🟠 medio | base giuridica chiara, minimizzazione, retention 24 mesi, DPA con l'offerente che carica i profili |
| **Scraping del catalogo** | 🟡 medio | rate limiting per account, accesso solo ad account attivati manualmente, watermark sui PDF |
| **Dipendenza da un solo sviluppatore** | 🟡 medio | tutto in git, migrazioni versionate, test e2e sui flussi critici, documentazione (questa) |

## Metriche da strumentare dal giorno uno

- Tempo medio di compilazione di una settimana (target < 15 s) e % compilate entro il lunedì.
- Tempo medio di approvazione (target < 48 h) e % di rifiuti.
- Richieste inviate → accettate → contrattualizzate (funnel).
- Tempo di risposta degli offerenti alle richieste.
- **Ricerche senza risultati, per skill**: dice quali profili acquisire per primi.
- Account in scadenza a 30 giorni e tasso di rinnovo.
