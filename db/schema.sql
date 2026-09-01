-- =============================================================================
-- Officina dei Talenti — Resource & Skill Sharing
-- Schema PostgreSQL 16 (Supabase-ready) — bozza di riferimento
--
-- Convenzioni:
--   * PK uuid (generabili anche offline dal client)
--   * importi numeric, mai float
--   * date di calendario in `date`, istanti in `timestamptz`
--   * enum nativi per gli stati
--   * nessun pagamento automatico: scadenze e stati pagamento sono manuali
-- =============================================================================

create extension if not exists "pgcrypto";
create extension if not exists "pg_trgm";
-- create extension if not exists "postgis";  -- opzionale, per il filtro geografico

-- =============================================================================
-- 1. ENUM
-- =============================================================================

create type org_type          as enum ('OFFERENTE','RICHIEDENTE');
create type org_status        as enum ('PENDING_APPROVAL','ACTIVE','GRACE','EXPIRED','SUSPENDED');
create type platform_role     as enum ('OFFERENTE','RICHIEDENTE','RESOURCE_USER','ADMIN');
create type org_role          as enum ('OWNER','MEMBER');

create type skill_category    as enum ('HARD','SOFT');
create type seniority_level   as enum ('JUNIOR','MID','SENIOR','TECH_LEAD');
create type availability_type as enum ('IMMEDIATA','ENTRO_1_MESE','ENTRO_3_MESI');
create type engagement_type   as enum ('PART_TIME','FULL_TIME');
create type rate_unit         as enum ('DAILY','HOURLY');
create type work_mode         as enum ('ONSITE','REMOTO','IBRIDO');
create type resource_op_status  as enum ('ATTIVA','OCCUPATA');
create type resource_pub_status as enum ('DRAFT','IN_REVIEW','PUBLISHED','REJECTED','ARCHIVED');

create type request_status    as enum ('REQUESTED','ACCEPTED','DECLINED','IN_NEGOTIATION','CONTRACTED','EXPIRED','CLOSED');
create type contract_status   as enum ('DRAFT','ACTIVE','SUSPENDED','EXPIRED','TERMINATED');
create type doc_visibility    as enum ('PRIVATO_OFFERENTE','PRIVATO_RICHIEDENTE','CONDIVISO','SOLO_ADMIN');
create type contract_doc_type as enum ('NDA','QUADRO','ORDINE','SOW','ADDENDUM','ALTRO');

create type timesheet_status  as enum ('DRAFT','SUBMITTED','APPROVED','REJECTED','INVOICED','PAID');
create type day_type          as enum ('LAVORO','TRASFERTA','FERIE','PERMESSO','MALATTIA','FESTIVO','NON_LAVORATO');
create type payment_status    as enum ('DA_EMETTERE','EMESSA','INVIATA','PAGATA','SCADUTA','CONTESTATA');

-- =============================================================================
-- 2. IDENTITÀ, ORGANIZZAZIONI, DURATA ACCOUNT
-- =============================================================================

create table organizations (
  id                    uuid primary key default gen_random_uuid(),
  type                  org_type    not null,
  legal_name            text        not null,
  vat_number            text unique,
  tax_code              text,
  sector                text,
  size_range            text,
  website               text,
  billing_address       jsonb,
  status                org_status  not null default 'PENDING_APPROVAL',

  -- "Durata del profilo": impostata MANUALMENTE dall'admin, legata a contratto esterno
  access_expires_at     date,
  grace_ends_at         date,
  external_contract_ref text,
  admin_notes           text,

  approved_by           uuid,
  approved_at           timestamptz,
  created_at            timestamptz not null default now(),
  updated_at            timestamptz not null default now(),
  deleted_at            timestamptz,

  constraint grace_after_expiry check (grace_ends_at is null
                                    or access_expires_at is null
                                    or grace_ends_at >= access_expires_at)
);

comment on column organizations.access_expires_at is
  'Scadenza account impostata a mano dall''Admin. Nessun rinnovo automatico, nessun gateway di pagamento.';

-- Storico delle proroghe: risponde a "perché questo account scade a giugno?"
create table account_extensions (
  id               uuid primary key default gen_random_uuid(),
  organization_id  uuid not null references organizations(id) on delete cascade,
  previous_expiry  date,
  new_expiry       date not null,
  reason           text,
  external_ref     text,
  created_by       uuid not null,
  created_at       timestamptz not null default now()
);

create table users (
  id               uuid primary key default gen_random_uuid(),  -- = auth.users.id su Supabase
  organization_id  uuid references organizations(id) on delete cascade,
  email            citext not null unique,
  full_name        text   not null,
  phone            text,
  platform_role    platform_role not null,
  org_role         org_role      not null default 'MEMBER',
  -- valorizzato solo per platform_role = 'RESOURCE_USER': la persona compila le proprie giornate
  resource_id      uuid,
  is_active        boolean not null default true,
  mfa_enabled      boolean not null default false,
  last_login_at    timestamptz,
  created_at       timestamptz not null default now(),
  updated_at       timestamptz not null default now(),

  constraint admin_has_no_org check (platform_role <> 'ADMIN' or organization_id is null),
  constraint org_user_has_org check (platform_role = 'ADMIN' or organization_id is not null)
);

-- =============================================================================
-- 3. CATALOGO RISORSE
-- =============================================================================

create table skills (
  id         uuid primary key default gen_random_uuid(),
  slug       text unique not null,
  name       text not null,
  category   skill_category not null,
  parent_id  uuid references skills(id),
  aliases    text[] not null default '{}',   -- sinonimi per la ricerca ("JS" -> "JavaScript")
  is_active  boolean not null default true,
  created_at timestamptz not null default now()
);

create table resources (
  id                  uuid primary key default gen_random_uuid(),
  organization_id     uuid not null references organizations(id) on delete cascade,

  title               text not null,                    -- "Senior React Developer"
  description         text,
  seniority           seniority_level  not null,
  availability        availability_type not null,
  engagement          engagement_type   not null,
  available_from      date,

  rate_min            numeric(10,2) not null,
  rate_max            numeric(10,2) not null,
  rate_unit           rate_unit not null,
  currency            char(3) not null default 'EUR',
  rate_negotiable     boolean not null default false,

  -- Normalizzazione oraria -> giornaliera (1 gg = 8 h): rende lo slider budget confrontabile
  daily_rate_min_norm numeric(10,2) generated always as
      (case when rate_unit = 'HOURLY' then rate_min * 8 else rate_min end) stored,
  daily_rate_max_norm numeric(10,2) generated always as
      (case when rate_unit = 'HOURLY' then rate_max * 8 else rate_max end) stored,

  work_mode           work_mode not null,
  city                text,
  province            text,
  country             char(2) default 'IT',
  lat                 numeric(9,6),
  lng                 numeric(9,6),
  onsite_radius_km    int,

  languages           text[] not null default '{}',
  industries          text[] not null default '{}',
  certifications      text[] not null default '{}',
  career_start_year   int,
  cv_storage_key      text,                              -- visibile solo dopo richiesta accettata

  -- Due assi INDIPENDENTI: la moderazione non deve ripartire quando la risorsa si libera
  operational_status  resource_op_status  not null default 'ATTIVA',
  publication_status  resource_pub_status not null default 'DRAFT',
  rejection_reason    text,
  published_at        timestamptz,
  reviewed_by         uuid,

  -- Denormalizzazione per la ricerca: filtro multi-skill in una sola condizione (GIN + @>)
  skill_ids           uuid[] not null default '{}',
  search_vector       tsvector generated always as (
                        to_tsvector('simple', coalesce(title,'') || ' ' || coalesce(description,''))
                      ) stored,

  created_at          timestamptz not null default now(),
  updated_at          timestamptz not null default now(),
  deleted_at          timestamptz,

  constraint rate_range_valid check (rate_max >= rate_min and rate_min >= 0),
  constraint location_required_if_onsite check (
    work_mode = 'REMOTO' or (city is not null and country is not null)
  ),
  constraint rejection_reason_required check (
    publication_status <> 'REJECTED' or rejection_reason is not null
  )
);

alter table users
  add constraint users_resource_fk foreign key (resource_id) references resources(id) on delete set null;

create table resource_skills (
  resource_id uuid not null references resources(id) on delete cascade,
  skill_id    uuid not null references skills(id)    on delete restrict,
  level       smallint check (level between 1 and 5),
  years       smallint check (years >= 0),
  primary key (resource_id, skill_id)
);

-- Richiesta "Richiedi risorsa" -> avvio negoziazione
create table resource_requests (
  id                 uuid primary key default gen_random_uuid(),
  resource_id        uuid not null references resources(id)     on delete cascade,
  client_org_id      uuid not null references organizations(id) on delete cascade,
  created_by         uuid not null references users(id),
  status             request_status not null default 'REQUESTED',
  project_brief      text not null,
  estimated_duration text,
  desired_start_date date,
  budget_hint        numeric(10,2),
  budget_unit        rate_unit,
  responded_at       timestamptz,
  responded_by       uuid references users(id),
  decline_reason     text,
  expires_at         timestamptz not null default (now() + interval '7 days'),
  created_at         timestamptz not null default now(),
  updated_at         timestamptz not null default now()
);

-- =============================================================================
-- 4. CONTRATTI
-- =============================================================================

create table contracts (
  id                      uuid primary key default gen_random_uuid(),
  code                    text unique not null,               -- CTR-2026-0041
  provider_org_id         uuid not null references organizations(id) on delete restrict,
  client_org_id           uuid not null references organizations(id) on delete restrict,
  resource_id             uuid references resources(id) on delete set null,
  request_id              uuid references resource_requests(id) on delete set null,

  status                  contract_status not null default 'DRAFT',
  start_date              date not null,
  end_date                date not null,

  -- Tariffa concordata: sorgente di verità per TUTTI i calcoli di rendicontazione.
  -- Il range sul profilo risorsa resta indicativo.
  agreed_rate             numeric(10,2) not null check (agreed_rate > 0),
  rate_unit               rate_unit not null,
  currency                char(3) not null default 'EUR',

  timesheet_required      boolean not null default true,
  auto_approve_after_days int,                                 -- opzionale, off di default
  visibility              doc_visibility not null default 'CONDIVISO',
  notes                   text,

  created_at              timestamptz not null default now(),
  updated_at              timestamptz not null default now(),
  deleted_at              timestamptz,

  constraint parties_differ  check (provider_org_id <> client_org_id),
  constraint dates_valid     check (end_date >= start_date)
);

create table contract_documents (
  id            uuid primary key default gen_random_uuid(),
  contract_id   uuid not null references contracts(id) on delete cascade,
  doc_type      contract_doc_type not null default 'ORDINE',
  version       int  not null default 1,
  file_name     text not null,
  storage_key   text not null,                 -- bucket PRIVATO, mai URL pubblico
  file_size     bigint,
  file_hash     text,                          -- SHA-256: prova che il PDF non è cambiato
  visibility    doc_visibility not null default 'CONDIVISO',
  uploaded_by   uuid not null references users(id),
  signed_at     date,
  created_at    timestamptz not null default now(),

  unique (contract_id, doc_type, version)
);

-- =============================================================================
-- 5. RENDICONTAZIONE SETTIMANALE  (il cuore del sistema)
-- =============================================================================

create table timesheets (
  id                uuid primary key default gen_random_uuid(),
  contract_id       uuid not null references contracts(id) on delete cascade,

  iso_year          int  not null,
  iso_week          int  not null check (iso_week between 1 and 53),
  week_start        date not null,             -- lunedì
  week_end          date not null,             -- domenica

  status            timesheet_status not null default 'DRAFT',
  unit              rate_unit not null,        -- copiata dal contratto alla creazione

  -- Ricalcolati da trigger sui timesheet_days: MAI scritti dal client
  total_quantity    numeric(6,2) not null default 0,
  -- Tariffa congelata all'approvazione: una rinegoziazione non riscrive il passato
  rate_snapshot     numeric(10,2),
  amount            numeric(12,2),

  submitted_by      uuid references users(id),
  submitted_at      timestamptz,
  reviewed_by       uuid references users(id),
  reviewed_at       timestamptz,
  rejection_reason  text,

  invoice_id        uuid,
  notes             text,
  created_at        timestamptz not null default now(),
  updated_at        timestamptz not null default now(),

  -- Impedisce fisicamente il doppio rendiconto della stessa settimana
  constraint uq_timesheet_week unique (contract_id, iso_year, iso_week),
  constraint week_dates_valid  check (week_end = week_start + 6),
  constraint rejection_reason_required check (
    status <> 'REJECTED' or rejection_reason is not null
  ),
  constraint approved_has_snapshot check (
    status not in ('APPROVED','INVOICED','PAID') or (rate_snapshot is not null and amount is not null)
  )
);

create table timesheet_days (
  id           uuid primary key default gen_random_uuid(),
  timesheet_id uuid not null references timesheets(id) on delete cascade,
  work_date    date not null,
  day_type     day_type not null default 'NON_LAVORATO',
  quantity     numeric(4,2) not null default 0 check (quantity >= 0 and quantity <= 24),
  note         text,
  created_at   timestamptz not null default now(),
  updated_at   timestamptz not null default now(),

  constraint uq_timesheet_day unique (timesheet_id, work_date)
);

-- Log append-only delle transizioni: "chi ha approvato questa settimana e quando"
create table timesheet_events (
  id           uuid primary key default gen_random_uuid(),
  timesheet_id uuid not null references timesheets(id) on delete cascade,
  from_status  timesheet_status,
  to_status    timesheet_status not null,
  actor_id     uuid references users(id),
  reason       text,
  created_at   timestamptz not null default now()
);

-- Festività: precompilano la griglia settimanale
create table public_holidays (
  holiday_date date primary key,
  name         text not null,
  country      char(2) not null default 'IT'
);

-- =============================================================================
-- 6. FATTURE E PAGAMENTI  (tutto manuale, nessun gateway)
-- =============================================================================

create table invoices (
  id               uuid primary key default gen_random_uuid(),
  number           text,                       -- numero della fattura reale, emessa fuori dal sistema
  provider_org_id  uuid not null references organizations(id) on delete restrict,
  client_org_id    uuid not null references organizations(id) on delete restrict,
  contract_id      uuid references contracts(id) on delete set null,

  period_start     date not null,
  period_end       date not null,
  issue_date       date,
  due_date         date,

  amount_net       numeric(12,2) not null default 0,
  vat_rate         numeric(5,2)  not null default 22.00,
  amount_total     numeric(12,2) not null default 0,
  currency         char(3) not null default 'EUR',

  payment_status   payment_status not null default 'DA_EMETTERE',
  paid_at          date,
  paid_amount      numeric(12,2),

  file_name        text,
  storage_key      text,                       -- il PDF caricato da Offerente o Admin
  uploaded_by      uuid references users(id),
  notes            text,

  created_at       timestamptz not null default now(),
  updated_at       timestamptz not null default now(),

  constraint period_valid check (period_end >= period_start),
  constraint unique_number_per_provider unique (provider_org_id, number)
);

alter table timesheets
  add constraint timesheets_invoice_fk foreign key (invoice_id) references invoices(id) on delete set null;

-- I pagamenti parziali esistono: un solo campo paid_at non li rappresenta
create table invoice_payments (
  id           uuid primary key default gen_random_uuid(),
  invoice_id   uuid not null references invoices(id) on delete cascade,
  paid_on      date not null,
  amount       numeric(12,2) not null check (amount > 0),
  method       text,
  reference    text,
  recorded_by  uuid not null references users(id),
  created_at   timestamptz not null default now()
);

-- =============================================================================
-- 7. TRASVERSALI
-- =============================================================================

create table saved_searches (
  id           uuid primary key default gen_random_uuid(),
  user_id      uuid not null references users(id) on delete cascade,
  name         text not null,
  filters      jsonb not null,
  alert_enabled boolean not null default false,
  last_alert_at timestamptz,
  created_at   timestamptz not null default now()
);

create table notifications (
  id          uuid primary key default gen_random_uuid(),
  user_id     uuid not null references users(id) on delete cascade,
  type        text not null,                  -- TIMESHEET_SUBMITTED, TIMESHEET_APPROVED, ...
  title       text not null,
  body        text,
  link        text,
  channels    text[] not null default '{IN_APP}',
  read_at     timestamptz,
  created_at  timestamptz not null default now()
);

create table push_subscriptions (
  id          uuid primary key default gen_random_uuid(),
  user_id     uuid not null references users(id) on delete cascade,
  endpoint    text not null unique,
  p256dh      text not null,
  auth        text not null,
  user_agent  text,
  created_at  timestamptz not null default now()
);

create table audit_log (
  id           bigserial primary key,
  actor_id     uuid references users(id),
  actor_email  text,
  action       text not null,                 -- TIMESHEET_APPROVED, CONTRACT_DOWNLOADED, ...
  entity_type  text not null,
  entity_id    uuid,
  diff         jsonb,
  ip_address   inet,
  user_agent   text,
  created_at   timestamptz not null default now()
);

-- =============================================================================
-- 8. INDICI
-- =============================================================================

create index idx_resources_skills      on resources using gin (skill_ids);
create index idx_resources_fts         on resources using gin (search_vector);
create index idx_resources_facets      on resources (publication_status, operational_status, seniority, work_mode);
create index idx_resources_rate        on resources (daily_rate_min_norm, daily_rate_max_norm);
create index idx_resources_org         on resources (organization_id) where deleted_at is null;

create index idx_requests_resource     on resource_requests (resource_id, status);
create index idx_requests_client       on resource_requests (client_org_id, status, created_at desc);

create index idx_contracts_provider    on contracts (provider_org_id, status);
create index idx_contracts_client      on contracts (client_org_id, status);
create index idx_contracts_active      on contracts (status, start_date, end_date) where status = 'ACTIVE';

-- Badge "da approvare": indice parziale, minuscolo e velocissimo
create index idx_timesheets_to_approve on timesheets (status, week_start) where status = 'SUBMITTED';
create index idx_timesheets_contract   on timesheets (contract_id, iso_year desc, iso_week desc);
create index idx_timesheets_invoice    on timesheets (invoice_id) where invoice_id is not null;
create index idx_timesheet_days_ts     on timesheet_days (timesheet_id, work_date);

create index idx_invoices_client       on invoices (client_org_id, payment_status, due_date);
create index idx_invoices_provider     on invoices (provider_org_id, payment_status, issue_date desc);

-- Job notturno sulle scadenze account
create index idx_orgs_expiry           on organizations (access_expires_at) where status in ('ACTIVE','GRACE');

create index idx_notifications_unread  on notifications (user_id, created_at desc) where read_at is null;
create index idx_audit_entity          on audit_log (entity_type, entity_id, created_at desc);

-- =============================================================================
-- 9. TRIGGER DI INTEGRITÀ
-- =============================================================================

-- 9.1 updated_at automatico
create or replace function touch_updated_at() returns trigger language plpgsql as $$
begin
  new.updated_at := now();
  return new;
end $$;

do $$
declare t text;
begin
  foreach t in array array['organizations','users','resources','resource_requests',
                           'contracts','timesheets','timesheet_days','invoices']
  loop
    execute format(
      'create trigger trg_%1$s_touch before update on %1$s
         for each row execute function touch_updated_at()', t);
  end loop;
end $$;

-- 9.2 Il totale settimanale è SEMPRE la somma dei giorni: il client non lo scrive mai
create or replace function recalc_timesheet_totals() returns trigger language plpgsql as $$
declare
  v_ts   uuid := coalesce(new.timesheet_id, old.timesheet_id);
  v_tot  numeric(6,2);
begin
  select coalesce(sum(quantity), 0) into v_tot
    from timesheet_days where timesheet_id = v_ts;

  update timesheets
     set total_quantity = v_tot,
         amount = case when rate_snapshot is not null then v_tot * rate_snapshot else amount end
   where id = v_ts;

  return null;
end $$;

create trigger trg_recalc_totals
  after insert or update or delete on timesheet_days
  for each row execute function recalc_timesheet_totals();

-- 9.3 Immutabilità del dato approvato: nessun percorso applicativo può aggirarla
create or replace function prevent_approved_timesheet_edit() returns trigger language plpgsql as $$
begin
  if old.status in ('APPROVED','INVOICED','PAID')
     and current_setting('app.admin_override', true) is distinct from 'on' then
    raise exception
      'Time-sheet % in stato % : non modificabile (serve override admin tracciato)', old.id, old.status;
  end if;
  return new;
end $$;

create trigger trg_timesheet_immutable
  before update on timesheets
  for each row execute function prevent_approved_timesheet_edit();

-- Stesso blocco sul dettaglio giornaliero
create or replace function prevent_locked_day_edit() returns trigger language plpgsql as $$
declare v_status timesheet_status;
begin
  select status into v_status from timesheets
   where id = coalesce(new.timesheet_id, old.timesheet_id);

  if v_status <> 'DRAFT'
     and current_setting('app.admin_override', true) is distinct from 'on' then
    raise exception 'La settimana non è in bozza (stato %): giornate non modificabili', v_status;
  end if;
  return coalesce(new, old);
end $$;

create trigger trg_days_locked
  before insert or update or delete on timesheet_days
  for each row execute function prevent_locked_day_edit();

-- 9.4 Congelamento della tariffa all'approvazione
create or replace function snapshot_rate_on_approval() returns trigger language plpgsql as $$
declare v_rate numeric(10,2);
begin
  if new.status = 'APPROVED' and old.status <> 'APPROVED' then
    select agreed_rate into v_rate from contracts where id = new.contract_id;
    new.rate_snapshot := v_rate;
    new.amount        := new.total_quantity * v_rate;
    new.reviewed_at   := now();
  end if;
  return new;
end $$;

create trigger trg_snapshot_rate
  before update on timesheets
  for each row execute function snapshot_rate_on_approval();

-- 9.5 Storico delle transizioni di stato
create or replace function log_timesheet_transition() returns trigger language plpgsql as $$
begin
  if new.status is distinct from old.status then
    insert into timesheet_events (timesheet_id, from_status, to_status, actor_id, reason)
    values (new.id, old.status, new.status,
            coalesce(new.reviewed_by, new.submitted_by),
            new.rejection_reason);
  end if;
  return new;
end $$;

create trigger trg_timesheet_events
  after update on timesheets
  for each row execute function log_timesheet_transition();

-- 9.6 skill_ids denormalizzato sempre allineato a resource_skills
create or replace function sync_resource_skill_ids() returns trigger language plpgsql as $$
declare v_res uuid := coalesce(new.resource_id, old.resource_id);
begin
  update resources
     set skill_ids = coalesce(
           (select array_agg(skill_id order by skill_id) from resource_skills where resource_id = v_res),
           '{}')
   where id = v_res;
  return null;
end $$;

create trigger trg_sync_skill_ids
  after insert or update or delete on resource_skills
  for each row execute function sync_resource_skill_ids();

-- =============================================================================
-- 10. VISTA PUBBLICA ANONIMIZZATA (catalogo per il Richiedente)
--     Espone competenze e tariffe, NON l'identità dell'offerente:
--     è ciò che impedisce la disintermediazione.
-- =============================================================================

create view resources_public as
select r.id, r.title, r.description, r.seniority, r.availability, r.engagement,
       r.available_from, r.rate_min, r.rate_max, r.rate_unit, r.currency, r.rate_negotiable,
       r.daily_rate_min_norm, r.daily_rate_max_norm,
       r.work_mode, r.city, r.province, r.country, r.onsite_radius_km,
       r.languages, r.industries, r.certifications, r.career_start_year,
       r.operational_status, r.skill_ids, r.updated_at
from resources r
where r.publication_status = 'PUBLISHED'
  and r.deleted_at is null
  and exists (
    select 1 from organizations o
     where o.id = r.organization_id
       and o.status in ('ACTIVE','GRACE')    -- account scaduto = risorse de-indicizzate
  );

-- =============================================================================
-- 11. JOB SCHEDULATI (pg_cron)
-- =============================================================================

-- 03:00 — degrado degli account scaduti (scadenza impostata a mano dall'admin)
-- select cron.schedule('account-expiry', '0 3 * * *', $$
--   update organizations
--      set status = 'GRACE', grace_ends_at = access_expires_at + 15
--    where status = 'ACTIVE' and access_expires_at < current_date;
--   update organizations
--      set status = 'EXPIRED'
--    where status = 'GRACE' and grace_ends_at < current_date;
-- $$);

-- Venerdì 17:00 — promemoria settimane non compilate
-- Lunedì  09:00 — sollecito settimane ancora in DRAFT
-- 03:30   — fatture scadute: payment_status = 'SCADUTA' dove due_date < current_date
--           and payment_status in ('EMESSA','INVIATA')

-- =============================================================================
-- 12. ROW LEVEL SECURITY (esempi chiave — la versione completa vive nelle migration)
-- =============================================================================

alter table organizations   enable row level security;
alter table resources       enable row level security;
alter table contracts       enable row level security;
alter table timesheets      enable row level security;
alter table timesheet_days  enable row level security;
alter table invoices        enable row level security;

-- Helper: l'organizzazione dell'utente autenticato
create or replace function auth_org_id() returns uuid language sql stable as $$
  select organization_id from users where id = auth.uid();
$$;

create or replace function is_admin() returns boolean language sql stable as $$
  select exists (select 1 from users where id = auth.uid() and platform_role = 'ADMIN');
$$;

-- L'offerente vede e modifica solo le proprie risorse
create policy resources_own on resources
  for all using (is_admin() or organization_id = auth_org_id());

-- Contratti: visibili alle due parti
create policy contracts_parties on contracts
  for select using (
    is_admin() or provider_org_id = auth_org_id() or client_org_id = auth_org_id()
  );

-- Time-sheet: lettura a entrambe le parti
create policy timesheets_read on timesheets
  for select using (
    is_admin() or exists (
      select 1 from contracts c where c.id = timesheets.contract_id
        and (c.provider_org_id = auth_org_id() or c.client_org_id = auth_org_id())
    )
  );

-- Compilazione: solo il fornitore, solo in bozza
create policy timesheets_fill on timesheets
  for update using (
    status = 'DRAFT' and exists (
      select 1 from contracts c where c.id = timesheets.contract_id
        and c.provider_org_id = auth_org_id()
    )
  );

-- Approvazione: solo il cliente, solo su settimane inviate
create policy timesheets_approve on timesheets
  for update using (
    status = 'SUBMITTED' and exists (
      select 1 from contracts c where c.id = timesheets.contract_id
        and c.client_org_id = auth_org_id()
    )
  );

-- Lo stato del pagamento lo cambia solo l'Admin
create policy invoices_read on invoices
  for select using (
    is_admin() or provider_org_id = auth_org_id() or client_org_id = auth_org_id()
  );
create policy invoices_admin_write on invoices
  for update using (is_admin());
