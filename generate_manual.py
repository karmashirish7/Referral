"""
Referral Network Portal — Manual Generator
Run:  pip install python-docx  (if not installed)
      python3 generate_manual.py
Output: MANUAL.docx in the same folder
"""

from docx import Document
from docx.shared import Pt, RGBColor, Inches, Cm
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml.ns import qn
import datetime

NAVY = RGBColor(0x1F, 0x38, 0x64)

doc = Document()

# ── Page margins ─────────────────────────────────────────────
for section in doc.sections:
    section.top_margin    = Cm(2)
    section.bottom_margin = Cm(2)
    section.left_margin   = Cm(2.5)
    section.right_margin  = Cm(2.5)

# ── Helpers ───────────────────────────────────────────────────
def heading1(text):
    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(18)
    p.paragraph_format.space_after  = Pt(6)
    run = p.add_run(text)
    run.bold      = True
    run.font.size = Pt(16)
    run.font.color.rgb = NAVY
    return p

def heading2(text):
    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(12)
    p.paragraph_format.space_after  = Pt(4)
    run = p.add_run(text)
    run.bold      = True
    run.font.size = Pt(13)
    run.font.color.rgb = NAVY
    return p

def body(text):
    p = doc.add_paragraph(text)
    p.paragraph_format.space_after = Pt(4)
    p.runs[0].font.size = Pt(11)
    return p

def bullet(text):
    p = doc.add_paragraph(style='List Bullet')
    run = p.add_run(text)
    run.font.size = Pt(11)
    p.paragraph_format.space_after = Pt(2)
    return p

def code(text):
    p = doc.add_paragraph()
    run = p.add_run(text)
    run.font.name = 'Courier New'
    run.font.size = Pt(9)
    run.font.color.rgb = RGBColor(0x0d, 0x6e, 0x6e)
    p.paragraph_format.left_indent = Inches(0.4)
    p.paragraph_format.space_after = Pt(2)
    return p

def note(text):
    p = doc.add_paragraph()
    p.paragraph_format.left_indent  = Inches(0.3)
    p.paragraph_format.space_after  = Pt(4)
    run = p.add_run("⚠ Note: " + text)
    run.font.size   = Pt(10)
    run.italic      = True
    run.font.color.rgb = RGBColor(0xd9, 0x77, 0x06)
    return p

def divider():
    doc.add_paragraph("─" * 80)

# ═══════════════════════════════════════════════════════════════
# TITLE PAGE
# ═══════════════════════════════════════════════════════════════
title = doc.add_paragraph()
title.alignment = WD_ALIGN_PARAGRAPH.CENTER
title.paragraph_format.space_before = Pt(40)
r = title.add_run("Referral Network Portal")
r.bold = True; r.font.size = Pt(26); r.font.color.rgb = NAVY

sub = doc.add_paragraph()
sub.alignment = WD_ALIGN_PARAGRAPH.CENTER
r2 = sub.add_run("Administrator & Developer Manual")
r2.font.size = Pt(14); r2.font.color.rgb = RGBColor(0x71, 0x80, 0x96)

doc.add_paragraph()
date_p = doc.add_paragraph()
date_p.alignment = WD_ALIGN_PARAGRAPH.CENTER
date_p.add_run(f"Generated: {datetime.date.today().strftime('%d %B %Y')}").font.size = Pt(10)

doc.add_page_break()

# ═══════════════════════════════════════════════════════════════
# TABLE OF CONTENTS (manual)
# ═══════════════════════════════════════════════════════════════
heading1("Table of Contents")
toc_items = [
    ("1", "Overview"),
    ("2", "System Requirements"),
    ("3", "Installation & Setup"),
    ("4", "Database Configuration"),
    ("5", "Default Login Accounts"),
    ("6", "File Structure"),
    ("7", "Changing the Primary Colour"),
    ("8", "Adding / Modifying Referral Statuses"),
    ("9", "Changing Commission Rates & Tiers"),
    ("10","Adding New Loan Types"),
    ("11","Changing Database Credentials"),
    ("12","User Role Management"),
    ("13","File Upload Settings"),
    ("14","Adding New Pages"),
    ("15","Email Notifications"),
    ("16","Compliance Notes (Privacy Act 1988)"),
    ("17","Troubleshooting"),
]
for num, title_text in toc_items:
    p = doc.add_paragraph()
    p.paragraph_format.space_after = Pt(2)
    p.add_run(f"{num}.  {title_text}").font.size = Pt(11)

doc.add_page_break()

# ═══════════════════════════════════════════════════════════════
# 1. OVERVIEW
# ═══════════════════════════════════════════════════════════════
heading1("1. Overview")
body("The Referral Network Portal is a PHP + MySQL web application for managing partner referrals in the Australian mortgage and real estate sector. It replaces manual spreadsheets and email tracking with a centralised digital system.")
body("Built for the Industry Capstone Project, the portal supports four user roles, commission-tier calculations, a broker Kanban pipeline, and a full audit trail for Privacy Act 1988 compliance.")

heading2("Key Features")
for f in [
    "Partner dashboard with live stats and monthly chart",
    "Referral submission form with client consent (Privacy Act 1988)",
    "6-stage referral tracking: Pending → Qualified → Lodged → Approved → Settled → Declined",
    "Automatic commission calculation: Broker Upfront × Tier Rate (Gold 25%, Silver 20%, Bronze 15%)",
    "Broker Kanban pipeline view",
    "Admin panel: approve users, set tiers, override commissions",
    "Auditor view: full read-only audit log (CSV export)",
    "In-app notifications + CSV export for referrals and audit log",
    "File upload (PDF, DOCX, XLSX) per referral",
]:
    bullet(f)

# ═══════════════════════════════════════════════════════════════
# 2. SYSTEM REQUIREMENTS
# ═══════════════════════════════════════════════════════════════
doc.add_page_break()
heading1("2. System Requirements")
for req in [
    "PHP 8.1 or higher (PHP 8.2 recommended)",
    "MySQL 8.0 or higher (or MariaDB 10.6+)",
    "Apache or Nginx web server (with mod_rewrite for Apache)",
    "A local server like XAMPP, MAMP, Laragon, or WAMP (Windows)",
    "Web browser: Chrome, Firefox, Safari, or Edge (latest versions)",
]:
    bullet(req)
note("XAMPP is the easiest option for local development. Download from https://www.apachefriends.org")

# ═══════════════════════════════════════════════════════════════
# 3. INSTALLATION & SETUP
# ═══════════════════════════════════════════════════════════════
doc.add_page_break()
heading1("3. Installation & Setup")

heading2("Step 1 — Copy Files")
body("Copy the entire project folder into your web server's document root:")
code("XAMPP (Windows):  C:\\xampp\\htdocs\\REFERRAL\\")
code("XAMPP (Mac):      /Applications/XAMPP/htdocs/REFERRAL/")
code("MAMP (Mac):       /Applications/MAMP/htdocs/REFERRAL/")

heading2("Step 2 — Create the Database")
body("Open phpMyAdmin (http://localhost/phpmyadmin) and run the SQL file:")
code("File:  REFERRAL/database.sql")
body("Or via command line:")
code("mysql -u root -p < database.sql")

heading2("Step 3 — Configure Database Credentials")
body("Open includes/db.php and update your MySQL credentials (see Section 11).")

heading2("Step 4 — Set Uploads Folder Permissions")
body("Make sure the uploads/ folder is writable by the web server:")
code("chmod 775 uploads/    (Linux/Mac)")
body("On Windows with XAMPP, this is automatic.")

heading2("Step 5 — Visit the Application")
body("Open your browser and go to:")
code("http://localhost/REFERRAL/")
body("The login page will appear. Use the default accounts in Section 5.")

# ═══════════════════════════════════════════════════════════════
# 4. DATABASE CONFIGURATION
# ═══════════════════════════════════════════════════════════════
doc.add_page_break()
heading1("4. Database Configuration")
body("The database schema is in database.sql. It creates 6 tables:")

rows = [
    ["users",        "All user accounts (partner, broker, admin, auditor)"],
    ["referrals",    "All referral records with client details and status"],
    ["commissions",  "Commission records created when a referral is settled"],
    ["documents",    "Uploaded files linked to referrals"],
    ["notifications","In-app notification messages per user"],
    ["audit_log",    "Full audit trail of every system action"],
]
table = doc.add_table(rows=1, cols=2)
table.style = 'Table Grid'
hdr = table.rows[0].cells
hdr[0].text = "Table"; hdr[1].text = "Purpose"
for row in rows:
    r = table.add_row().cells
    r[0].text = row[0]; r[1].text = row[1]

# ═══════════════════════════════════════════════════════════════
# 5. DEFAULT LOGIN ACCOUNTS
# ═══════════════════════════════════════════════════════════════
doc.add_page_break()
heading1("5. Default Login Accounts")
note("All default passwords are:  password   Change them immediately in production!")

accounts = [
    ["Admin User",    "admin@networkportal.com",  "admin",   "Full system access"],
    ["James Broker",  "broker@networkportal.com", "broker",  "Pipeline, status updates"],
    ["Alex Thompson", "alex@partner.com",         "partner", "Submit referrals, view commissions"],
    ["Lisa Audit",    "auditor@networkportal.com","auditor", "Read-only audit log"],
]
table2 = doc.add_table(rows=1, cols=4)
table2.style = 'Table Grid'
hdr2 = table2.rows[0].cells
for i, h in enumerate(["Name","Email","Role","Access"]):
    hdr2[i].text = h
for acc in accounts:
    r = table2.add_row().cells
    for i, v in enumerate(acc): r[i].text = v

heading2("How to Change a Password via phpMyAdmin")
body("1. Open phpMyAdmin → referral_portal → users table.")
body("2. Click Edit on the user row.")
body("3. Find the password column. Replace the hash with a new bcrypt hash.")
body("Generate a new bcrypt hash with PHP:")
code("<?php echo password_hash('your_new_password', PASSWORD_DEFAULT); ?>")
body("Paste the output (the long $2y$... string) into the password column and save.")

# ═══════════════════════════════════════════════════════════════
# 6. FILE STRUCTURE
# ═══════════════════════════════════════════════════════════════
doc.add_page_break()
heading1("6. File Structure")
files = [
    ("index.php",              "Login page"),
    ("register.php",           "New account registration"),
    ("logout.php",             "Destroys session, redirects to login"),
    ("dashboard.php",          "Partner dashboard with stats and chart"),
    ("referrals.php",          "Referral list with filters and CSV export"),
    ("submit-referral.php",    "New referral submission form"),
    ("pipeline.php",           "Broker Kanban pipeline (6 columns)"),
    ("admin-users.php",        "Admin: approve/suspend users, set tier"),
    ("admin-commissions.php",  "Admin: mark commissions paid, override amounts"),
    ("audit.php",              "Admin/Auditor: full audit log with filters"),
    ("documents.php",          "View and upload referral documents"),
    ("settings.php",           "Update profile, change password"),
    ("notifications.php",      "View all in-app notifications"),
    ("database.sql",           "MySQL schema and sample data"),
    ("includes/db.php",        "Database connection — EDIT THIS for credentials"),
    ("includes/auth.php",      "Session helpers (isLoggedIn, requireRole, etc.)"),
    ("includes/functions.php", "Helper functions (commission calc, badges, audit log)"),
    ("includes/sidebar.php",   "Role-aware left sidebar component"),
    ("assets/css/style.css",   "All custom styles"),
    ("assets/js/main.js",      "JavaScript: notifications, file upload, confirmations"),
    ("uploads/",               "Uploaded documents stored here (writable)"),
]
table3 = doc.add_table(rows=1, cols=2)
table3.style = 'Table Grid'
h = table3.rows[0].cells; h[0].text = "File / Folder"; h[1].text = "Purpose"
for f, d in files:
    r = table3.add_row().cells; r[0].text = f; r[1].text = d

# ═══════════════════════════════════════════════════════════════
# 7. CHANGING THE PRIMARY COLOUR
# ═══════════════════════════════════════════════════════════════
doc.add_page_break()
heading1("7. Changing the Primary Colour")
body("The portal uses dark navy #1F3864 as its primary colour (from the project spec). To change it, open assets/css/style.css and edit the CSS variables at the top of the file:")
code(":root {")
code("    --navy:      #1F3864;   /* main sidebar + buttons */")
code("    --navy-dark: #172d55;   /* button hover */")
code("    --navy-light:#2a4a7f;   /* active nav item */")
code("}")
body("Change all three hex values to your desired colour. Save the file and refresh the browser.")
note("The colour also appears inline in dashboard.php Chart.js config (backgroundColor: '#1F3864'). Update that too if you change the brand colour.")

# ═══════════════════════════════════════════════════════════════
# 8. ADDING / MODIFYING REFERRAL STATUSES
# ═══════════════════════════════════════════════════════════════
doc.add_page_break()
heading1("8. Adding / Modifying Referral Statuses")
body("Referral statuses are defined in three places. You must update all three consistently.")

heading2("8a. database.sql — ENUM column")
body("Open database.sql and find the referrals table definition. Edit the ENUM list:")
code("status ENUM('pending','qualified','lodged','approved','settled','declined')")
body("Add your new status inside the quotes, separated by commas, then re-run the SQL or run an ALTER TABLE command:")
code("ALTER TABLE referrals MODIFY status ENUM('pending','qualified','lodged','approved','settled','declined','on_hold');")

heading2("8b. includes/functions.php — statusBadge()")
body("Find the $map array in the statusBadge() function and add your new status with a CSS class:")
code("$map = [")
code("    'on_hold' => 'badge-lodged',   // reuse an existing colour")
code("];")

heading2("8c. assets/css/style.css — new badge class (optional)")
body("If you want a unique colour for the new status, add a CSS class in the STATUS BADGES section:")
code(".badge-on_hold { background: #e0f2fe; color: #0369a1; }")

heading2("8d. Update dropdowns in referrals.php and pipeline.php")
body("Search for the hardcoded status option lists in referrals.php and pipeline.php and add the new status option.")

# ═══════════════════════════════════════════════════════════════
# 9. CHANGING COMMISSION RATES & TIERS
# ═══════════════════════════════════════════════════════════════
doc.add_page_break()
heading1("9. Changing Commission Rates & Tiers")
body("The commission formula is:  Partner Commission = Broker Upfront × (Tier Rate / 100)")

heading2("9a. Default rates per tier")
body("The default rates are defined in three places:")
code("admin-users.php  →  const tierRates = {Gold:25, Silver:20, Bronze:15};  (JS modal)")
code("register.php     →  $rate = ['Gold'=>25,'Silver'=>20,'Bronze'=>15][$tier] ?? 20;")
code("admin-users.php  →  $rate = ['Gold'=>25,'Silver'=>20,'Bronze'=>15][$tier] ?? 20;  (PHP)")
body("Update all three with the same values to keep them in sync.")

heading2("9b. Changing a single user's rate")
body("Log in as Admin → Users → click 'Edit Tier' next to a partner. Enter a custom rate in the 'Custom Commission Rate %' field. This overrides the tier default for that user only.")

heading2("9c. Commission formula location")
body("The calculation is in includes/functions.php, function calculateCommission():")
code("function calculateCommission($brokerUpfront, $tierRate) {")
code("    return round($brokerUpfront * ($tierRate / 100), 2);")
code("}")
body("Modify this function if the formula needs to change (e.g. add a cap or a flat fee).")

# ═══════════════════════════════════════════════════════════════
# 10. ADDING NEW LOAN TYPES
# ═══════════════════════════════════════════════════════════════
doc.add_page_break()
heading1("10. Adding New Loan Types")
body("Loan types are stored as an ENUM in the database and as PHP arrays in the forms.")

heading2("Step 1 — Update the database ENUM")
code("ALTER TABLE referrals MODIFY loan_type ENUM('Owner-Occupied','Investment','Refinance','Commercial','Construction','SMSF');")

heading2("Step 2 — Update submit-referral.php")
body("Find the $loanTypes array near the top of the file:")
code("$loanTypes = ['Owner-Occupied','Investment','Refinance','Commercial','Construction'];")
body("Add your new type to the array:")
code("$loanTypes = ['Owner-Occupied','Investment','Refinance','Commercial','Construction','SMSF'];")

# ═══════════════════════════════════════════════════════════════
# 11. CHANGING DATABASE CREDENTIALS
# ═══════════════════════════════════════════════════════════════
doc.add_page_break()
heading1("11. Changing Database Credentials")
body("Open includes/db.php. The first four lines are the only thing you need to change:")
code("define('DB_HOST', 'localhost');   // usually 'localhost'")
code("define('DB_USER', 'root');        // your MySQL username")
code("define('DB_PASS', '');            // your MySQL password")
code("define('DB_NAME', 'referral_portal'); // database name")
note("Never commit db.php with real credentials to a public git repository.")

# ═══════════════════════════════════════════════════════════════
# 12. USER ROLE MANAGEMENT
# ═══════════════════════════════════════════════════════════════
doc.add_page_break()
heading1("12. User Role Management")
body("Roles are fixed to: partner, broker, admin, auditor.")

heading2("Role Permissions Summary")
role_rows = [
    ["Partner",  "Submit referrals, view own referrals, view own commissions and payouts, upload documents"],
    ["Broker",   "View/update all referral statuses, enter broker upfront commission, Kanban pipeline, add notes"],
    ["Admin",    "Everything above + approve/suspend users, change tiers, override commissions, audit log"],
    ["Auditor",  "Read-only: audit log, all referrals (no edit access)"],
]
t = doc.add_table(rows=1, cols=2); t.style = 'Table Grid'
h = t.rows[0].cells; h[0].text = "Role"; h[1].text = "Permissions"
for row in role_rows:
    r = t.add_row().cells; r[0].text = row[0]; r[1].text = row[1]

heading2("How to change a user's role")
body("Via phpMyAdmin: edit the user row and change the 'role' column value. Then update the 'status' to 'active' if needed.")
note("There is no admin UI for changing roles — use phpMyAdmin directly. This is intentional to prevent accidental privilege escalation.")

# ═══════════════════════════════════════════════════════════════
# 13. FILE UPLOAD SETTINGS
# ═══════════════════════════════════════════════════════════════
doc.add_page_break()
heading1("13. File Upload Settings")

heading2("Allowed File Types")
body("Currently allowed: PDF, DOCX, DOC, XLSX, XLS. To change, edit the $allowed array in submit-referral.php and documents.php:")
code("$allowed = ['pdf','docx','doc','xlsx','xls'];")

heading2("Maximum File Size")
body("The app enforces 10MB. Change the $maxSize variable in submit-referral.php:")
code("$maxSize = 10 * 1024 * 1024; // 10MB — change 10 to desired MB")
note("PHP itself has upload limits in php.ini. You may also need to change upload_max_filesize and post_max_size in php.ini if you raise the limit.")

heading2("Upload Folder")
body("All uploads go to the uploads/ folder in the project root. To change location, find move_uploaded_file() calls in submit-referral.php and documents.php and update the destination path.")

# ═══════════════════════════════════════════════════════════════
# 14. ADDING NEW PAGES
# ═══════════════════════════════════════════════════════════════
doc.add_page_break()
heading1("14. Adding New Pages")
body("Every new page follows the same template. Copy this skeleton into a new .php file:")
code("<?php")
code("require_once 'includes/db.php';")
code("require_once 'includes/auth.php';")
code("require_once 'includes/functions.php';")
code("requireRole('partner'); // or requireLogin() for any role")
code("")
code("$user   = currentUser();")
code("$uid    = currentUserId();")
code("$unread = unreadNotificationCount($pdo, $uid);")
code("?>")
code("<!DOCTYPE html>")
code("<html lang=\"en\">")
code("<head>")
code("    <link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css\">")
code("    <link rel=\"stylesheet\" href=\"assets/css/style.css\">")
code("</head>")
code("<body>")
code("<div class=\"app-wrapper\">")
code("    <?php include 'includes/sidebar.php'; ?>")
code("    <div class=\"main-content\">")
code("        <!-- top-header goes here -->")
code("        <div class=\"page-body\">")
code("            <div class=\"page-heading\">")
code("                <div><h1>Page Title</h1></div>")
code("            </div>")
code("            <!-- Your content here -->")
code("        </div>")
code("    </div>")
code("</div>")
code("<script src=\"assets/js/main.js\"></script>")
code("</body></html>")

heading2("Adding a new sidebar link")
body("Open includes/sidebar.php. Find the section matching the role you want to add the link to, then add a new line:")
code("<?= navItem('your-page.php', 'icon-name', 'Link Label', 'your-page.php', $page) ?>")
body("The second argument is a Bootstrap Icons icon name (without 'bi-'). Browse icons at https://icons.getbootstrap.com")

# ═══════════════════════════════════════════════════════════════
# 15. EMAIL NOTIFICATIONS
# ═══════════════════════════════════════════════════════════════
doc.add_page_break()
heading1("15. Email Notifications")
body("The current system uses in-app notifications only. To add real email sending, integrate PHP Mailer or a transactional email service (e.g. Mailgun, SendGrid).")

heading2("Where to add email sending")
body("In includes/functions.php, the addNotification() function is called whenever a notification is created. Add your email sending code there:")
code("function addNotification($pdo, $userId, $title, $message) {")
code("    // existing DB insert...")
code("    ")
code("    // Add email sending here:")
code("    // $userEmail = ...fetch from DB...")
code("    // mail($userEmail, $title, $message);")
code("    // or use PHPMailer / an SMTP service")
code("}")
note("Only send emails to users where consent_email = 1 (Spam Act 2003 compliance).")

# ═══════════════════════════════════════════════════════════════
# 16. COMPLIANCE NOTES
# ═══════════════════════════════════════════════════════════════
doc.add_page_break()
heading1("16. Compliance Notes (Privacy Act 1988 / Spam Act 2003)")

heading2("Client Consent")
body("Every referral submission requires a consent checkbox. The consent timestamp (consent_timestamp column) is stored in the database for audit purposes. Do not remove this field.")

heading2("Audit Trail")
body("Every significant action is logged to the audit_log table via the logAction() function in includes/functions.php. The audit log is accessible to admin and auditor roles at audit.php.")

heading2("Data Retention")
body("The Privacy Act 1988 requires client data to be retained for 7 years. The database does not auto-delete records. Implement a cron job or manual process to anonymise records older than 7 years.")

heading2("Email Opt-Out")
body("Users can unsubscribe from email notifications via Settings → uncheck 'Receive email notifications'. This sets consent_email = 0 in the users table.")

heading2("Password Security")
body("All passwords are stored as bcrypt hashes using PHP's password_hash(PASSWORD_DEFAULT). Plain text passwords are never stored.")

# ═══════════════════════════════════════════════════════════════
# 17. TROUBLESHOOTING
# ═══════════════════════════════════════════════════════════════
doc.add_page_break()
heading1("17. Troubleshooting")

problems = [
    (
        "White screen / blank page",
        "Enable PHP error reporting. Add to the top of the file:\n  ini_set('display_errors', 1); error_reporting(E_ALL);\nOr check the Apache error log."
    ),
    (
        "Database connection failed",
        "Check includes/db.php — verify DB_HOST, DB_USER, DB_PASS, DB_NAME match your MySQL setup. Make sure MySQL is running (check XAMPP control panel)."
    ),
    (
        "'Pending admin approval' on login",
        "The new account has status='pending'. Log in as admin and go to admin-users.php to approve it."
    ),
    (
        "File upload not working",
        "Check that the uploads/ folder exists and is writable (chmod 775). Check PHP's upload_max_filesize in php.ini (should be at least 10M)."
    ),
    (
        "Commission not calculated on settle",
        "The broker must enter the Broker Upfront Commission amount in the modal when changing status to 'settled'. Without that value, no commission is created."
    ),
    (
        "Dashboard chart not showing",
        "Chart.js is loaded from CDN. Check your internet connection, or download chart.umd.min.js and serve it locally from assets/js/."
    ),
    (
        "Session expires immediately",
        "Check that PHP sessions are configured. Increase session.gc_maxlifetime in php.ini. You can also add session_set_cookie_params() before session_start() in auth.php."
    ),
    (
        "Icons not showing (just text)",
        "Bootstrap Icons is loaded from CDN. Check internet connection or download the icon font and serve it from assets/css/."
    ),
]
for prob, sol in problems:
    heading2("Problem: " + prob)
    body("Solution: " + sol)

# ═══════════════════════════════════════════════════════════════
# Footer
# ═══════════════════════════════════════════════════════════════
doc.add_page_break()
p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = p.add_run("Referral Network Portal — Confidential Developer Manual")
r.font.size = Pt(10); r.font.color.rgb = RGBColor(0x71,0x80,0x96)
doc.add_paragraph()
p2 = doc.add_paragraph()
p2.alignment = WD_ALIGN_PARAGRAPH.CENTER
p2.add_run(f"© {datetime.date.today().year} Network Portal. For professional use only.").font.size = Pt(9)

# Save
output = "MANUAL.docx"
doc.save(output)
print(f"✅  Manual saved to {output}")
