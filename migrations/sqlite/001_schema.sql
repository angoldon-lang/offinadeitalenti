-- Schema SQLite: SOLO per sviluppo e test locali (php -S).
-- La produzione su Aruba usa migrations/mysql/001_schema.sql.
-- Stessi vincoli semantici: UNIQUE settimanale e trigger di immutabilita'.

CREATE TABLE runtime_flags (id INTEGER PRIMARY KEY, admin_override INTEGER NOT NULL DEFAULT 0);
-- ;; --
INSERT INTO runtime_flags (id, admin_override) VALUES (1, 0);
-- ;; --
CREATE TABLE organizations (
  id TEXT PRIMARY KEY, type TEXT NOT NULL, legal_name TEXT NOT NULL, vat_number TEXT,
  sector TEXT, size_range TEXT, website TEXT, phone TEXT, address TEXT,
  status TEXT NOT NULL DEFAULT 'PENDING_APPROVAL',
  access_expires_at TEXT, grace_ends_at TEXT, external_contract_ref TEXT, admin_notes TEXT,
  approved_by TEXT, approved_at TEXT, created_at TEXT NOT NULL, updated_at TEXT NOT NULL,
  CHECK (type IN ('OFFERENTE','RICHIEDENTE')),
  CHECK (status IN ('PENDING_APPROVAL','ACTIVE','GRACE','EXPIRED','SUSPENDED'))
);
-- ;; --
CREATE UNIQUE INDEX uq_org_vat ON organizations(vat_number);
-- ;; --
CREATE INDEX idx_org_expiry ON organizations(access_expires_at, status);
-- ;; --
CREATE TABLE account_extensions (
  id TEXT PRIMARY KEY, organization_id TEXT NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
  previous_expiry TEXT, new_expiry TEXT NOT NULL, reason TEXT, external_ref TEXT,
  created_by TEXT, created_at TEXT NOT NULL
);
-- ;; --
CREATE TABLE users (
  id TEXT PRIMARY KEY, organization_id TEXT REFERENCES organizations(id) ON DELETE CASCADE,
  email TEXT NOT NULL, password_hash TEXT NOT NULL, full_name TEXT NOT NULL, phone TEXT,
  platform_role TEXT NOT NULL, org_role TEXT NOT NULL DEFAULT 'MEMBER', resource_id TEXT,
  is_active INTEGER NOT NULL DEFAULT 1, last_login_at TEXT,
  created_at TEXT NOT NULL, updated_at TEXT NOT NULL,
  CHECK (platform_role IN ('OFFERENTE','RICHIEDENTE','RESOURCE_USER','ADMIN'))
);
-- ;; --
CREATE UNIQUE INDEX uq_users_email ON users(email);
-- ;; --
CREATE TABLE skills (
  id TEXT PRIMARY KEY, slug TEXT NOT NULL, name TEXT NOT NULL, category TEXT NOT NULL,
  is_active INTEGER NOT NULL DEFAULT 1, CHECK (category IN ('HARD','SOFT'))
);
-- ;; --
CREATE UNIQUE INDEX uq_skill_slug ON skills(slug);
-- ;; --
CREATE TABLE resources (
  id TEXT PRIMARY KEY, organization_id TEXT NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
  title TEXT NOT NULL, description TEXT, seniority TEXT NOT NULL, availability TEXT NOT NULL,
  engagement TEXT NOT NULL, available_from TEXT,
  rate_min NUMERIC NOT NULL, rate_max NUMERIC NOT NULL, rate_unit TEXT NOT NULL,
  rate_negotiable INTEGER NOT NULL DEFAULT 0,
  daily_rate_min NUMERIC NOT NULL, daily_rate_max NUMERIC NOT NULL,
  work_mode TEXT NOT NULL, city TEXT, province TEXT, languages TEXT,
  operational_status TEXT NOT NULL DEFAULT 'ATTIVA',
  publication_status TEXT NOT NULL DEFAULT 'DRAFT',
  rejection_reason TEXT, published_at TEXT, reviewed_by TEXT,
  created_at TEXT NOT NULL, updated_at TEXT NOT NULL,
  CHECK (rate_max >= rate_min AND rate_min >= 0),
  CHECK (work_mode IN ('ONSITE','REMOTO','IBRIDO')),
  CHECK (operational_status IN ('ATTIVA','OCCUPATA'))
);
-- ;; --
CREATE INDEX idx_res_org ON resources(organization_id, publication_status);
-- ;; --
CREATE INDEX idx_res_search ON resources(publication_status, operational_status, seniority, work_mode);
-- ;; --
CREATE TABLE resource_skills (
  resource_id TEXT NOT NULL REFERENCES resources(id) ON DELETE CASCADE,
  skill_id TEXT NOT NULL REFERENCES skills(id) ON DELETE CASCADE,
  level INTEGER, years INTEGER, PRIMARY KEY (resource_id, skill_id)
);
-- ;; --
CREATE TABLE resource_requests (
  id TEXT PRIMARY KEY,
  resource_id TEXT NOT NULL REFERENCES resources(id) ON DELETE CASCADE,
  client_org_id TEXT NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
  created_by TEXT, status TEXT NOT NULL DEFAULT 'REQUESTED', project_brief TEXT NOT NULL,
  estimated_duration TEXT, desired_start_date TEXT, budget_hint NUMERIC, budget_unit TEXT,
  responded_at TEXT, decline_reason TEXT, expires_at TEXT,
  created_at TEXT NOT NULL, updated_at TEXT NOT NULL
);
-- ;; --
CREATE TABLE contracts (
  id TEXT PRIMARY KEY, code TEXT NOT NULL,
  provider_org_id TEXT NOT NULL REFERENCES organizations(id),
  client_org_id TEXT NOT NULL REFERENCES organizations(id),
  resource_id TEXT REFERENCES resources(id) ON DELETE SET NULL,
  request_id TEXT, status TEXT NOT NULL DEFAULT 'DRAFT',
  start_date TEXT NOT NULL, end_date TEXT NOT NULL,
  agreed_rate NUMERIC NOT NULL, rate_unit TEXT NOT NULL,
  timesheet_required INTEGER NOT NULL DEFAULT 1, auto_approve_after_days INTEGER,
  visibility TEXT NOT NULL DEFAULT 'CONDIVISO', notes TEXT,
  created_at TEXT NOT NULL, updated_at TEXT NOT NULL,
  CHECK (provider_org_id <> client_org_id),
  CHECK (end_date >= start_date),
  CHECK (agreed_rate > 0)
);
-- ;; --
CREATE UNIQUE INDEX uq_contract_code ON contracts(code);
-- ;; --
CREATE TABLE contract_documents (
  id TEXT PRIMARY KEY, contract_id TEXT NOT NULL REFERENCES contracts(id) ON DELETE CASCADE,
  doc_type TEXT NOT NULL DEFAULT 'ORDINE', version INTEGER NOT NULL DEFAULT 1,
  file_name TEXT NOT NULL, storage_key TEXT NOT NULL, file_size INTEGER, file_hash TEXT,
  visibility TEXT NOT NULL DEFAULT 'CONDIVISO', uploaded_by TEXT, signed_at TEXT,
  created_at TEXT NOT NULL
);
-- ;; --
CREATE UNIQUE INDEX uq_doc_version ON contract_documents(contract_id, doc_type, version);
-- ;; --
CREATE TABLE invoices (
  id TEXT PRIMARY KEY, number TEXT,
  provider_org_id TEXT NOT NULL REFERENCES organizations(id),
  client_org_id TEXT NOT NULL REFERENCES organizations(id),
  contract_id TEXT REFERENCES contracts(id) ON DELETE SET NULL,
  period_start TEXT NOT NULL, period_end TEXT NOT NULL, issue_date TEXT, due_date TEXT,
  amount_net NUMERIC NOT NULL DEFAULT 0, vat_rate NUMERIC NOT NULL DEFAULT 22.00,
  amount_total NUMERIC NOT NULL DEFAULT 0,
  payment_status TEXT NOT NULL DEFAULT 'DA_EMETTERE', paid_at TEXT, paid_amount NUMERIC,
  file_name TEXT, storage_key TEXT, uploaded_by TEXT, notes TEXT,
  created_at TEXT NOT NULL, updated_at TEXT NOT NULL,
  CHECK (period_end >= period_start)
);
-- ;; --
CREATE TABLE timesheets (
  id TEXT PRIMARY KEY, contract_id TEXT NOT NULL REFERENCES contracts(id) ON DELETE CASCADE,
  iso_year INTEGER NOT NULL, iso_week INTEGER NOT NULL,
  week_start TEXT NOT NULL, week_end TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'DRAFT', unit TEXT NOT NULL,
  total_quantity NUMERIC NOT NULL DEFAULT 0, rate_snapshot NUMERIC, amount NUMERIC,
  submitted_by TEXT, submitted_at TEXT, reviewed_by TEXT, reviewed_at TEXT,
  rejection_reason TEXT, invoice_id TEXT REFERENCES invoices(id) ON DELETE SET NULL, notes TEXT,
  created_at TEXT NOT NULL, updated_at TEXT NOT NULL,
  CHECK (status IN ('DRAFT','SUBMITTED','APPROVED','REJECTED','INVOICED','PAID'))
);
-- ;; --
CREATE UNIQUE INDEX uq_timesheet_week ON timesheets(contract_id, iso_year, iso_week);
-- ;; --
CREATE INDEX idx_ts_status ON timesheets(status, week_start);
-- ;; --
CREATE TABLE timesheet_days (
  id TEXT PRIMARY KEY, timesheet_id TEXT NOT NULL REFERENCES timesheets(id) ON DELETE CASCADE,
  work_date TEXT NOT NULL, day_type TEXT NOT NULL DEFAULT 'NON_LAVORATO',
  quantity NUMERIC NOT NULL DEFAULT 0, note TEXT, updated_at TEXT NOT NULL,
  CHECK (quantity >= 0 AND quantity <= 24)
);
-- ;; --
CREATE UNIQUE INDEX uq_ts_day ON timesheet_days(timesheet_id, work_date);
-- ;; --
CREATE TABLE timesheet_events (
  id TEXT PRIMARY KEY, timesheet_id TEXT NOT NULL REFERENCES timesheets(id) ON DELETE CASCADE,
  from_status TEXT, to_status TEXT NOT NULL, actor_id TEXT, actor_name TEXT, reason TEXT,
  created_at TEXT NOT NULL
);
-- ;; --
CREATE TABLE notifications (
  id TEXT PRIMARY KEY, user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  type TEXT NOT NULL, title TEXT NOT NULL, body TEXT, link TEXT, read_at TEXT,
  created_at TEXT NOT NULL
);
-- ;; --
CREATE TABLE audit_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT, actor_id TEXT, actor_email TEXT, action TEXT NOT NULL,
  entity_type TEXT NOT NULL, entity_id TEXT, diff TEXT, ip_address TEXT, user_agent TEXT,
  created_at TEXT NOT NULL
);
-- ;; --
CREATE TRIGGER trg_ts_immutable BEFORE UPDATE ON timesheets
FOR EACH ROW WHEN OLD.status IN ('APPROVED','INVOICED','PAID')
  AND (SELECT admin_override FROM runtime_flags WHERE id = 1) = 0
BEGIN
  SELECT RAISE(ABORT, 'Time-sheet approvato: modifica non consentita senza override amministrativo.');
END;
-- ;; --
CREATE TRIGGER trg_tsd_locked_ins BEFORE INSERT ON timesheet_days
FOR EACH ROW WHEN (SELECT status FROM timesheets WHERE id = NEW.timesheet_id) <> 'DRAFT'
  AND (SELECT admin_override FROM runtime_flags WHERE id = 1) = 0
BEGIN
  SELECT RAISE(ABORT, 'La settimana non e'' in bozza: giornate non modificabili.');
END;
-- ;; --
CREATE TRIGGER trg_tsd_locked_upd BEFORE UPDATE ON timesheet_days
FOR EACH ROW WHEN (SELECT status FROM timesheets WHERE id = NEW.timesheet_id) <> 'DRAFT'
  AND (SELECT admin_override FROM runtime_flags WHERE id = 1) = 0
BEGIN
  SELECT RAISE(ABORT, 'La settimana non e'' in bozza: giornate non modificabili.');
END;
-- ;; --
CREATE TRIGGER trg_tsd_locked_del BEFORE DELETE ON timesheet_days
FOR EACH ROW WHEN (SELECT status FROM timesheets WHERE id = OLD.timesheet_id) <> 'DRAFT'
  AND (SELECT admin_override FROM runtime_flags WHERE id = 1) = 0
BEGIN
  SELECT RAISE(ABORT, 'La settimana non e'' in bozza: giornate non cancellabili.');
END;
