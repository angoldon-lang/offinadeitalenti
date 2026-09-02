-- Officina dei Talenti — Passo unico: tabelle + competenze
-- Generato il 02/09/2026 23:31
-- Incolla in phpMyAdmin (scheda SQL) e premi Esegui.

-- Contiene lo stesso contenuto di 01-schema.sql e 03-skills.sql.
-- Usa QUESTO file, oppure quei due separati: non entrambi.

-- =============================================================================
-- Officina dei Talenti - schema MySQL 8 / MariaDB 10.4+
-- Target: Aruba Hosting Basic Linux (database MySQL condiviso)
--
-- Gli statement sono separati da una riga marcatore dedicata, perche' i
-- trigger contengono punti e virgola al loro interno e non si possono
-- dividere sul ";".
-- Su MySQL 5.7 i CHECK vengono ignorati dal motore: i vincoli che contano
-- davvero (UNIQUE settimanale e immutabilita' del time-sheet approvato) sono
-- affidati a indici e trigger, supportati da tutte le versioni.
-- =============================================================================

CREATE TABLE runtime_flags (
  id             TINYINT      NOT NULL PRIMARY KEY,
  admin_override TINYINT      NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO runtime_flags (id, admin_override) VALUES (1, 0);

CREATE TABLE organizations (
  id                    CHAR(36)     NOT NULL PRIMARY KEY,
  type                  VARCHAR(16)  NOT NULL,
  legal_name            VARCHAR(200) NOT NULL,
  vat_number            VARCHAR(20)  NULL,
  sector                VARCHAR(100) NULL,
  size_range            VARCHAR(50)  NULL,
  website               VARCHAR(200) NULL,
  phone                 VARCHAR(40)  NULL,
  address               VARCHAR(255) NULL,
  status                VARCHAR(20)  NOT NULL DEFAULT 'PENDING_APPROVAL',
  -- Durata del profilo: impostata a mano dall'admin, nessun pagamento automatico
  access_expires_at     DATE         NULL,
  grace_ends_at         DATE         NULL,
  external_contract_ref VARCHAR(120) NULL,
  admin_notes           TEXT         NULL,
  approved_by           CHAR(36)     NULL,
  approved_at           DATETIME     NULL,
  created_at            DATETIME     NOT NULL,
  updated_at            DATETIME     NOT NULL,
  UNIQUE KEY uq_org_vat (vat_number),
  KEY idx_org_expiry (access_expires_at, status),
  CONSTRAINT chk_org_type   CHECK (type IN ('OFFERENTE','RICHIEDENTE')),
  CONSTRAINT chk_org_status CHECK (status IN ('PENDING_APPROVAL','ACTIVE','GRACE','EXPIRED','SUSPENDED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE account_extensions (
  id              CHAR(36)     NOT NULL PRIMARY KEY,
  organization_id CHAR(36)     NOT NULL,
  previous_expiry DATE         NULL,
  new_expiry      DATE         NOT NULL,
  reason          VARCHAR(255) NULL,
  external_ref    VARCHAR(120) NULL,
  created_by      CHAR(36)     NULL,
  created_at      DATETIME     NOT NULL,
  KEY idx_ext_org (organization_id, created_at),
  CONSTRAINT fk_ext_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
  id              CHAR(36)     NOT NULL PRIMARY KEY,
  organization_id CHAR(36)     NULL,
  email           VARCHAR(190) NOT NULL,
  password_hash   VARCHAR(255) NOT NULL,
  full_name       VARCHAR(150) NOT NULL,
  phone           VARCHAR(40)  NULL,
  platform_role   VARCHAR(20)  NOT NULL,
  org_role        VARCHAR(10)  NOT NULL DEFAULT 'MEMBER',
  resource_id     CHAR(36)     NULL,
  is_active       TINYINT      NOT NULL DEFAULT 1,
  last_login_at   DATETIME     NULL,
  created_at      DATETIME     NOT NULL,
  updated_at      DATETIME     NOT NULL,
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_org (organization_id),
  CONSTRAINT fk_users_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT chk_users_role CHECK (platform_role IN ('OFFERENTE','RICHIEDENTE','RESOURCE_USER','ADMIN'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE skills (
  id         CHAR(36)     NOT NULL PRIMARY KEY,
  slug       VARCHAR(80)  NOT NULL,
  name       VARCHAR(80)  NOT NULL,
  category   VARCHAR(10)  NOT NULL,
  is_active  TINYINT      NOT NULL DEFAULT 1,
  UNIQUE KEY uq_skill_slug (slug),
  KEY idx_skill_cat (category, name),
  CONSTRAINT chk_skill_cat CHECK (category IN ('HARD','SOFT'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE resources (
  id                 CHAR(36)      NOT NULL PRIMARY KEY,
  organization_id    CHAR(36)      NOT NULL,
  title              VARCHAR(150)  NOT NULL,
  description        TEXT          NULL,
  seniority          VARCHAR(20)   NOT NULL,
  availability       VARCHAR(20)   NOT NULL,
  engagement         VARCHAR(20)   NOT NULL,
  available_from     DATE          NULL,
  rate_min           DECIMAL(10,2) NOT NULL,
  rate_max           DECIMAL(10,2) NOT NULL,
  rate_unit          VARCHAR(10)   NOT NULL,
  rate_negotiable    TINYINT       NOT NULL DEFAULT 0,
  -- Normalizzazione oraria -> giornaliera (1 gg = 8 h): rende confrontabile lo slider budget
  daily_rate_min     DECIMAL(10,2) NOT NULL,
  daily_rate_max     DECIMAL(10,2) NOT NULL,
  work_mode          VARCHAR(10)   NOT NULL,
  city               VARCHAR(100)  NULL,
  province           VARCHAR(4)    NULL,
  languages          VARCHAR(255)  NULL,
  -- Due assi INDIPENDENTI: la moderazione non riparte quando la risorsa si libera
  operational_status VARCHAR(10)   NOT NULL DEFAULT 'ATTIVA',
  publication_status VARCHAR(12)   NOT NULL DEFAULT 'DRAFT',
  rejection_reason   TEXT          NULL,
  published_at       DATETIME      NULL,
  reviewed_by        CHAR(36)      NULL,
  created_at         DATETIME      NOT NULL,
  updated_at         DATETIME      NOT NULL,
  KEY idx_res_org (organization_id, publication_status),
  KEY idx_res_search (publication_status, operational_status, seniority, work_mode),
  KEY idx_res_rate (daily_rate_min, daily_rate_max),
  CONSTRAINT fk_res_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT chk_res_rate CHECK (rate_max >= rate_min AND rate_min >= 0),
  CONSTRAINT chk_res_mode CHECK (work_mode IN ('ONSITE','REMOTO','IBRIDO')),
  CONSTRAINT chk_res_op   CHECK (operational_status IN ('ATTIVA','OCCUPATA'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE resource_skills (
  resource_id CHAR(36) NOT NULL,
  skill_id    CHAR(36) NOT NULL,
  level       TINYINT  NULL,
  years       TINYINT  NULL,
  PRIMARY KEY (resource_id, skill_id),
  KEY idx_rs_skill (skill_id),
  CONSTRAINT fk_rs_res   FOREIGN KEY (resource_id) REFERENCES resources(id) ON DELETE CASCADE,
  CONSTRAINT fk_rs_skill FOREIGN KEY (skill_id)    REFERENCES skills(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE resource_requests (
  id                 CHAR(36)      NOT NULL PRIMARY KEY,
  resource_id        CHAR(36)      NOT NULL,
  client_org_id      CHAR(36)      NOT NULL,
  created_by         CHAR(36)      NULL,
  status             VARCHAR(20)   NOT NULL DEFAULT 'REQUESTED',
  project_brief      TEXT          NOT NULL,
  estimated_duration VARCHAR(80)   NULL,
  desired_start_date DATE          NULL,
  budget_hint        DECIMAL(10,2) NULL,
  budget_unit        VARCHAR(10)   NULL,
  responded_at       DATETIME      NULL,
  decline_reason     TEXT          NULL,
  expires_at         DATE          NULL,
  created_at         DATETIME      NOT NULL,
  updated_at         DATETIME      NOT NULL,
  KEY idx_req_res (resource_id, status),
  KEY idx_req_client (client_org_id, status, created_at),
  CONSTRAINT fk_req_res    FOREIGN KEY (resource_id)   REFERENCES resources(id)     ON DELETE CASCADE,
  CONSTRAINT fk_req_client FOREIGN KEY (client_org_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE contracts (
  id                      CHAR(36)      NOT NULL PRIMARY KEY,
  code                    VARCHAR(30)   NOT NULL,
  provider_org_id         CHAR(36)      NOT NULL,
  client_org_id           CHAR(36)      NOT NULL,
  resource_id             CHAR(36)      NULL,
  request_id              CHAR(36)      NULL,
  status                  VARCHAR(12)   NOT NULL DEFAULT 'DRAFT',
  start_date              DATE          NOT NULL,
  end_date                DATE          NOT NULL,
  -- Tariffa concordata: sorgente di verita' per TUTTI i calcoli di rendicontazione
  agreed_rate             DECIMAL(10,2) NOT NULL,
  rate_unit               VARCHAR(10)   NOT NULL,
  timesheet_required      TINYINT       NOT NULL DEFAULT 1,
  auto_approve_after_days SMALLINT      NULL,
  visibility              VARCHAR(24)   NOT NULL DEFAULT 'CONDIVISO',
  notes                   TEXT          NULL,
  created_at              DATETIME      NOT NULL,
  updated_at              DATETIME      NOT NULL,
  UNIQUE KEY uq_contract_code (code),
  KEY idx_con_provider (provider_org_id, status),
  KEY idx_con_client (client_org_id, status),
  CONSTRAINT fk_con_provider FOREIGN KEY (provider_org_id) REFERENCES organizations(id),
  CONSTRAINT fk_con_client   FOREIGN KEY (client_org_id)   REFERENCES organizations(id),
  CONSTRAINT fk_con_res      FOREIGN KEY (resource_id)     REFERENCES resources(id) ON DELETE SET NULL,
  CONSTRAINT chk_con_parties CHECK (provider_org_id <> client_org_id),
  CONSTRAINT chk_con_dates   CHECK (end_date >= start_date),
  CONSTRAINT chk_con_rate    CHECK (agreed_rate > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE contract_documents (
  id          CHAR(36)     NOT NULL PRIMARY KEY,
  contract_id CHAR(36)     NOT NULL,
  doc_type    VARCHAR(12)  NOT NULL DEFAULT 'ORDINE',
  version     SMALLINT     NOT NULL DEFAULT 1,
  file_name   VARCHAR(150) NOT NULL,
  storage_key VARCHAR(255) NOT NULL,
  file_size   INT          NULL,
  file_hash   CHAR(64)     NULL,
  visibility  VARCHAR(24)  NOT NULL DEFAULT 'CONDIVISO',
  uploaded_by CHAR(36)     NULL,
  signed_at   DATE         NULL,
  created_at  DATETIME     NOT NULL,
  UNIQUE KEY uq_doc_version (contract_id, doc_type, version),
  CONSTRAINT fk_doc_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE invoices (
  id              CHAR(36)      NOT NULL PRIMARY KEY,
  number          VARCHAR(40)   NULL,
  provider_org_id CHAR(36)      NOT NULL,
  client_org_id   CHAR(36)      NOT NULL,
  contract_id     CHAR(36)      NULL,
  period_start    DATE          NOT NULL,
  period_end      DATE          NOT NULL,
  issue_date      DATE          NULL,
  due_date        DATE          NULL,
  amount_net      DECIMAL(12,2) NOT NULL DEFAULT 0,
  vat_rate        DECIMAL(5,2)  NOT NULL DEFAULT 22.00,
  amount_total    DECIMAL(12,2) NOT NULL DEFAULT 0,
  payment_status  VARCHAR(16)   NOT NULL DEFAULT 'DA_EMETTERE',
  paid_at         DATE          NULL,
  paid_amount     DECIMAL(12,2) NULL,
  file_name       VARCHAR(150)  NULL,
  storage_key     VARCHAR(255)  NULL,
  uploaded_by     CHAR(36)      NULL,
  notes           TEXT          NULL,
  created_at      DATETIME      NOT NULL,
  updated_at      DATETIME      NOT NULL,
  KEY idx_inv_client (client_org_id, payment_status, due_date),
  KEY idx_inv_provider (provider_org_id, payment_status, issue_date),
  CONSTRAINT fk_inv_provider FOREIGN KEY (provider_org_id) REFERENCES organizations(id),
  CONSTRAINT fk_inv_client   FOREIGN KEY (client_org_id)   REFERENCES organizations(id),
  CONSTRAINT fk_inv_contract FOREIGN KEY (contract_id)     REFERENCES contracts(id) ON DELETE SET NULL,
  CONSTRAINT chk_inv_period  CHECK (period_end >= period_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE timesheets (
  id               CHAR(36)      NOT NULL PRIMARY KEY,
  contract_id      CHAR(36)      NOT NULL,
  iso_year         SMALLINT      NOT NULL,
  iso_week         TINYINT       NOT NULL,
  week_start       DATE          NOT NULL,
  week_end         DATE          NOT NULL,
  status           VARCHAR(12)   NOT NULL DEFAULT 'DRAFT',
  unit             VARCHAR(10)   NOT NULL,
  total_quantity   DECIMAL(6,2)  NOT NULL DEFAULT 0,
  -- Tariffa congelata all'approvazione: una rinegoziazione non riscrive il passato
  rate_snapshot    DECIMAL(10,2) NULL,
  amount           DECIMAL(12,2) NULL,
  submitted_by     CHAR(36)      NULL,
  submitted_at     DATETIME      NULL,
  reviewed_by      CHAR(36)      NULL,
  reviewed_at      DATETIME      NULL,
  rejection_reason TEXT          NULL,
  invoice_id       CHAR(36)      NULL,
  notes            TEXT          NULL,
  created_at       DATETIME      NOT NULL,
  updated_at       DATETIME      NOT NULL,
  -- Rende FISICAMENTE impossibile il doppio rendiconto della stessa settimana
  UNIQUE KEY uq_timesheet_week (contract_id, iso_year, iso_week),
  KEY idx_ts_status (status, week_start),
  KEY idx_ts_contract (contract_id, iso_year, iso_week),
  KEY idx_ts_invoice (invoice_id),
  CONSTRAINT fk_ts_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
  CONSTRAINT fk_ts_invoice  FOREIGN KEY (invoice_id)  REFERENCES invoices(id)  ON DELETE SET NULL,
  CONSTRAINT chk_ts_status  CHECK (status IN ('DRAFT','SUBMITTED','APPROVED','REJECTED','INVOICED','PAID'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE timesheet_days (
  id           CHAR(36)     NOT NULL PRIMARY KEY,
  timesheet_id CHAR(36)     NOT NULL,
  work_date    DATE         NOT NULL,
  day_type     VARCHAR(16)  NOT NULL DEFAULT 'NON_LAVORATO',
  quantity     DECIMAL(4,2) NOT NULL DEFAULT 0,
  note         VARCHAR(255) NULL,
  updated_at   DATETIME     NOT NULL,
  UNIQUE KEY uq_ts_day (timesheet_id, work_date),
  CONSTRAINT fk_tsd_ts FOREIGN KEY (timesheet_id) REFERENCES timesheets(id) ON DELETE CASCADE,
  CONSTRAINT chk_tsd_qty CHECK (quantity >= 0 AND quantity <= 24)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE timesheet_events (
  id           CHAR(36)    NOT NULL PRIMARY KEY,
  timesheet_id CHAR(36)    NOT NULL,
  from_status  VARCHAR(12) NULL,
  to_status    VARCHAR(12) NOT NULL,
  actor_id     CHAR(36)    NULL,
  actor_name   VARCHAR(150) NULL,
  reason       TEXT        NULL,
  created_at   DATETIME    NOT NULL,
  KEY idx_tse_ts (timesheet_id, created_at),
  CONSTRAINT fk_tse_ts FOREIGN KEY (timesheet_id) REFERENCES timesheets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notifications (
  id         CHAR(36)     NOT NULL PRIMARY KEY,
  user_id    CHAR(36)     NOT NULL,
  type       VARCHAR(40)  NOT NULL,
  title      VARCHAR(150) NOT NULL,
  body       VARCHAR(255) NULL,
  link       VARCHAR(255) NULL,
  read_at    DATETIME     NULL,
  created_at DATETIME     NOT NULL,
  KEY idx_notif_user (user_id, read_at, created_at),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE audit_log (
  id          BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
  actor_id    CHAR(36)     NULL,
  actor_email VARCHAR(190) NULL,
  action      VARCHAR(60)  NOT NULL,
  entity_type VARCHAR(40)  NOT NULL,
  entity_id   CHAR(36)     NULL,
  diff        TEXT         NULL,
  ip_address  VARCHAR(45)  NULL,
  user_agent  VARCHAR(255) NULL,
  created_at  DATETIME     NOT NULL,
  KEY idx_audit_entity (entity_type, entity_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO skills (id, slug, name, category, is_active) VALUES
('77c35d6b-7dac-4390-970a-9942435e18c9', 'php', 'PHP', 'HARD', 1),
('fb260a60-f2cf-4968-893d-c56513e5024b', 'javascript', 'JavaScript', 'HARD', 1),
('41d2cb6a-1bd0-40b2-b80c-72ed6cdaa577', 'typescript', 'TypeScript', 'HARD', 1),
('eb9b7bf0-93d6-4c3c-a6b4-bd266fa46323', 'react', 'React', 'HARD', 1),
('b1b7cb27-17dc-4d73-bd54-7e45b7b7a8a5', 'vue', 'Vue', 'HARD', 1),
('ea21ba77-311b-4187-aece-7bdf9345d532', 'angular', 'Angular', 'HARD', 1),
('255aa61e-64c3-4c5b-b479-9cab4a35632d', 'node-js', 'Node.js', 'HARD', 1),
('d325980b-c7a6-405a-a1d2-0efbf16330a3', 'python', 'Python', 'HARD', 1),
('ee445b0b-3b4d-4ed3-a4e7-d81debc0b24f', 'java', 'Java', 'HARD', 1),
('888d0e14-c5fb-47c6-b36a-8e7fa4537468', 'c-net', 'C#/.NET', 'HARD', 1),
('483e731a-414b-4717-b5f8-9b75b05b3f57', 'go', 'Go', 'HARD', 1),
('33c52945-4b54-4c42-8fb4-f41d4e9f51b2', 'sql', 'SQL', 'HARD', 1),
('3f6a37b3-2b54-45e7-b0a5-f4cd936e1cc8', 'postgresql', 'PostgreSQL', 'HARD', 1),
('1c83edd5-78fe-4460-82d0-5f34353d2a67', 'mysql', 'MySQL', 'HARD', 1),
('41223036-f238-47c0-b964-63b2e9230714', 'mongodb', 'MongoDB', 'HARD', 1),
('2cdcdf16-64b5-4331-8842-6983de08076b', 'docker', 'Docker', 'HARD', 1),
('147e01ed-94d0-4523-8249-f8927f0d05a4', 'kubernetes', 'Kubernetes', 'HARD', 1),
('51761b7d-dbe0-4bb9-8647-5eae61aa7423', 'aws', 'AWS', 'HARD', 1),
('f53f3f97-a864-43c7-aef0-66949d24a3e7', 'azure', 'Azure', 'HARD', 1),
('02a1ea33-5ac0-4526-a5b3-40b16e200d63', 'terraform', 'Terraform', 'HARD', 1),
('6e7ec814-9b7d-4de6-b5fe-4182b83d59d1', 'ci-cd', 'CI/CD', 'HARD', 1),
('d3c51d1a-8b57-478e-8fb9-517dad9b891f', 'linux', 'Linux', 'HARD', 1),
('063bb61e-f26b-4f69-9926-e4edf7f6c14e', 'cybersecurity', 'Cybersecurity', 'HARD', 1),
('0ab6cb90-b1c2-4a2e-b462-b4531ddd1b12', 'power-bi', 'Power BI', 'HARD', 1),
('37a0fb43-1313-4210-907c-f6aec31dfaed', 'sap', 'SAP', 'HARD', 1),
('67066718-b376-46bd-997f-c9b68fd940cc', 'salesforce', 'Salesforce', 'HARD', 1),
('90ce0bbd-9801-489b-ba44-7cd50a52f3d2', 'android', 'Android', 'HARD', 1),
('dd462cf1-ff72-4df7-a3a8-b29048a5ee96', 'ios', 'iOS', 'HARD', 1),
('c717769c-64d0-4e2d-87e6-6acbe6110287', 'flutter', 'Flutter', 'HARD', 1),
('e9d0e1bf-7250-4c84-be0d-db5220b6b4b6', 'ux-ui-design', 'UX/UI Design', 'HARD', 1),
('ff138561-164f-4fc9-8d3e-3a952d43590e', 'project-management', 'Project Management', 'HARD', 1),
('4c6c4ccc-e0ad-4ce3-9efa-a68ec28d955d', 'data-engineering', 'Data Engineering', 'HARD', 1),
('21d124bd-b6b6-4ebf-970c-43a9152d7beb', 'machine-learning', 'Machine Learning', 'HARD', 1),
('52e506f8-b751-4930-abc6-7b54db46816f', 'comunicazione', 'Comunicazione', 'SOFT', 1),
('9a5805cd-db5c-4f5e-9fca-d18ba93d4dfd', 'problem-solving', 'Problem solving', 'SOFT', 1),
('20f975dd-6f15-439a-9242-a8ffc792bca0', 'lavoro-in-team', 'Lavoro in team', 'SOFT', 1),
('79cdd542-999f-426f-9fe3-aa61b7fa1032', 'autonomia', 'Autonomia', 'SOFT', 1),
('0c15c128-39af-42b4-ae57-63a00dc625b4', 'leadership', 'Leadership', 'SOFT', 1),
('7c687291-924b-4316-8252-4897cb05cfff', 'gestione-del-cliente', 'Gestione del cliente', 'SOFT', 1),
('dbf59f32-1cd9-44b0-b066-3a6acd57edfa', 'precisione', 'Precisione', 'SOFT', 1),
('7a0b13ab-6b3a-42f5-a0f8-ab91ea7dc0f2', 'adattabilita-', 'Adattabilita''', 'SOFT', 1),
('02cae71d-d0bd-4ddd-a377-e616d8d89586', 'inglese-fluente', 'Inglese fluente', 'SOFT', 1);
