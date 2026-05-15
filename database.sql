-- ============================================================
-- Referral Network Portal — PostgreSQL Schema (Supabase)
-- ============================================================
-- Paste this entire file into:
--   Supabase Dashboard → SQL Editor → New Query → Run
-- ============================================================

-- Users
CREATE TABLE IF NOT EXISTS users (
    id              SERIAL PRIMARY KEY,
    name            VARCHAR(100)  NOT NULL,
    email           VARCHAR(100)  UNIQUE NOT NULL,
    password        VARCHAR(255)  NOT NULL,
    role            VARCHAR(20)   DEFAULT 'partner' CHECK (role IN ('partner','broker','admin','auditor')),
    status          VARCHAR(20)   DEFAULT 'pending' CHECK (status IN ('pending','active','suspended')),
    tier            VARCHAR(10)   DEFAULT 'Silver'  CHECK (tier IN ('Gold','Silver','Bronze')),
    commission_rate DECIMAL(5,2)  DEFAULT 20.00,
    phone           VARCHAR(30),
    suburb          VARCHAR(100),
    state           VARCHAR(10),
    consent_email   SMALLINT      DEFAULT 1,
    avatar          VARCHAR(3)    DEFAULT 'PT',
    created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);

-- Referrals
CREATE TABLE IF NOT EXISTS referrals (
    id                SERIAL PRIMARY KEY,
    ref_number        VARCHAR(20)  UNIQUE NOT NULL,
    partner_id        INT          NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    broker_id         INT          REFERENCES users(id) ON DELETE SET NULL,
    client_name       VARCHAR(100) NOT NULL,
    client_email      VARCHAR(100),
    client_phone      VARCHAR(30),
    client_suburb     VARCHAR(100),
    client_state      VARCHAR(10),
    loan_type         VARCHAR(30)  DEFAULT 'Owner-Occupied',
    estimated_amount  DECIMAL(14,2) DEFAULT 0,
    broker_upfront    DECIMAL(14,2) DEFAULT 0,
    commission_amount DECIMAL(14,2) DEFAULT 0,
    consent           SMALLINT     DEFAULT 0,
    consent_timestamp TIMESTAMP,
    notes             TEXT,
    broker_notes      TEXT,
    status            VARCHAR(20)  DEFAULT 'pending' CHECK (status IN ('pending','qualified','lodged','approved','settled','declined')),
    document_path     VARCHAR(255),
    document_name     VARCHAR(255),
    date_submitted    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- Auto-update updated_at on referrals
CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER AS $$
BEGIN NEW.updated_at = CURRENT_TIMESTAMP; RETURN NEW; END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS referrals_updated_at ON referrals;
CREATE TRIGGER referrals_updated_at
    BEFORE UPDATE ON referrals
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- Commissions
CREATE TABLE IF NOT EXISTS commissions (
    id              SERIAL PRIMARY KEY,
    referral_id     INT           NOT NULL REFERENCES referrals(id) ON DELETE CASCADE,
    partner_id      INT           NOT NULL REFERENCES users(id)    ON DELETE CASCADE,
    broker_upfront  DECIMAL(14,2) DEFAULT 0,
    rate            DECIMAL(5,2)  DEFAULT 0,
    amount          DECIMAL(14,2) DEFAULT 0,
    override_amount DECIMAL(14,2),
    override_reason TEXT,
    status          VARCHAR(10)   DEFAULT 'pending' CHECK (status IN ('pending','paid')),
    paid_at         TIMESTAMP,
    created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);

-- Documents
CREATE TABLE IF NOT EXISTS documents (
    id          SERIAL PRIMARY KEY,
    referral_id INT          NOT NULL REFERENCES referrals(id) ON DELETE CASCADE,
    user_id     INT          NOT NULL REFERENCES users(id)     ON DELETE CASCADE,
    filename    VARCHAR(255) NOT NULL,
    filepath    VARCHAR(255) NOT NULL,
    filesize    INT          DEFAULT 0,
    uploaded_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- Notifications
CREATE TABLE IF NOT EXISTS notifications (
    id         SERIAL PRIMARY KEY,
    user_id    INT          NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title      VARCHAR(150) NOT NULL,
    message    TEXT,
    is_read    SMALLINT     DEFAULT 0,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- Audit Log
CREATE TABLE IF NOT EXISTS audit_log (
    id          SERIAL PRIMARY KEY,
    user_id     INT,
    user_name   VARCHAR(100),
    action      VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id   INT,
    description TEXT,
    ip_address  VARCHAR(45),
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- Sample Data (password for all accounts: "password")
-- ============================================================

INSERT INTO users (name, email, password, role, status, tier, commission_rate, phone, suburb, state, avatar) VALUES
('Admin User',    'admin@networkportal.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin',   'active', 'Gold',   0,  '0400 000 001', 'Sydney',    'NSW', 'AU'),
('James Broker',  'broker@networkportal.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'broker',  'active', 'Gold',   0,  '0400 000 002', 'Melbourne', 'VIC', 'JB'),
('Alex Thompson', 'alex@partner.com',         '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'partner', 'active', 'Gold',  25,  '0400 000 003', 'Brisbane',  'QLD', 'AT'),
('Sarah Chen',    'sarah@partner.com',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'partner', 'active', 'Silver',20,  '0400 000 004', 'Perth',     'WA',  'SC'),
('Lisa Audit',    'auditor@networkportal.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'auditor', 'active', 'Silver', 0,  '0400 000 006', 'Canberra',  'ACT', 'LA')
ON CONFLICT (email) DO NOTHING;

INSERT INTO referrals (ref_number, partner_id, broker_id, client_name, client_email, client_phone, client_suburb, client_state, loan_type, estimated_amount, broker_upfront, commission_amount, consent, consent_timestamp, notes, status, date_submitted) VALUES
('REF-2024-0001', 3, 2, 'Jonathan Sterling', 'j.sterling@corp.com',   '0411 111 001', 'Bondi Beach', 'NSW', 'Owner-Occupied', 850000,  8500,  2125, 1, '2024-08-14 10:00:00', 'High net worth client.',            'approved',  '2024-08-14 10:00:00'),
('REF-2024-0002', 3, 2, 'Amara Rodriguez',   'a.rodriguez@biz.com',   '0411 111 002', 'Southbank',   'VIC', 'Investment',     620000,  0,     0,    1, '2024-08-12 11:00:00', 'Investment property in Melbourne.', 'pending',   '2024-08-12 11:00:00'),
('REF-2024-0003', 3, 2, 'Marcus Bennett',    'm.bennett@vent.com',    '0411 111 003', 'New Farm',    'QLD', 'Refinance',      1200000, 12000, 3000, 1, '2024-07-10 09:00:00', 'Refinancing existing portfolio.',   'settled',   '2024-07-10 09:00:00'),
('REF-2024-0004', 3, 2, 'Elena Langford',    'e.langford@consult.com','0411 111 004', 'Cottesloe',   'WA',  'Owner-Occupied', 520000,  0,     0,    1, '2024-06-05 08:00:00', 'First home buyer.',                 'declined',  '2024-06-05 08:00:00'),
('REF-2024-0005', 3, 2, 'Tobias Hoffmann',   't.hoffmann@int.com',    '0411 111 005', 'Toorak',      'VIC', 'Investment',     2100000, 21000, 5250, 1, '2024-05-03 07:00:00', 'Established investor.',             'settled',   '2024-05-03 07:00:00'),
('REF-2024-0006', 4, 2, 'David Nguyen',      'd.nguyen@mail.com',     '0411 111 007', 'Richmond',    'VIC', 'Investment',     490000,  4900,  980,  1, '2024-07-20 10:00:00', 'Adding to investment portfolio.',   'settled',   '2024-07-20 10:00:00')
ON CONFLICT (ref_number) DO NOTHING;

INSERT INTO commissions (referral_id, partner_id, broker_upfront, rate, amount, status, paid_at) VALUES
(1, 3, 8500,  25, 2125, 'paid',    '2024-09-01 00:00:00'),
(3, 3, 12000, 25, 3000, 'paid',    '2024-08-15 00:00:00'),
(5, 3, 21000, 25, 5250, 'pending', NULL),
(6, 4, 4900,  20, 980,  'pending', NULL);

INSERT INTO notifications (user_id, title, message, is_read) VALUES
(3, 'Referral Approved',  'Your referral REF-2024-0001 for Jonathan Sterling has been approved.', 0),
(3, 'Commission Paid',    'Commission of $3,000 for REF-2024-0003 (Marcus Bennett) has been paid.', 0),
(3, 'Referral Declined',  'Your referral REF-2024-0004 for Elena Langford has been declined.', 1);

INSERT INTO audit_log (user_id, user_name, action, entity_type, entity_id, description, ip_address) VALUES
(3, 'Alex Thompson', 'referral_submitted', 'referral', 1, 'Submitted REF-2024-0001 for Jonathan Sterling', '127.0.0.1'),
(2, 'James Broker',  'status_changed',     'referral', 1, 'Status changed to approved for REF-2024-0001',  '127.0.0.1'),
(1, 'Admin User',    'commission_paid',    'commission',1,'Marked commission $2,125 as paid for REF-2024-0001','127.0.0.1');
