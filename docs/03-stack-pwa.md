# 03 — Proposta consigliata: PWA custom, leggera e mobile-first

## 1. Lo stack in una tabella

| Layer | Scelta | Perché |
|---|---|---|
| **Frontend** | **Next.js 15** (App Router, React 19, TypeScript) | Server Components → ricerca filtrata renderizzata lato server, veloce su 4G; un solo linguaggio su tutto lo stack |
| **Styling** | **Tailwind CSS v4** | Design system per token, mobile-first per costruzione (le utility partono da mobile e salgono con `sm:` `md:`), bundle CSS minimo |
| **Componenti** | **shadcn/ui** (Radix) | Accessibili di default, codice nel repo e non in `node_modules` → personalizzabili senza combattere la libreria. Include Sheet, Drawer, Dialog: i mattoni della UI mobile |
| **Animazioni** | **Motion** (ex Framer Motion) | Micro-interazioni fluide a 60 fps: swipe fra settimane, transizioni di stato, feedback tattile |
| **Stato server** | **TanStack Query** | Cache, optimistic update, retry automatico — la base del comportamento offline |
| **Stato URL** | **nuqs** | Filtri sincronizzati con l'URL → ricerche condivisibili e salvabili, back/forward funzionante |
| **Form** | **React Hook Form + Zod** | Un unico schema di validazione condiviso client e server |
| **Backend + DB** | **Supabase** (PostgreSQL 16 gestito) | Postgres vero + Auth + Storage + Realtime + **Row Level Security** in un unico servizio; niente server da mantenere |
| **Auth** | Supabase Auth (email+password, magic link, TOTP) | Magic link su mobile = niente password da digitare sul telefono. SSO enterprise disponibile in seguito |
| **File storage** | Supabase Storage (S3-compatibile) | Bucket **privati**, signed URL a scadenza breve, policy di accesso scritte in SQL |
| **Logica server** | Route Handlers + Server Actions Next.js; **Edge Functions** Supabase per i job | Le regole critiche (calcolo importi, cambi di stato) girano solo lato server |
| **Job schedulati** | `pg_cron` su Supabase (o GitHub Actions cron) | Scadenze account, promemoria del venerdì, fatture scadute |
| **Notifiche push** | **Web Push API** (VAPID) + service worker | Native su Android/desktop; su iOS ≥ 16.4 richiedono che la PWA sia installata in home screen |
| **Email** | **Resend** + React Email | Template versionati in git, deliverability transazionale seria |
| **Offline** | **Serwist** (successore di next-pwa) + IndexedDB (Dexie) | Time-sheet compilabile senza rete e sincronizzato al ritorno online |
| **PDF** | `@react-pdf/renderer` server-side | Riepiloghi di fatturazione generati dal server, non dal browser |
| **Osservabilità** | Sentry + Vercel Analytics | Errori e Core Web Vitals reali da dispositivo mobile |
| **Deploy** | **Vercel** (frontend) + Supabase (backend) | Da ~45 €/mese in totale; alternativa full self-hosted con Supabase su VPS + Docker |
| **CI/CD** | GitHub Actions | Lint, typecheck, test, migrazioni, preview deploy per PR |

**Costo licenze ricorrenti: 0 €.** Solo infrastruttura: Vercel Pro 20 $/mese + Supabase Pro 25 $/mese
coprono ampiamente i volumi previsti. Contro i 900–1.500 €/anno di licenze WordPress.

### Perché Supabase e non Firebase

Firebase è ottimo per app realtime con dati poco relazionali. Qui i dati sono **fortemente
relazionali**: organizzazioni → contratti → time-sheet → fatture, con vincoli di integrità
(`UNIQUE(contract_id, year, week)`, foreign key, immutabilità del dato approvato) e query
aggregate ("giorni fatturati per cliente nel trimestre"). Su Firestore queste diventano
denormalizzazione manuale e Cloud Function di consistenza; su Postgres sono **una riga di DDL**.

In più: la ricerca faccettata su Firestore è un limite noto (niente range multipli su campi diversi
nella stessa query) ed è esattamente ciò che serve al Richiedente. Supabase dà Postgres vero, SQL,
migrazioni versionate in git e nessun lock-in — a costo di rinunciare al realtime offline-first
nativo di Firestore, che qui si gestisce comunque con IndexedDB su un solo caso d'uso.

## 2. Architettura

```
   📱 PWA installata (service worker + IndexedDB)
        │
        ├─ offline: bozze time-sheet in coda locale
        │
   HTTPS│
        ▼
   ┌──────────────────────────────────────────────────┐
   │  Next.js 15 su Vercel                            │
   │  ├── /(public)      landing, SEO                 │
   │  ├── /offerente     risorse · time-sheet · docs  │
   │  ├── /richiedente   ricerca · approvazioni       │
   │  ├── /risorsa       solo la settimana corrente   │
   │  └── /admin         back-office                  │
   │  Server Actions ─ logica critica lato server     │
   └───────────────┬──────────────────────────────────┘
                   │ supabase-js (JWT in cookie httpOnly)
   ┌───────────────▼──────────────────────────────────┐
   │  Supabase                                        │
   │  ├── PostgreSQL 16 + Row Level Security          │
   │  ├── Auth (JWT, ruolo nei claim)                 │
   │  ├── Storage privato (contratti, fatture, CV)    │
   │  ├── Realtime (badge approvazioni live)          │
   │  └── pg_cron → scadenze, promemoria, solleciti   │
   └──────────────────────────────────────────────────┘
```

### Separazione delle tre dashboard

Ogni area è un **route group** con layout e middleware propri:

```ts
// middleware.ts — primo cancello, prima del rendering
const AREA_ROLES = {
  '/offerente':   ['OFFERENTE'],
  '/richiedente': ['RICHIEDENTE'],
  '/risorsa':     ['RESOURCE_USER'],
  '/admin':       ['ADMIN'],
} as const;
```

Il middleware è però solo ergonomia — evita il flash di una pagina non autorizzata. L'autorizzazione
**reale** sta nel database, con le policy RLS:

```sql
-- Un offerente vede solo le proprie risorse. Punto.
create policy "offerente vede le proprie risorse" on resources
  for all using (organization_id = auth_org_id());

-- Un time-sheet lo approva solo il richiedente di quel contratto,
-- e solo se è in stato SUBMITTED.
create policy "richiedente approva i propri timesheet" on timesheets
  for update using (
    status = 'SUBMITTED'
    and exists (
      select 1 from contracts c
      where c.id = timesheets.contract_id
        and c.client_org_id = auth_org_id()
    )
  );
```

Questa è la differenza sostanziale rispetto a WordPress: **una query dimenticata non può leggere i
dati di un altro tenant, nemmeno per errore.** Il controllo non è nel codice applicativo (dove si
dimentica) ma nel database (dove non si aggira).

### Immutabilità del dato approvato

Il requisito "un time-sheet approvato è la base di una fattura" si implementa con un **trigger**,
non con una convenzione:

```sql
create or replace function prevent_approved_timesheet_edit()
returns trigger language plpgsql as $$
begin
  if old.status in ('APPROVED','INVOICED','PAID')
     and current_setting('app.admin_override', true) is distinct from 'on' then
    raise exception 'Il time-sheet % è approvato e non è modificabile', old.id;
  end if;
  return new;
end $$;
```

Nessun percorso applicativo, nessuna Edge Function, nessuno script di manutenzione può aggirarlo.
L'unico override è esplicito, riservato all'Admin e tracciato in `audit_log`.

### Ricerca faccettata senza Elasticsearch

Postgres basta, se il modello è fatto bene:

- array denormalizzato `skill_ids uuid[]` sulla risorsa con indice **GIN** → "ha tutte queste skill"
  in una condizione `@>`;
- colonna generata `daily_rate_normalized` (tariffa oraria × 8) → lo slider budget confronta valori
  omogenei;
- `lat`/`lng` + PostGIS (o bounding box + haversine) → filtro per raggio in km;
- `tsvector` generato + GIN per il testo libero;
- **vista materializzata** `resource_search_index` aggiornata da trigger → una sola tabella larga da
  interrogare, senza join a runtime.

Con questo impianto 50.000 risorse e 5 filtri stanno sotto i 50 ms. Il passaggio a Meilisearch si fa
dopo, se e quando servono fuzzy search e sinonimi, dietro un'interfaccia `SearchService` isolata.

### PWA e offline: cosa si sincronizza davvero

Non serve rendere offline tutta l'app. Serve **una schermata**, quella del time-sheet:

1. Il service worker (Serwist) mette in cache l'app shell e le settimane aperte.
2. Le modifiche vanno in una coda in **IndexedDB** (Dexie), con un flag `pending_sync`.
3. Al ritorno online, la **Background Sync API** svuota la coda; su iOS, dove non è supportata, il
   flush avviene alla riapertura dell'app.
4. Il server è l'arbitro dei conflitti: se nel frattempo la settimana è stata inviata o approvata, la
   sincronizzazione fallisce in modo esplicito e l'utente vede "questa settimana è già stata
   approvata: le tue modifiche locali sono state scartate" — mai una sovrascrittura silenziosa.

Il resto dell'app (ricerca, contratti, admin) richiede rete: è accettabile, perché sono azioni
che si fanno da fermi.

### Notifiche

| Evento | Canale | Destinatario |
|---|---|---|
| Time-sheet inviato in approvazione | push + email | Richiedente |
| Time-sheet approvato / rifiutato | push + email | Offerente (+ Risorsa) |
| Promemoria settimana non compilata (ven 17:00, lun 09:00) | push | Offerente / Risorsa |
| Nuova richiesta risorsa | push + email | Offerente |
| Fattura caricata / stato pagamento aggiornato | email | controparte |
| Account in scadenza (T-30 / T-7) | email | organizzazione + Admin |
| Contratto in scadenza (T-60 / T-30 / T-7) | email | entrambe le parti |

Regola di prodotto: **push per ciò che è urgente e azionabile, email per ciò che va conservato.**
Ogni categoria disattivabile dalle impostazioni, altrimenti l'utente disattiva tutto.

## 3. Sicurezza — checklist di progetto

- RLS **attiva su ogni tabella**, senza eccezioni; il ruolo `service_role` usato solo nelle Edge
  Function server-side, mai esposto al client.
- Sessioni in cookie `httpOnly` + `SameSite=Lax` + `Secure`; rotazione al cambio privilegi.
- 2FA TOTP obbligatorio per gli Admin.
- Bucket Storage privati; download solo con signed URL a 5 minuti generato **dopo** il controllo di
  autorizzazione, con riga di audit. Nessun URL permanente.
- Upload diretto browser → Storage con URL firmato: i PDF non passano dal server applicativo.
  Scansione antivirus (ClamAV in Edge Function) prima di rendere il file scaricabile.
- Validazione **Zod** al confine di ogni Server Action: a runtime i tipi TypeScript non esistono.
- Rate limiting per IP **e per account** su login, ricerca e invio richieste — anti-scraping del
  catalogo, che è l'asset del business.
- Calcolo degli importi **solo lato server**, dalla tariffa del contratto: il client invia le
  quantità, mai i totali.
- CSP restrittiva, HSTS, security headers da `next.config`.
- Segreti in Vercel/Supabase environment, mai nel repository. `dependabot` + `npm audit` in CI.
- Impersonation admin tracciata, con banner permanente in UI e scadenza automatica di sessione.
- `audit_log` append-only su azioni admin, approvazioni e accessi ai documenti.
- Backup giornalieri con **restore testato**, non solo configurato.

## 4. Confronto sintetico

| Criterio | WordPress + plugin | PWA custom (Next + Supabase) |
|---|---|---|
| Time-to-market MVP | 6–8 settimane | 12–16 settimane |
| Costo sviluppo iniziale | 10–20k € | 40–75k € |
| Licenze ricorrenti | 900–1.500 €/anno | **0 €** |
| Infrastruttura | 40–100 €/mese | 40–80 €/mese |
| Esperienza mobile | responsive (desktop adattato) | **mobile-first, installabile** |
| Time-sheet offline | ❌ | ✅ |
| Notifiche push | plugin terzo (OneSignal) | native (Web Push) |
| Integrità del dato approvato | convenzione applicativa | **vincoli e trigger di DB** |
| Isolamento multi-tenant | da costruire, fragile | **RLS nativa** |
| Performance ricerca (10k risorse) | 1–3 s, da ottimizzare | < 100 ms |
| Superficie di attacco | alta (10 codebase terze) | contenuta |
| Testabilità automatica | scarsa | piena (unit, integration, e2e) |
| Tutta l'app è in git | ❌ (config nel DB) | ✅ |
| Costo di una feature nuova | cresce nel tempo | resta stabile |
| Scala oltre 200 collaborazioni | ❌ | ✅ |

## 5. Percorso pragmatico consigliato

1. **Fase 0 (1–2 settimane)** — landing + form di raccolta interesse (anche su Framer o WordPress).
   Serve a validare la domanda, non a costruire il prodotto.
2. **Fase 1 — MVP (10–12 settimane)** — registrazione e ruoli, catalogo risorse, moderazione admin,
   ricerca mobile con filtri, richiesta risorsa, contratti, **rendicontazione settimanale completa
   con approvazione**. Scadenze account gestite a mano dall'Admin, come da requisito.
3. **Fase 2 (4–6 settimane)** — PWA offline + push, fatture e stato pagamenti, dashboard admin di
   monitoraggio, report ed export CSV.
4. **Fase 3** — ricerche salvate con alert, e-signature, chat di negoziazione, API pubblica, SSO.

Il costo della Fase 1 è superiore all'equivalente WordPress, ma è **l'unico che non va buttato**
quando il portale inizia a funzionare.
