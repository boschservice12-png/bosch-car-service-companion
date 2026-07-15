-- Migrație inițială (referință SQL) — schema minimă.
-- În implementare va fi generată prin Doctrine Migrations; acest fișier
-- documentează structura țintă și constrângerile cheie.
-- PostgreSQL. UUID prin pgcrypto/gen_random_uuid().

CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- ============ Identity & Customer ============
CREATE TABLE users (
    id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    email         varchar(255) UNIQUE,
    phone         varchar(32),
    password_hash varchar(255),
    role          varchar(32) NOT NULL CHECK (role IN ('CLIENT','SERVICE_ADMIN')),
    totp_secret   varchar(255),
    totp_enabled  boolean NOT NULL DEFAULT false,
    is_active     boolean NOT NULL DEFAULT true,
    created_at    timestamptz NOT NULL DEFAULT now(),
    updated_at    timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE customer_profiles (
    id         uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id    uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    first_name varchar(120),
    last_name  varchar(120),
    phone      varchar(32),
    address    text,
    created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE service_admins (
    id           uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id      uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    display_name varchar(120),
    created_at   timestamptz NOT NULL DEFAULT now()
);

-- ============ Vehicle ============
CREATE TABLE vehicles (
    id           uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    vin          varchar(17) NOT NULL,
    plate_number varchar(16) NOT NULL,
    make         varchar(80),
    model        varchar(80),
    year         int CHECK (year BETWEEN 1950 AND 2100),
    created_at   timestamptz NOT NULL DEFAULT now(),
    updated_at   timestamptz NOT NULL DEFAULT now(),
    deleted_at   timestamptz,
    CONSTRAINT vin_upper CHECK (vin = upper(vin))
);
CREATE UNIQUE INDEX ux_vehicles_vin ON vehicles(vin) WHERE deleted_at IS NULL;

CREATE TABLE vehicle_ownerships (
    id                  uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    vehicle_id          uuid NOT NULL REFERENCES vehicles(id),
    customer_profile_id uuid NOT NULL REFERENCES customer_profiles(id),
    active              boolean NOT NULL DEFAULT true,
    valid_from          date NOT NULL DEFAULT current_date,
    valid_to            date
);
CREATE UNIQUE INDEX ux_vehicle_active_owner ON vehicle_ownerships(vehicle_id) WHERE active;
CREATE INDEX ix_ownership_customer ON vehicle_ownerships(customer_profile_id);

-- ============ Documents (cross-cutting) ============
CREATE TABLE documents (
    id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    storage_key   varchar(512) NOT NULL,
    original_name varchar(255),
    mime_type     varchar(120) NOT NULL,
    size_bytes    bigint NOT NULL CHECK (size_bytes >= 0),
    scan_status   varchar(16) NOT NULL DEFAULT 'PENDING'
                    CHECK (scan_status IN ('PENDING','CLEAN','INFECTED')),
    owner_user_id uuid REFERENCES users(id),
    created_at    timestamptz NOT NULL DEFAULT now(),
    deleted_at    timestamptz
);

-- ============ Deadline ============
CREATE TABLE vehicle_deadlines (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    vehicle_id  uuid NOT NULL REFERENCES vehicles(id),
    type        varchar(32) NOT NULL
                  CHECK (type IN ('ITP','RCA','ROAD_TAX','ROADSIDE_ASSISTANCE')),
    valid_from  date,
    expires_at  date,
    document_id uuid REFERENCES documents(id),
    source      varchar(16) NOT NULL DEFAULT 'CLIENT'
                  CHECK (source IN ('CLIENT','SERVICE','IMPORT')),
    verified_by uuid REFERENCES users(id),
    verified_at timestamptz,
    note        text,
    created_at  timestamptz NOT NULL DEFAULT now(),
    updated_at  timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT chk_deadline_dates CHECK (valid_from IS NULL OR expires_at IS NULL OR expires_at > valid_from)
);
CREATE INDEX ix_deadlines_vehicle ON vehicle_deadlines(vehicle_id);
CREATE INDEX ix_deadlines_expires ON vehicle_deadlines(expires_at);

CREATE TABLE deadline_notifications (
    id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    deadline_id   uuid NOT NULL REFERENCES vehicle_deadlines(id) ON DELETE CASCADE,
    threshold_days int NOT NULL,
    channel       varchar(16) NOT NULL,
    sent_at       timestamptz NOT NULL DEFAULT now(),
    UNIQUE (deadline_id, threshold_days, channel)
);

-- ============ ServiceHistory ============
CREATE TABLE service_history_entries (
    id               uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    vehicle_id       uuid NOT NULL REFERENCES vehicles(id),
    service_date     date NOT NULL,
    mileage          int CHECK (mileage >= 0),
    work_order_number varchar(64),
    category         varchar(80),
    work_summary     text,
    parts_summary    text,
    labor_value      numeric(12,2) CHECK (labor_value >= 0),
    total_value      numeric(12,2) CHECK (total_value >= 0),
    warranty_until   date,
    warranty_text    text,
    status           varchar(16) NOT NULL DEFAULT 'DRAFT'
                       CHECK (status IN ('DRAFT','PUBLISHED','CORRECTED')),
    created_by       uuid NOT NULL REFERENCES users(id),
    published_at     timestamptz,
    created_at       timestamptz NOT NULL DEFAULT now(),
    deleted_at       timestamptz
);
CREATE INDEX ix_history_vehicle ON service_history_entries(vehicle_id);
CREATE INDEX ix_history_status ON service_history_entries(status);

CREATE TABLE service_history_items (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    entry_id    uuid NOT NULL REFERENCES service_history_entries(id) ON DELETE CASCADE,
    description text NOT NULL,
    quantity    numeric(10,2) NOT NULL DEFAULT 1,
    unit_value  numeric(12,2) NOT NULL DEFAULT 0 CHECK (unit_value >= 0)
);

CREATE TABLE service_history_corrections (
    id               uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    entry_id         uuid NOT NULL REFERENCES service_history_entries(id),
    previous_snapshot jsonb NOT NULL,
    reason           text NOT NULL,
    corrected_by     uuid NOT NULL REFERENCES users(id),
    created_at       timestamptz NOT NULL DEFAULT now()
);

-- ============ Requests (quote / roadside / mobility / damage) ============
CREATE TABLE quote_requests (
    id                    uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    vehicle_id            uuid NOT NULL REFERENCES vehicles(id),
    mileage               int,
    symptom_description   text,
    occurrence_conditions text,
    vehicle_drivable      boolean,
    warning_lights        text,
    preferred_contact_method varchar(32),
    preferred_interval    varchar(64),
    status                varchar(24) NOT NULL DEFAULT 'DRAFT',
    created_at            timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ix_quote_vehicle ON quote_requests(vehicle_id);
CREATE INDEX ix_quote_status ON quote_requests(status);

CREATE TABLE quote_request_attachments (
    id               uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    quote_request_id uuid NOT NULL REFERENCES quote_requests(id) ON DELETE CASCADE,
    document_id      uuid NOT NULL REFERENCES documents(id)
);

CREATE TABLE quote_responses (
    id               uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    quote_request_id uuid NOT NULL REFERENCES quote_requests(id),
    body             text NOT NULL,
    document_id      uuid REFERENCES documents(id),
    created_by       uuid NOT NULL REFERENCES users(id),
    created_at       timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE roadside_assistance_requests (
    id                 uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    vehicle_id         uuid NOT NULL REFERENCES vehicles(id),
    latitude           numeric(9,6),
    longitude          numeric(9,6),
    manual_address     text,
    issue_type         varchar(64),
    issue_description  text,
    vehicle_drivable   boolean,
    safe_location      boolean,
    passenger_count    int CHECK (passenger_count >= 0),
    contact_phone      varchar(32),
    status             varchar(16) NOT NULL DEFAULT 'SUBMITTED',
    forwarded_provider varchar(120),
    created_at         timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ix_roadside_status ON roadside_assistance_requests(status);

CREATE TABLE mobility_requests (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    vehicle_id      uuid NOT NULL REFERENCES vehicles(id),
    mobility_type   varchar(24) NOT NULL
                      CHECK (mobility_type IN ('REPLACEMENT_CAR','TAXI','PERSON_TRANSPORT','ACCOMMODATION','OTHER')),
    location        text,
    requested_at    timestamptz,
    passenger_count int CHECK (passenger_count >= 0),
    note            text,
    contact_phone   varchar(32),
    status          varchar(16) NOT NULL DEFAULT 'SUBMITTED',
    created_at      timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE damage_claim_requests (
    id                uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    vehicle_id        uuid NOT NULL REFERENCES vehicles(id),
    event_date_time   timestamptz,
    location          text,
    event_description text,
    other_party_data  jsonb,
    insurer           varchar(120),
    policy_number     varchar(64),
    police_document_id uuid REFERENCES documents(id),
    amicable_report_id uuid REFERENCES documents(id),
    contact_phone     varchar(32),
    status            varchar(24) NOT NULL DEFAULT 'SUBMITTED',
    missing_documents jsonb NOT NULL DEFAULT '[]',
    created_at        timestamptz NOT NULL DEFAULT now()
);

-- ============ Tax ============
CREATE TABLE vehicle_tax_records (
    id                 uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    vehicle_id         uuid NOT NULL REFERENCES vehicles(id),
    tax_type           varchar(64) NOT NULL,
    tax_year           int NOT NULL CHECK (tax_year BETWEEN 1990 AND 2100),
    amount             numeric(12,2) NOT NULL CHECK (amount >= 0),
    due_date           date,
    paid_amount        numeric(12,2) NOT NULL DEFAULT 0 CHECK (paid_amount >= 0),
    paid_at            timestamptz,
    receipt_document_id uuid REFERENCES documents(id),
    note               text,
    status             varchar(16) NOT NULL DEFAULT 'UNPAID'
                         CHECK (status IN ('UNPAID','PARTIALLY_PAID','PAID','OVERDUE')),
    created_at         timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ix_tax_vehicle ON vehicle_tax_records(vehicle_id);

-- ============ Communication ============
CREATE TABLE conversations (
    id                  uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    customer_profile_id uuid NOT NULL REFERENCES customer_profiles(id),
    vehicle_id          uuid REFERENCES vehicles(id),
    subject             varchar(200) NOT NULL,
    status              varchar(20) NOT NULL DEFAULT 'OPEN'
                          CHECK (status IN ('OPEN','WAITING_CLIENT','WAITING_SERVICE','CLOSED')),
    created_at          timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ix_conv_customer ON conversations(customer_profile_id);
CREATE INDEX ix_conv_status ON conversations(status);

CREATE TABLE messages (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    conversation_id uuid NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
    sender_user_id  uuid NOT NULL REFERENCES users(id),
    body            text NOT NULL,
    read_at         timestamptz,
    created_at      timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ix_messages_conv ON messages(conversation_id);

CREATE TABLE message_attachments (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    message_id  uuid NOT NULL REFERENCES messages(id) ON DELETE CASCADE,
    document_id uuid NOT NULL REFERENCES documents(id)
);

-- ============ GDPR / audit / settings / notifications ============
CREATE TABLE consents (
    id           uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id      uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type         varchar(64) NOT NULL,
    granted      boolean NOT NULL,
    text_version varchar(32) NOT NULL,
    granted_at   timestamptz,
    revoked_at   timestamptz
);

CREATE TABLE notifications (
    id         uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id    uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type       varchar(64) NOT NULL,
    payload    jsonb NOT NULL DEFAULT '{}',
    channel    varchar(16) NOT NULL,
    read_at    timestamptz,
    sent_at    timestamptz,
    created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ix_notif_user ON notifications(user_id);

CREATE TABLE audit_logs (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    actor_id    uuid REFERENCES users(id),
    action      varchar(120) NOT NULL,
    entity_type varchar(120),
    entity_id   uuid,
    before      jsonb,
    after       jsonb,
    ip          varchar(64),
    created_at  timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ix_audit_entity ON audit_logs(entity_type, entity_id);
CREATE INDEX ix_audit_created ON audit_logs(created_at);

CREATE TABLE application_settings (
    key        varchar(120) PRIMARY KEY,
    value      jsonb NOT NULL,
    updated_at timestamptz NOT NULL DEFAULT now()
);

-- Setări implicite
INSERT INTO application_settings(key, value) VALUES
    ('deadline.notification_thresholds', '[60,30,7,0]'),
    ('whatsapp.number', '""'),
    ('privacy.text_version', '"v1"')
ON CONFLICT (key) DO NOTHING;
