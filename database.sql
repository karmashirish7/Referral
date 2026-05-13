-- ============================================================
-- Referral Network Portal — Database Schema
-- Australian Mortgage & Real Estate Sector
-- ============================================================
-- Setup: Create the database, then run this file in MySQL.
--   mysql -u root -p < database.sql
--
-- Default logins (password for ALL accounts: "password"):
--   Admin   -> admin@networkportal.com
--   Broker  -> broker@networkportal.com
--   Partner -> alex@partner.com
--   Auditor -> auditor@networkportal.com
-- ============================================================

CREATE DATABASE IF NOT EXISTS referral_portal
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE referral_portal;

-- ============================================================
-- USERS
-- Roles: partner, broker, admin, auditor
-- Status: pending (awaiting admin approval), active, suspended
-- Tier:   Gold (25%), Silver (20%), Bronze (15%)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    name             VARCHAR(100)  NOT NULL,
    email            VARCHAR(100)  UNIQUE NOT NULL,
    password         VARCHAR(255)  NOT NULL,
    role             ENUM('partner','broker','admin','auditor') DEFAULT 'partner',
    status           ENUM('pending','active','suspended') DEFAULT 'pending',
    tier             ENUM('Gold','Silver','Bronze') DEFAULT 'Silver',
    commission_rate  DECIMAL(5,2) DEFAULT 20.00,
    phone            VARCHAR(30),
    suburb           VARCHAR(100),
    state            VARCHAR(10),
    consent_email    TINYINT(1) DEFAULT 1,
    avatar           VARCHAR(3)  DEFAULT 'PT',
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- REFERRALS
-- Statuses: pending -> qualified -> lodged -> approved -> settled -> declined
-- partner_id: the partner who submitted the referral
-- broker_id:  the broker assigned to handle it (can be NULL until assigned)
-- ============================================================
CREATE TABLE IF NOT EXISTS referrals (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    ref_number              VARCHAR(20) UNIQUE NOT NULL,
    partner_id              INT NOT NULL,
    broker_id               INT,
    client_name             VARCHAR(100) NOT NULL,
    client_email            VARCHAR(100),
    client_phone            VARCHAR(30),
    client_suburb           VARCHAR(100),
    client_state            VARCHAR(10),
    loan_type               ENUM('Owner-Occupied','Investment','Refinance','Commercial','Construction') DEFAULT 'Owner-Occupied',
    estimated_amount        DECIMAL(14,2) DEFAULT 0,
    broker_upfront          DECIMAL(14,2) DEFAULT 0,
    commission_amount       DECIMAL(14,2) DEFAULT 0,
    consent                 TINYINT(1) DEFAULT 0,
    consent_timestamp       TIMESTAMP NULL,
    notes                   TEXT,
    broker_notes            TEXT,
    status                  ENUM('pending','qualified','lodged','approved','settled','declined') DEFAULT 'pending',
    document_path           VARCHAR(255),
    document_name           VARCHAR(255),
    date_submitted          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (partner_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (broker_id)  REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
-- COMMISSIONS
-- Created automatically when a referral is settled.
-- status: pending -> paid
-- override_amount/override_reason: admin can override
-- ============================================================
CREATE TABLE IF NOT EXISTS commissions (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    referral_id      INT NOT NULL,
    partner_id       INT NOT NULL,
    broker_upfront   DECIMAL(14,2) DEFAULT 0,
    rate             DECIMAL(5,2)  DEFAULT 0,
    amount           DECIMAL(14,2) DEFAULT 0,
    override_amount  DECIMAL(14,2) DEFAULT NULL,
    override_reason  TEXT,
    status           ENUM('pending','paid') DEFAULT 'pending',
    paid_at          TIMESTAMP NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (referral_id) REFERENCES referrals(id) ON DELETE CASCADE,
    FOREIGN KEY (partner_id)  REFERENCES users(id)    ON DELETE CASCADE
);

-- ============================================================
-- DOCUMENTS
-- Files uploaded against referrals.
-- ============================================================
CREATE TABLE IF NOT EXISTS documents (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    referral_id  INT NOT NULL,
    user_id      INT NOT NULL,
    filename     VARCHAR(255) NOT NULL,
    filepath     VARCHAR(255) NOT NULL,
    filesize     INT DEFAULT 0,
    uploaded_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (referral_id) REFERENCES referrals(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)     REFERENCES users(id)     ON DELETE CASCADE
);

-- ============================================================
-- NOTIFICATIONS
-- In-app alerts sent to users on referral status changes, etc.
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    title       VARCHAR(150) NOT NULL,
    message     TEXT,
    is_read     TINYINT(1) DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- AUDIT LOG
-- Every significant action is logged: actor, action, timestamp.
-- Required for Privacy Act 1988 compliance.
-- ============================================================
CREATE TABLE IF NOT EXISTS audit_log (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT,
    user_name    VARCHAR(100),
    action       VARCHAR(100) NOT NULL,
    entity_type  VARCHAR(50),
    entity_id    INT,
    description  TEXT,
    ip_address   VARCHAR(45),
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
-- SAMPLE DATA
-- All passwords are bcrypt hash of "password"
-- ============================================================

INSERT INTO users (name, email, password, role, status, tier, commission_rate, phone, suburb, state, avatar) VALUES
('Admin User',    'admin@networkportal.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin',   'active', 'Gold',   0.00, '0400 000 001', 'Sydney',     'NSW', 'AU'),
('James Broker',  'broker@networkportal.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'broker',  'active', 'Gold',   0.00, '0400 000 002', 'Melbourne',  'VIC', 'JB'),
('Alex Thompson', 'alex@partner.com',         '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'partner', 'active', 'Gold',  25.00, '0400 000 003', 'Brisbane',   'QLD', 'AT'),
('Sarah Chen',    'sarah@partner.com',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'partner', 'active', 'Silver',20.00, '0400 000 004', 'Perth',      'WA',  'SC'),
('Tom Wilson',    'tom@partner.com',          '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'partner', 'pending','Bronze',15.00, '0400 000 005', 'Adelaide',   'SA',  'TW'),
('Lisa Audit',    'auditor@networkportal.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'auditor', 'active', 'Silver', 0.00, '0400 000 006', 'Canberra',   'ACT', 'LA');

INSERT INTO referrals (ref_number, partner_id, broker_id, client_name, client_email, client_phone, client_suburb, client_state, loan_type, estimated_amount, broker_upfront, commission_amount, consent, consent_timestamp, notes, status, date_submitted) VALUES
('REF-3042', 3, 2, 'Jonathan Sterling', 'j.sterling@corp.com',    '0411 111 001', 'Bondi Beach',  'NSW', 'Owner-Occupied', 850000.00, 8500.00, 2125.00, 1, '2024-08-14 10:00:00', 'High net worth client, very motivated to buy.',     'approved',  '2024-08-14 10:00:00'),
('REF-3041', 3, 2, 'Amara Rodriguez',   'a.rodriguez@biz.com',    '0411 111 002', 'Southbank',    'VIC', 'Investment',     620000.00, 0,       0,       1, '2024-08-12 11:00:00', 'Looking for investment property in inner Melbourne.','pending',   '2024-08-12 11:00:00'),
('REF-3029', 3, 2, 'Marcus Bennett',    'm.bennett@vent.com',     '0411 111 003', 'New Farm',     'QLD', 'Refinance',      1200000.00,12000.00,3000.00, 1, '2024-07-10 09:00:00', 'Refinancing existing portfolio.',                    'settled',   '2024-07-10 09:00:00'),
('REF-3025', 3, 2, 'Elena Langford',    'e.langford@consult.com', '0411 111 004', 'Cottesloe',    'WA',  'Owner-Occupied', 520000.00, 0,       0,       1, '2024-06-05 08:00:00', 'First home buyer, pre-approval needed first.',       'declined',  '2024-06-05 08:00:00'),
('REF-3021', 3, 2, 'Tobias Hoffmann',   't.hoffmann@int.com',     '0411 111 005', 'Toorak',       'VIC', 'Investment',     2100000.00,21000.00,5250.00, 1, '2024-05-03 07:00:00', 'Established investor, looking for commercial deal.',  'settled',   '2024-05-03 07:00:00'),
('REF-3018', 4, 2, 'Priya Sharma',      'p.sharma@mail.com',      '0411 111 006', 'Surry Hills',  'NSW', 'Construction',   750000.00, 0,       0,       1, '2024-08-15 14:00:00', 'Building new home on existing block.',                'qualified', '2024-08-15 14:00:00'),
('REF-3015', 4, 2, 'David Nguyen',      'd.nguyen@mail.com',      '0411 111 007', 'Richmond',     'VIC', 'Investment',     490000.00, 4900.00, 980.00,  1, '2024-07-20 10:00:00', 'Adding to investment portfolio.',                     'settled',   '2024-07-20 10:00:00');

INSERT INTO commissions (referral_id, partner_id, broker_upfront, rate, amount, status, paid_at) VALUES
(1, 3, 8500.00,  25.00, 2125.00, 'paid',    '2024-09-01 00:00:00'),
(3, 3, 12000.00, 25.00, 3000.00, 'paid',    '2024-08-15 00:00:00'),
(5, 3, 21000.00, 25.00, 5250.00, 'pending', NULL),
(7, 4, 4900.00,  20.00, 980.00,  'pending', NULL);

INSERT INTO notifications (user_id, title, message, is_read) VALUES
(3, 'Referral Approved',      'Your referral REF-3042 for Jonathan Sterling has been approved.',       0),
(3, 'Commission Paid',        'Commission of $3,000.00 for REF-3029 (Marcus Bennett) has been paid.', 0),
(3, 'Referral Declined',      'Your referral REF-3025 for Elena Langford has been declined.',         1),
(3, 'New Referral Received',  'Your referral REF-3041 for Amara Rodriguez is under review.',          1);

INSERT INTO audit_log (user_id, user_name, action, entity_type, entity_id, description, ip_address) VALUES
(3, 'Alex Thompson', 'referral_submitted',   'referral', 1, 'Submitted referral REF-3042 for client Jonathan Sterling', '127.0.0.1'),
(2, 'James Broker',  'status_changed',       'referral', 1, 'Status changed from pending to approved for REF-3042',     '127.0.0.1'),
(1, 'Admin User',    'commission_paid',      'commission',1,'Marked commission $2,125.00 as paid for REF-3042',         '127.0.0.1'),
(3, 'Alex Thompson', 'referral_submitted',   'referral', 2, 'Submitted referral REF-3041 for client Amara Rodriguez',  '127.0.0.1'),
(1, 'Admin User',    'user_tier_changed',    'user',     4, 'Changed tier for Sarah Chen from Bronze to Silver',        '127.0.0.1'),
(1, 'Admin User',    'user_approved',        'user',     4, 'Approved partner account for Sarah Chen',                  '127.0.0.1');
