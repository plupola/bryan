-- Workspace App — MySQL 8.x / InnoDB / utf8mb4 
-- Target runtime: PHP 8.4.x + XAMPP (MySQL 8)
-- All timestamps UTC (use NOW() only if @@time_zone = '+00:00').
-- Major changes from your PostgreSQL draft:
-- 1) UUID → BINARY(16) with UUID_TO_BIN(..., 1) for time‑ordered storage; expose BIN_TO_UUID in views.
-- 2) LTREE → materialized path (folders.path VARCHAR(1024), folders.depth INT) with LIKE prefix queries.
-- 3) INET → VARBINARY(16) with INET6_ATON/INET6_NTOA.
-- 4) JSONB/TSVECTOR → JSON + FULLTEXT; optional search_index table.
-- 5) Unified ACLs replace workspace_permissions/folder_permissions/document_permissions.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =============================================================
-- Helper: ensure UTC
-- =============================================================
-- SET time_zone = '+00:00';
 
-- =============================================================
-- USERS & WORKSPACES
-- =============================================================
CREATE TABLE users (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  email           VARCHAR(255) NOT NULL UNIQUE,
  password_hash   VARCHAR(255) NOT NULL,
  first_name      VARCHAR(100) NULL,
  last_name       VARCHAR(100) NULL,
  avatar_url      VARCHAR(512) NULL,
  locale          VARCHAR(10)  NOT NULL DEFAULT 'en',
  time_zone       VARCHAR(64)  NOT NULL DEFAULT 'UTC',
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  is_admin        TINYINT(1) NOT NULL DEFAULT 0,
  last_login_at   DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at      DATETIME NULL
) ENGINE=InnoDB;

-- Create the 'user_sessions' table
DROP TABLE IF EXISTS user_sessions;
CREATE TABLE user_sessions (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
user_id BIGINT UNSIGNED NOT NULL,
token_jti VARCHAR(255) NULL,
expires_at TIMESTAMP NULL,
ip_address VARCHAR(45) NULL,
user_agent TEXT NULL,
payload TEXT NULL,
last_activity TIMESTAMP NULL,
device_type VARCHAR(50) NULL,
browser VARCHAR(100) NULL,
platform VARCHAR(100) NULL,
country VARCHAR(100) NULL,
city VARCHAR(100) NULL,
login_time TIMESTAMP NULL,
logout_time TIMESTAMP NULL,
is_active TINYINT(1) NOT NULL DEFAULT '1',
created_at TIMESTAMP NULL,
updated_at TIMESTAMP NULL,

-- Add indexes for improved query performance
INDEX user_sessions_user_id_index (user_id),
INDEX user_sessions_is_active_index (is_active),
INDEX user_sessions_last_activity_index (last_activity),

-- Add foreign key constraint to the 'users' table
CONSTRAINT user_sessions_user_id_foreign
FOREIGN KEY (user_id)
REFERENCES users (id)
ON DELETE CASCADE,

PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE workspaces (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name            VARCHAR(255) NOT NULL,
  description     TEXT NULL,
  owner_user_id   BIGINT UNSIGNED NULL,
  storage_quota   BIGINT UNSIGNED NOT NULL DEFAULT 1073741824,
  storage_used    BIGINT UNSIGNED NOT NULL DEFAULT 0,
  is_archived     TINYINT(1) NOT NULL DEFAULT 0,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_ws_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS workspace_settings (
  id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  workspace_id  BIGINT UNSIGNED NOT NULL,
  k             VARCHAR(120) NOT NULL,
  v             JSON NOT NULL,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                 ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ws_setting (workspace_id, k),
  CONSTRAINT fk_ws_settings_ws
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Roles & permissions
CREATE TABLE roles (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  key_name        VARCHAR(100) NOT NULL, -- e.g. 'workspace_admin','editor','viewer'
  label           VARCHAR(120) NOT NULL,
  workspace_id    BIGINT UNSIGNED NULL,  -- null => system role template
  is_system_role  TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_role_scope (workspace_id, key_name),
  CONSTRAINT fk_roles_ws FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE permissions (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  key_name        VARCHAR(120) NOT NULL UNIQUE, -- e.g. 'document.view','document.edit'
  label           VARCHAR(160) NOT NULL,
  description     VARCHAR(500) NULL
) ENGINE=InnoDB;

CREATE TABLE role_permissions (
  role_id         BIGINT UNSIGNED NOT NULL,
  permission_id   BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE workspace_members (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  workspace_id    BIGINT UNSIGNED NOT NULL,
  user_id         BIGINT UNSIGNED NOT NULL,
  role_id         BIGINT UNSIGNED NOT NULL,
  status          ENUM('invited','active','suspended') NOT NULL DEFAULT 'active',
  joined_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at      DATETIME NULL, 
  UNIQUE KEY uq_member (workspace_id, user_id),
  CONSTRAINT fk_wm_ws FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
  CONSTRAINT fk_wm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_wm_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

-- Unified ACLs (replace workspace_permissions / folder_permissions / document_permissions)
CREATE TABLE acls (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  workspace_id    BIGINT UNSIGNED NOT NULL,
  subject_type    ENUM('user','role') NOT NULL,
  subject_id      BIGINT UNSIGNED NOT NULL,
  resource_type   ENUM('workspace','folder','document') NOT NULL,
  resource_id     BIGINT UNSIGNED NULL, -- workspace/folder/doc id depending on type
  permission_id   BIGINT UNSIGNED NOT NULL,
  effect          ENUM('allow','deny') NOT NULL DEFAULT 'allow',
  created_by      BIGINT UNSIGNED NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_acl_ws (workspace_id),
  KEY idx_acl_subject (subject_type, subject_id),
  KEY idx_acl_resource (resource_type, resource_id),
  CONSTRAINT fk_acl_ws FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
  CONSTRAINT fk_acl_perm FOREIGN KEY (permission_id) REFERENCES permissions(id),
  CONSTRAINT fk_acl_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- =============================================================
-- FOLDER TREE & DOCUMENTS
-- =============================================================
CREATE TABLE folders (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name            VARCHAR(255) NOT NULL,
  parent_id       BIGINT UNSIGNED NULL,
  workspace_id    BIGINT UNSIGNED NOT NULL,
  path            VARCHAR(1024) NOT NULL,
  depth           INT UNSIGNED NOT NULL DEFAULT 0,
  created_by      BIGINT UNSIGNED NULL,
  is_deleted      TINYINT(1) NOT NULL DEFAULT 0, -- NEW
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at      DATETIME NULL,
  KEY idx_f_ws_parent (workspace_id, parent_id),
  KEY idx_f_ws_path (workspace_id, path(255)),
  CONSTRAINT fk_f_ws FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
  CONSTRAINT fk_f_parent FOREIGN KEY (parent_id) REFERENCES folders(id) ON DELETE SET NULL,
  CONSTRAINT fk_f_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- documents use BIGINT id for joins & BINARY UUID shadow for external refs (optional)
CREATE TABLE documents (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  uuid_bin        BINARY(16) NOT NULL UNIQUE,
  original_name   VARCHAR(255) NOT NULL,
  folder_id       BIGINT UNSIGNED NULL,
  workspace_id    BIGINT UNSIGNED NOT NULL,
  uploaded_by     BIGINT UNSIGNED NOT NULL,
  size_bytes      BIGINT UNSIGNED NOT NULL,
  mime_type       VARCHAR(180) NOT NULL,
  latest_version  INT UNSIGNED NOT NULL DEFAULT 1,
  checksum        VARBINARY(32) NOT NULL,
  confidentiality VARCHAR(20) NOT NULL DEFAULT 'internal',
  is_locked       TINYINT(1) NOT NULL DEFAULT 0,
  locked_by       BIGINT UNSIGNED NULL,
  locked_at       DATETIME NULL,
  expires_at      DATETIME NULL,
  description     TEXT NULL,
  password_hash   VARCHAR(255) NULL,   
  is_remote_wiped TINYINT(1) NOT NULL DEFAULT 0,
  is_deleted      TINYINT(1) NOT NULL DEFAULT 0, 
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at      DATETIME NULL,
  KEY idx_d_ws_folder (workspace_id, folder_id),
  FULLTEXT KEY ftx_d_name (original_name),
  CONSTRAINT fk_d_ws FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
  CONSTRAINT fk_d_folder FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE SET NULL,
  CONSTRAINT fk_d_uploader FOREIGN KEY (uploaded_by) REFERENCES users(id),
  CONSTRAINT fk_d_locked_by FOREIGN KEY (locked_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE document_versions (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  uuid_bin        BINARY(16) NOT NULL UNIQUE,
  document_id     BIGINT UNSIGNED NOT NULL,
  version_no      INT UNSIGNED NOT NULL,
  storage_key     VARCHAR(300) NOT NULL,
  original_name   VARCHAR(255) NOT NULL,
  mime_type       VARCHAR(180) NOT NULL,
  extension       VARCHAR(20) NULL,
  size_bytes      BIGINT UNSIGNED NOT NULL,
  sha256          VARBINARY(32) NOT NULL,
  uploaded_by     BIGINT UNSIGNED NOT NULL,
  uploaded_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  change_note     VARCHAR(500) NULL,
  UNIQUE KEY uq_doc_version (document_id, version_no),
  KEY idx_dv_doc (document_id),
  CONSTRAINT fk_dv_doc FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
  CONSTRAINT fk_dv_user FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE document_revisions (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  document_id     BIGINT UNSIGNED NOT NULL,
  version_id      BIGINT UNSIGNED NOT NULL,
  revision_no     INT UNSIGNED NOT NULL,
  status          ENUM('draft','in_review','approved','rejected','obsolete') NOT NULL DEFAULT 'draft',
  effective_date  DATE NULL,
  expiration_date DATE NULL,
  approver_id     BIGINT UNSIGNED NULL,
  approved_at     DATETIME NULL,
  change_summary  TEXT NULL,
  UNIQUE KEY uq_rev (document_id, revision_no),
  CONSTRAINT fk_rev_doc FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
  CONSTRAINT fk_rev_ver FOREIGN KEY (version_id) REFERENCES document_versions(id) ON DELETE CASCADE,
  CONSTRAINT fk_rev_user FOREIGN KEY (approver_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- Explicit document lock (admin override by deleting row)
CREATE TABLE document_locks (
  document_id     BIGINT UNSigned PRIMARY KEY,
  locked_by       BIGINT UNSIGNED NOT NULL,
  locked_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  lock_reason     VARCHAR(255) NULL,
  override_key    VARCHAR(64) NULL,
  CONSTRAINT fk_lock_doc FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
  CONSTRAINT fk_lock_user FOREIGN KEY (locked_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- =============================================================
-- COLLABORATION & APPROVALS
-- =============================================================
CREATE TABLE approvals (
  id                    BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  document_version_id   BIGINT UNSIGNED NOT NULL,
  step_no               INT UNSIGNED NOT NULL DEFAULT 1,
  assigned_to           BIGINT UNSIGNED NOT NULL,
  status                ENUM('pending','approved','rejected','skipped') NOT NULL DEFAULT 'pending',
  decision_note         VARCHAR(500) NULL,
  decided_at            DATETIME NULL,
  created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ap_ver (document_version_id, step_no),
  KEY idx_ap_user (assigned_to, status),
  CONSTRAINT fk_ap_ver FOREIGN KEY (document_version_id) REFERENCES document_versions(id) ON DELETE CASCADE,
  CONSTRAINT fk_ap_user FOREIGN KEY (assigned_to) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE comments (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  workspace_id    BIGINT UNSIGNED NOT NULL,
  resource_type   ENUM('document','document_version','folder') NOT NULL,
  resource_id     BIGINT UNSIGNED NOT NULL,
  author_id       BIGINT UNSIGNED NOT NULL,
  parent_id       BIGINT UNSIGNED NULL,
  body            TEXT NOT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NULL,
  resolved_at     DATETIME NULL,
  resolved_by     BIGINT UNSIGNED NULL,
  KEY idx_c_ws_res (workspace_id, resource_type, resource_id),
  CONSTRAINT fk_c_ws FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
  CONSTRAINT fk_c_author FOREIGN KEY (author_id) REFERENCES users(id),
  CONSTRAINT fk_c_parent FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE,
  CONSTRAINT fk_c_resolved_by FOREIGN KEY (resolved_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE tasks (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  title           VARCHAR(255) NOT NULL,
  description     TEXT NULL,
  due_at          DATETIME NULL,
  priority        ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  status          ENUM('open','in_progress','completed','cancelled') NOT NULL DEFAULT 'open',
  created_by      BIGINT UNSIGNED NOT NULL,
  assigned_to     BIGINT UNSIGNED NULL,
  document_id     BIGINT UNSIGNED NULL,
  workspace_id    BIGINT UNSIGNED NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at    DATETIME NULL,
  KEY idx_t_ws (workspace_id),
  KEY idx_t_status (status),
  KEY idx_t_due (due_at),
  CONSTRAINT fk_t_doc FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
  CONSTRAINT fk_t_ws FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
  CONSTRAINT fk_t_creator FOREIGN KEY (created_by) REFERENCES users(id),
  CONSTRAINT fk_t_assignee FOREIGN KEY (assigned_to) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE notifications (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id         BIGINT UNSIGNED NOT NULL,
  type            VARCHAR(120) NOT NULL,
  payload         JSON NOT NULL,
  read_at         DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_n_user (user_id, read_at),
  CONSTRAINT fk_n_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Sharing & downloads (version‑aware)
CREATE TABLE shared_links (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  workspace_id    BIGINT UNSIGNED NOT NULL,
  resource_type   ENUM('folder','document','document_version') NOT NULL,
  resource_id     BIGINT UNSIGNED NOT NULL,
  token           CHAR(32) NOT NULL UNIQUE,
  expires_at      DATETIME NULL,
  max_downloads   INT UNSIGNED NULL,
  download_count  INT UNSIGNED NOT NULL DEFAULT 0,
  password_hash   VARCHAR(255) NULL,
  created_by      BIGINT UNSIGNED NOT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sl_ws_res (workspace_id, resource_type, resource_id),
  CONSTRAINT fk_sl_ws FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
  CONSTRAINT fk_sl_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE download_history (
  id                    BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id               BIGINT UNSIGNED NULL,
  shared_link_id        BIGINT UNSIGNED NULL,
  document_version_id   BIGINT UNSIGNED NOT NULL,
  ip_address            VARBINARY(16) NULL,
  user_agent            VARCHAR(300) NULL,
  created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_dlh_user (user_id, created_at),
  KEY idx_dlh_ver (document_version_id),
  CONSTRAINT fk_dlh_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_dlh_link FOREIGN KEY (shared_link_id) REFERENCES shared_links(id) ON DELETE SET NULL,
  CONSTRAINT fk_dlh_ver FOREIGN KEY (document_version_id) REFERENCES document_versions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE document_previews (
  id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  document_version_id BIGINT UNSIGNED NOT NULL,
  kind                ENUM('thumb','pdf','image','text') NOT NULL,
  storage_key         VARCHAR(300) NOT NULL,
  width               INT NULL,
  height              INT NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_dp_ver (document_version_id, kind),
  CONSTRAINT fk_dp_ver FOREIGN KEY (document_version_id) REFERENCES document_versions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE view_history (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id         BIGINT UNSIGNED NOT NULL,
  resource_type   ENUM('folder','document','document_version') NOT NULL,
  resource_id     BIGINT UNSIGNED NOT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_vh_user (user_id, created_at),
  KEY idx_vh_res (resource_type, resource_id),
  CONSTRAINT fk_vh_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE favorites (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id         BIGINT UNSIGNED NOT NULL,
  resource_type   ENUM('folder','document') NOT NULL,
  resource_id     BIGINT UNSIGNED NOT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_fav (user_id, resource_type, resource_id),
  CONSTRAINT fk_fav_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE templates (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  workspace_id    BIGINT UNSIGNED NOT NULL,
  name            VARCHAR(200) NOT NULL,
  description     VARCHAR(500) NULL,
  storage_key     VARCHAR(300) NOT NULL,
  created_by      BIGINT UNSIGNED NOT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tpl_ws FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
  CONSTRAINT fk_tpl_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE custom_fields (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  workspace_id    BIGINT UNSIGNED NOT NULL,
  entity_type     ENUM('folder','document') NOT NULL,
  key_name        VARCHAR(120) NOT NULL,
  label           VARCHAR(160) NOT NULL,
  field_type      ENUM('text','number','date','select','multiselect','boolean') NOT NULL,
  config_json     JSON NULL,
  is_required     TINYINT(1) NOT NULL DEFAULT 0,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cf (workspace_id, entity_type, key_name),
  CONSTRAINT fk_cf_ws FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE custom_field_values (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  field_id        BIGINT UNSIGNED NOT NULL,
  entity_type     ENUM('folder','document') NOT NULL,
  entity_id       BIGINT UNSIGNED NOT NULL,
  value_json      JSON NULL,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cfv (field_id, entity_type, entity_id),
  CONSTRAINT fk_cfv_field FOREIGN KEY (field_id) REFERENCES custom_fields(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================================
-- SECURITY / COMPLIANCE / SYSTEM
-- =============================================================
CREATE TABLE audit_logs (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  workspace_id    BIGINT UNSIGNED NULL,
  actor_user_id   BIGINT UNSIGNED NULL,
  action          VARCHAR(120) NOT NULL,
  resource_type   VARCHAR(60)  NULL,
  resource_id     BIGINT UNSIGNED NULL,
  ip_address      VARBINARY(16) NULL,
  user_agent      VARCHAR(300) NULL,
  metadata        JSON NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_a_ws_time (workspace_id, created_at),
  KEY idx_a_actor (actor_user_id, created_at),
  CONSTRAINT fk_a_ws FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE SET NULL,
  CONSTRAINT fk_a_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE system_settings (
  k               VARCHAR(120) PRIMARY KEY,
  v               JSON NOT NULL,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE retention_policies (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  workspace_id    BIGINT UNSIGNED NOT NULL,
  name            VARCHAR(160) NOT NULL,
  description     TEXT NULL,
  keep_rule_json  JSON NOT NULL, -- e.g. {"keep_months": 36}
  action          ENUM('delete','archive','review') NOT NULL DEFAULT 'delete',
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by      BIGINT UNSIGNED NULL,
  CONSTRAINT fk_rp_ws FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE document_retention (
  document_id     BIGINT UNSIGNED PRIMARY KEY,
  policy_id       BIGINT UNSIGNED NOT NULL,
  applied_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  applied_by      BIGINT UNSIGNED NULL,
  expires_at      DATETIME NOT NULL,
  CONSTRAINT fk_dr_doc FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
  CONSTRAINT fk_dr_policy FOREIGN KEY (policy_id) REFERENCES retention_policies(id) ON DELETE CASCADE,
  CONSTRAINT fk_dr_user FOREIGN KEY (applied_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE legal_holds (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  workspace_id    BIGINT UNSIGNED NOT NULL,
  name            VARCHAR(255) NOT NULL,
  description     TEXT NULL,
  case_number     VARCHAR(100) NULL,
  issued_by       BIGINT UNSIGNED NOT NULL,
  issued_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  released_at     DATETIME NULL,
  released_by     BIGINT UNSIGNED NULL,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_lh_ws FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
  CONSTRAINT fk_lh_issuer FOREIGN KEY (issued_by) REFERENCES users(id),
  CONSTRAINT fk_lh_release FOREIGN KEY (released_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE legal_hold_items (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  legal_hold_id   BIGINT UNSIGNED NOT NULL,
  resource_type   ENUM('folder','document') NOT NULL,
  resource_id     BIGINT UNSIGNED NOT NULL,
  placed_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  placed_by       BIGINT UNSIGNED NULL,
  CONSTRAINT fk_lhi_hold FOREIGN KEY (legal_hold_id) REFERENCES legal_holds(id) ON DELETE CASCADE,
  CONSTRAINT fk_lhi_user FOREIGN KEY (placed_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE disposal_records (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  workspace_id    BIGINT UNSIGNED NOT NULL,
  resource_type   ENUM('folder','document','document_version') NOT NULL,
  resource_id     BIGINT UNSIGNED NOT NULL,
  method          VARCHAR(50) NOT NULL, -- 'crypto_shred', etc.
  performed_by    BIGINT UNSIGNED NOT NULL,
  retention_policy_id BIGINT UNSIGNED NULL,
  evidence_json   JSON NULL,
  certificate_key VARCHAR(300) NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_disp_ws FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
  CONSTRAINT fk_disp_user FOREIGN KEY (performed_by) REFERENCES users(id),
  CONSTRAINT fk_disp_policy FOREIGN KEY (retention_policy_id) REFERENCES retention_policies(id)
) ENGINE=InnoDB;

CREATE TABLE access_requests (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  requester_id    BIGINT UNSIGNED NOT NULL,
  document_id     BIGINT UNSIGNED NULL,
  workspace_id    BIGINT UNSIGNED NULL,
  request_type    ENUM('access','deletion','correction') NOT NULL,
  status          ENUM('pending','approved','rejected','completed') NOT NULL DEFAULT 'pending',
  justification   TEXT NOT NULL,
  requested_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at    DATETIME NULL,
  processed_by    BIGINT UNSIGNED NULL,
  response_note   TEXT NULL,
  CONSTRAINT fk_ar_user FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ar_doc FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
  CONSTRAINT fk_ar_ws FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
  CONSTRAINT fk_ar_proc FOREIGN KEY (processed_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE signatures (
  id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  document_version_id BIGINT UNSIGNED NOT NULL,
  signer_user_id      BIGINT UNSIGNED NULL,
  signer_name         VARCHAR(200) NULL,
  signer_email        VARCHAR(255) NULL,
  status              ENUM('pending','signed','declined','expired') NOT NULL DEFAULT 'pending',
  signed_at           DATETIME NULL,
  evidence_json       JSON NULL,
  ip_address          VARBINARY(16) NULL,
  audit_trail_hash    VARBINARY(32) NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_revoked          TINYINT(1) NOT NULL DEFAULT 0,
  revoked_at          DATETIME NULL,
  revoked_by          BIGINT UNSIGNED NULL,
  revoke_reason       TEXT NULL,
  CONSTRAINT fk_sig_ver FOREIGN KEY (document_version_id) REFERENCES document_versions(id) ON DELETE CASCADE,
  CONSTRAINT fk_sig_user FOREIGN KEY (signer_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_sig_revoke FOREIGN KEY (revoked_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Optional: search_index for content
CREATE TABLE search_index (
  resource_type   ENUM('document','comment') NOT NULL,
  resource_id     BIGINT UNSIGNED NOT NULL,
  content         LONGTEXT NOT NULL,
  PRIMARY KEY (resource_type, resource_id),
  FULLTEXT KEY ftx_search (content)
) ENGINE=InnoDB;

-- =============================================================
-- VIEWS for readable UUIDs (optional)
-- =============================================================
CREATE OR REPLACE VIEW v_documents_uuid AS
SELECT id,
       CONCAT(
           SUBSTR(HEX(uuid_bin), 9, 8), '-',
           SUBSTR(HEX(uuid_bin), 5, 4), '-',
           SUBSTR(HEX(uuid_bin), 1, 4), '-',
           SUBSTR(HEX(uuid_bin), 17, 4), '-',
           SUBSTR(HEX(uuid_bin), 21, 12)
       ) AS uuid,
       original_name, workspace_id, folder_id, latest_version,
       created_at, updated_at
FROM documents;
-- =============================================================
-- EMAIL & NOTIFICATIONS
-- =============================================================
CREATE TABLE email_templates (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  template_key    VARCHAR(120) NOT NULL UNIQUE,
  subject         VARCHAR(255) NOT NULL,
  body_html       TEXT NOT NULL,
  body_text       TEXT NOT NULL,
  variables_json  JSON NOT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE email_queue (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  recipient_email VARCHAR(255) NOT NULL,
  subject         VARCHAR(255) NOT NULL,
  body_html       TEXT NOT NULL,
  body_text       TEXT NOT NULL,
  status          ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  attempts        INT UNSIGNED NOT NULL DEFAULT 0,
  last_error      TEXT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at         DATETIME NULL,
  KEY idx_eq_status (status, created_at)
) ENGINE=InnoDB;

-- =============================================================
-- API & INTEGRATIONS
-- =============================================================
CREATE TABLE api_keys (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id         BIGINT UNSIGNED NOT NULL,
  name            VARCHAR(120) NOT NULL,
  key_hash        VARCHAR(255) NOT NULL,
  last_used_at    DATETIME NULL,
  expires_at      DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ak_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE webhooks (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  workspace_id    BIGINT UNSIGNED NOT NULL,
  name            VARCHAR(120) NOT NULL,
  url             VARCHAR(512) NOT NULL,
  secret_token    VARCHAR(255) NULL,
  events_json     JSON NOT NULL,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wh_ws FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE webhook_logs (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  webhook_id      BIGINT UNSIGNED NOT NULL,
  event_type      VARCHAR(120) NOT NULL,
  payload_json    JSON NOT NULL,
  response_code   INT NULL,
  response_body   TEXT NULL,
  duration_ms     INT UNSIGNED NULL,
  error_message   TEXT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_wl_webhook (webhook_id, created_at),
  CONSTRAINT fk_wl_webhook FOREIGN KEY (webhook_id) REFERENCES webhooks(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================================
-- SYSTEM MAINTENANCE
-- =============================================================
CREATE TABLE background_jobs (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  job_type        VARCHAR(120) NOT NULL,
  payload_json    JSON NULL,
  status          ENUM('pending','running','completed','failed') NOT NULL DEFAULT 'pending',
  attempts        INT UNSIGNED NOT NULL DEFAULT 0,
  last_error      TEXT NULL,
  scheduled_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  started_at      DATETIME NULL,
  completed_at    DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_bj_status (status, scheduled_at)
) ENGINE=InnoDB;

CREATE TABLE system_health (
  metric_name     VARCHAR(120) PRIMARY KEY,
  metric_value    JSON NOT NULL,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================================
-- NEW MIGRATIONS
-- =============================================================

-- Migration 1: Create companies table and alter users table
CREATE TABLE `companies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `domain` varchar(255) DEFAULT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `companies_domain_unique` (`domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `users` 
ADD COLUMN `company_id` bigint unsigned NULL AFTER `id`;

ALTER TABLE `users` 
ADD CONSTRAINT `users_company_id_foreign` 
FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) 
ON DELETE SET NULL;

-- Migration 2: Create teams and team_members tables
CREATE TABLE `teams` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `teams_workspace_id_foreign` (`workspace_id`),
  CONSTRAINT `teams_workspace_id_foreign` 
  FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) 
  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `team_members` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `role` varchar(60) NOT NULL DEFAULT 'member',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `team_members_team_id_user_id_unique` (`team_id`,`user_id`),
  KEY `team_members_user_id_foreign` (`user_id`),
  CONSTRAINT `team_members_team_id_foreign` 
  FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) 
  ON DELETE CASCADE,
  CONSTRAINT `team_members_user_id_foreign` 
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) 
  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration 3: Create file_requests table
CREATE TABLE `file_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint unsigned NOT NULL,
  `folder_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `instructions` text,
  `token` char(32) NOT NULL,
  `opens_at` datetime DEFAULT NULL,
  `closes_at` datetime DEFAULT NULL,
  `require_email` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `file_requests_token_unique` (`token`),
  KEY `file_requests_workspace_id_foreign` (`workspace_id`),
  KEY `file_requests_folder_id_foreign` (`folder_id`),
  KEY `file_requests_created_by_foreign` (`created_by`),
  CONSTRAINT `file_requests_created_by_foreign` 
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `file_requests_folder_id_foreign` 
  FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) 
  ON DELETE SET NULL,
  CONSTRAINT `file_requests_workspace_id_foreign` 
  FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) 
  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration 4: Create task_assignees and task_comments tables
CREATE TABLE `task_assignees` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `task_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `task_assignees_task_id_user_id_unique` (`task_id`,`user_id`),
  KEY `task_assignees_user_id_foreign` (`user_id`),
  CONSTRAINT `task_assignees_task_id_foreign` 
  FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) 
  ON DELETE CASCADE,
  CONSTRAINT `task_assignees_user_id_foreign` 
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) 
  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `task_comments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `task_id` bigint unsigned NOT NULL,
  `author_id` bigint unsigned NOT NULL,
  `body` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_comments_task_id_foreign` (`task_id`),
  KEY `task_comments_author_id_foreign` (`author_id`),
  CONSTRAINT `task_comments_author_id_foreign` 
  FOREIGN KEY (`author_id`) REFERENCES `users` (`id`),
  CONSTRAINT `task_comments_task_id_foreign` 
  FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) 
  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE search_history (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL,
  query VARCHAR(500) NOT NULL,
  result_count INT UNSIGNED NOT NULL,
  filters_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_search_user (user_id, created_at),
  KEY idx_search_query (query(255)),
  CONSTRAINT fk_search_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE saved_searches (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  query_params JSON NOT NULL,
  is_global TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_saved_user (user_id),
  CONSTRAINT fk_saved_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================================
-- FINAL SETUP (revised)
-- =============================================================

-- System settings (store real JSON types)
INSERT INTO system_settings (k, v) VALUES
('system.version', '"1.0.0"'),
('system.maintenance_mode', 'false'),
('system.allow_registration', 'true'),
('storage.default_quota', '1073741824'),  -- 1GB number (as string)
('security.password_policy.min_length', '8'),
('security.password_policy.require_mixed_case', 'true'),
('security.password_policy.require_numbers', 'true'),
('security.password_policy.require_symbols', 'false'),
('security.session_lifetime_minutes', '1440'),
('security.2fa.enforced', 'false'),
('email.default_from', '"Huddle <no-reply@huddle.example.com>"'),
('ui.default_theme', '"light"');

-- Create core system roles (workspace_id = NULL => system-scope templates)
INSERT INTO roles (key_name, label, is_system_role, workspace_id) VALUES
('system_admin',     'System Administrator', 1, NULL),
('workspace_owner',  'Workspace Owner',      1, NULL),
('workspace_member', 'Workspace Member',     1, NULL),
('workspace_guest',  'Workspace Guest',      1, NULL);

-- Create core permissions (by key_name; avoids numeric guesswork)
INSERT INTO permissions (key_name, label, description) VALUES
('system.admin',        'System Administration', 'Full access to system settings and administration'),
('user.manage',         'User Management',       'Create, edit, and delete users'),
('workspace.create',    'Create Workspace',      'Create new workspaces'),
('workspace.manage',    'Manage Workspace',      'Edit workspace settings and members'),
('workspace.delete',    'Delete Workspace',      'Delete workspaces'),
('folder.create',       'Create Folder',         'Create new folders'),
('folder.manage',       'Manage Folder',         'Edit folder properties and permissions'),
('folder.delete',       'Delete Folder',         'Delete folders'),
('document.upload',     'Upload Document',       'Upload new documents'),
('document.view',       'View Document',         'View document content and metadata'),
('document.edit',       'Edit Document',         'Edit document metadata and content'),
('document.delete',     'Delete Document',       'Delete documents'),
('document.download',   'Download Document',     'Download document files'),
('document.share',      'Share Document',        'Create and manage document shares'),
('comment.create',      'Create Comment',        'Add comments to documents'),
('comment.manage',      'Manage Comment',        'Edit and delete comments'),
('comment.resolve',     'Resolve Comment',       'Mark comments as resolved'),
('version.manage',      'Manage Versions',       'View and manage document versions'),
('version.rollback',    'Rollback Version',      'Restore previous document versions');

-- Grant ALL permissions to system_admin
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON 1
WHERE r.key_name = 'system_admin' AND r.workspace_id IS NULL;

-- Grant broad workspace owner rights
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.key_name IN (
  'workspace.manage','workspace.delete',
  'folder.create','folder.manage','folder.delete',
  'document.upload','document.view','document.edit','document.delete','document.download','document.share',
  'comment.create','comment.manage','comment.resolve',
  'version.manage','version.rollback'
)
WHERE r.key_name = 'workspace_owner' AND r.workspace_id IS NULL;

-- Grant member basic rights
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.key_name IN (
  'folder.create',
  'document.upload','document.view','document.edit','document.download',
  'comment.create',
  'version.manage'
)
WHERE r.key_name = 'workspace_member' AND r.workspace_id IS NULL;

-- Grant guest read-only
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.key_name IN ('document.view','document.download','version.manage')
WHERE r.key_name = 'workspace_guest' AND r.workspace_id IS NULL;

-- Create initial admin user (password: "Admin123!")
INSERT INTO users (email, password_hash, first_name, last_name, is_active)
VALUES ('admin@example.com',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'System','Administrator', 1);

-- Create initial workspace owned by the admin
INSERT INTO workspaces (name, description, owner_user_id)
VALUES ('Default Workspace', 'Initial workspace for the system', 1);

-- Make the admin the workspace owner in workspace 1
INSERT INTO workspace_members (workspace_id, user_id, role_id, status)
SELECT 1, 1, r.id, 'active'
FROM roles r
WHERE r.key_name = 'workspace_owner' AND r.workspace_id IS NULL
LIMIT 1;

-- Create a root folder
INSERT INTO folders (name, workspace_id, path, depth, created_by)
VALUES ('Root', 1, '/', 0, 1);

-- Essential email templates
INSERT INTO email_templates (template_key, subject, body_html, body_text, variables_json) VALUES
('user_welcome',   'Welcome to Huddle!',              '<p>Welcome {{user.first_name}}!</p>',            'Welcome {{user.first_name}}!',            JSON_ARRAY('user')),
('password_reset', 'Reset your Huddle password',      '<p>Click here to reset your password: {{reset_link}}</p>', 'Reset your password: {{reset_link}}', JSON_ARRAY('user','reset_link')),
('document_shared','{{sender.name}} shared a document with you', '<p>You have been granted access to a document.</p>', 'You have been granted access to a document.', JSON_ARRAY('sender','document'));


-- If your .env uses SESSION_DRIVER=database you must add this table

-- CREATE TABLE `sessions` (
--  `id` varchar(255) NOT NULL,
--  `user_id` bigint unsigned NULL,
--  `ip_address` varchar(45) NULL,
-- `user_agent` text NULL,
--  `payload` longtext NOT NULL,
--  `last_activity` int NOT NULL,
--  PRIMARY KEY (`id`),
--  KEY `sessions_last_activity_index` (`last_activity`),
--  KEY `sessions_user_id_index` (`user_id`)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- =============================================================
-- USERS
-- =============================================================
INSERT INTO users (email, password_hash, first_name, last_name, is_active, is_admin)
VALUES

('user@example.com',
 '$2y$10$eImiTXuWVxfM37uY4JANjQ==',  -- bcrypt for "User123!" (replace with a proper hash generator)
 'Regular','User',1,0),
('client@example.com',
 '$2y$10$7EqJtq98hPqEX7fNZaFWoO==', -- bcrypt for "Client123!" (replace with a proper hash)
 'Client','User',1,0);

-- Capture IDs
SET @admin_id   = (SELECT id FROM users WHERE email='admin@example.com');
SET @user_id    = (SELECT id FROM users WHERE email='user@example.com');
SET @client_id  = (SELECT id FROM users WHERE email='client@example.com');

-- =============================================================
-- WORKSPACES
-- =============================================================
INSERT INTO workspaces (name, description, owner_user_id)
VALUES
('Marketing Campaign','Workspace for marketing projects', @admin_id),
('Product Development','Workspace for product R&D', @admin_id);

SET @marketing_ws = (SELECT id FROM workspaces WHERE name='Marketing Campaign');
SET @product_ws   = (SELECT id FROM workspaces WHERE name='Product Development');

-- Assign roles
INSERT INTO workspace_members (workspace_id, user_id, role_id, status)
SELECT @marketing_ws, @user_id, r.id, 'active'
FROM roles r WHERE r.key_name='workspace_member' AND r.workspace_id IS NULL;

INSERT INTO workspace_members (workspace_id, user_id, role_id, status)
SELECT @marketing_ws, @client_id, r.id, 'active'
FROM roles r WHERE r.key_name='workspace_guest' AND r.workspace_id IS NULL;

INSERT INTO workspace_members (workspace_id, user_id, role_id, status)
SELECT @product_ws, @user_id, r.id, 'active'
FROM roles r WHERE r.key_name='workspace_member' AND r.workspace_id IS NULL;

-- =============================================================
-- FOLDERS
-- =============================================================
INSERT INTO folders (name, workspace_id, path, depth, created_by)
VALUES
('Campaign Assets', @marketing_ws, '/Campaign Assets', 1, @admin_id),
('Reports', @marketing_ws, '/Reports', 1, @admin_id),
('Designs', @product_ws, '/Designs', 1, @admin_id),
('Specifications', @product_ws, '/Specifications', 1, @admin_id);

SET @folder_marketing = (SELECT id FROM folders WHERE name='Campaign Assets');
SET @folder_product   = (SELECT id FROM folders WHERE name='Designs');

-- =============================================================
-- DOCUMENTS & VERSIONS
-- =============================================================
INSERT INTO documents (uuid_bin, original_name, folder_id, workspace_id, uploaded_by,
                       size_bytes, mime_type, checksum)
VALUES
(UUID_TO_BIN(UUID(),1),'Marketing Plan.docx', @folder_marketing, @marketing_ws, @user_id,
 12345,'application/vnd.openxmlformats-officedocument.wordprocessingml.document', UNHEX(MD5('mp1'))),
(UUID_TO_BIN(UUID(),1),'Product Roadmap.pdf', @folder_product, @product_ws, @user_id,
 23456,'application/pdf', UNHEX(MD5('pr1')));

SET @doc_marketing = (SELECT id FROM documents WHERE original_name='Marketing Plan.docx');
SET @doc_product   = (SELECT id FROM documents WHERE original_name='Product Roadmap.pdf');

INSERT INTO document_versions (uuid_bin, document_id, version_no, storage_key,
                               original_name, mime_type, size_bytes, sha256, uploaded_by)
VALUES
(UUID_TO_BIN(UUID(),1), @doc_marketing, 1,'/docs/marketing_plan_v1.docx',
 'Marketing Plan.docx','application/vnd.openxmlformats-officedocument.wordprocessingml.document',
 12345, UNHEX(MD5('mpv1')), @user_id),
(UUID_TO_BIN(UUID(),1), @doc_product, 1,'/docs/product_roadmap_v1.pdf',
 'Product Roadmap.pdf','application/pdf',
 23456, UNHEX(MD5('prv1')), @user_id);

-- =============================================================
-- TASKS
-- =============================================================
INSERT INTO tasks (title, description, due_at, priority, status, created_by, assigned_to, workspace_id)
VALUES
('Draft social media posts','Prepare initial campaign posts', DATE_ADD(NOW(), INTERVAL 7 DAY),
 'high','open', @admin_id, @user_id, @marketing_ws),
('Review marketing plan','Check the uploaded plan document', DATE_ADD(NOW(), INTERVAL 5 DAY),
 'medium','open', @admin_id, @user_id, @marketing_ws);

-- =============================================================
-- FILE REQUESTS
-- =============================================================
INSERT INTO file_requests (workspace_id, folder_id, created_by, title, instructions, token)
VALUES (@marketing_ws, @folder_marketing, @admin_id,
        'Upload Client Assets','Please upload logos and brand guidelines', SUBSTRING(MD5(RAND()),1,32));

-- =============================================================
-- COMMENTS
-- =============================================================
INSERT INTO comments (workspace_id, resource_type, resource_id, author_id, body)
VALUES
(@marketing_ws,'document', @doc_marketing, @user_id,'Looks good, but needs financial projections.'),
(@product_ws,'document', @doc_product, @client_id,'Can we get a draft of version 2 soon?');

-- =============================================================
-- TEAMS
-- =============================================================
INSERT INTO teams (workspace_id, name, description)
VALUES (@marketing_ws,'Marketing Team','Team working on campaigns');

SET @team_marketing = (SELECT id FROM teams WHERE name='Marketing Team');

INSERT INTO team_members (team_id, user_id, role)
VALUES (@team_marketing, @user_id,'member'),
       (@team_marketing, @admin_id,'owner');

-- =============================================================
-- AUDIT LOGS
-- =============================================================
INSERT INTO audit_logs (workspace_id, actor_user_id, action, resource_type, resource_id, metadata)
VALUES
(@marketing_ws, @user_id, 'document.upload','document', @doc_marketing, JSON_OBJECT('note','Uploaded Marketing Plan')),
(@product_ws, @user_id, 'document.upload','document', @doc_product, JSON_OBJECT('note','Uploaded Product Roadmap')),
(@marketing_ws, @admin_id, 'task.create','task', 1, JSON_OBJECT('note','Assigned Draft Social Media Posts'));
