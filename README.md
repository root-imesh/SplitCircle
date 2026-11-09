# SplitCircle Classic — MySQL-connected edition

This is the **original Classic dashboard UI** integrated with a real PHP/MySQL backend. It uses only **HTML, CSS, vanilla JavaScript, PHP, and SQL** and runs on local XAMPP. The previous browser-only demo, profile photo upload feature, and circular initials avatars are removed. People are displayed by name only.

## Personal-only debt visibility (v5)

- **Debts** and **Dashboard → Outstanding Debts / Who Owes Whom** show only records where the signed-in user is either the debtor or the creditor. For example, Teshan ↔ Ravindu is visible to Teshan, but Ravindu ↔ Dilshan is not.
- The **PHP API** applies `WHERE (debtor_id = current_user_id OR creditor_id = current_user_id)` to debt and repayment queries, so third-party transactions do not reach the browser. Financial history is likewise restricted. Group membership and ordinary group-administration events are still visible to authorized group members.
- The old `group.php`, `debt.php`, and `history.php` pages receive the equivalent restrictions, including denying direct URL access to an unrelated debt.
- The **Remove Member** dialog no longer guesses another person's pending debts from the personal-only dataset; the server securely checks the complete group for unresolved obligations when the creator removes someone.
- This is a code-only update. **No SQL migration or reimport is required.** It is a display/access-control change; existing shared-group debts stay in MySQL and are visible to their own two participants.

## UI cleanup and mobile update (v4)

- Removed the entire **Upcoming Actions** dashboard panel (including the settlement reminder). Approval and repayment actions are still available from the **Requests** page and the notification bell.
- Resized the remaining group, debt-flow, activity and outstanding-debt cards into a wider two-column desktop dashboard, with the debt table spanning the width.
- Removed the decorative receipt illustration/tagline at the bottom of the sidebar, along with the nonessential dashboard subtitle and generic subtitles on secondary pages.
- Improved small-screen layout: slide-out navigation, two-column summary cards, one-column dashboard, wrapped activity text, scrollable debt tables, and a visible Account icon in the mobile top bar.
- This is **frontend-only**: no database migration required. It retains the original PHP/MySQL integration, real session identity, member removal and approval/repayment permissions.

## Applying this names-only UI update to an earlier Classic MySQL install

1. **Back up your current website folder** `C:\xampp\htdocs\SplitCircle_Classic_MySQL` before replacing it.
2. Replace the website files with this package (same folder name); keep your existing MySQL `splitcircle` database.
3. **No new SQL import or database migration is required for removing photos and initials.** If you previously imported the one-time repayment-note migration, do not re-import it.
4. Visit `http://localhost/SplitCircle_Classic_MySQL/` and refresh the browser. The JS/CSS URLs contain version tags so the browser does not keep displaying the old initials circles.

## Install on your existing XAMPP database (recommended)

**You already created/imported the `splitcircle` database in phpMyAdmin. Do not import `schema.sql` again and do not delete that database.**

1. Download and unzip the package. Copy its **entire `SplitCircle_Classic_MySQL` folder** into `C:\xampp\htdocs\`. The result should contain `C:\xampp\htdocs\SplitCircle_Classic_MySQL\index.php`, `api.php`, `classic.php`, `assets`, `database`, `includes` etc. This **does not overwrite** your existing `C:\xampp\htdocs\splitcircle` backend or `SplitCircle_Classic_UI` frontend.
2. In XAMPP start **Apache** and **MySQL**.
3. In phpMyAdmin open the existing **splitcircle** database. Select the **Import** tab and import **`database/upgrade_existing.sql` once**. It adds a nullable `note` column to `repayments` (keeps all existing records). **If the column already exists, do not run it again.** Check `repayments → Structure` to see whether the `note` column already exists.
4. Open `config/app.php` and verify the database settings. Default XAMPP values are DB host `127.0.0.1`, port `3306`, database `splitcircle`, username `root`, empty password. Change values only if your XAMPP setup differs.
5. Go to **http://localhost/SplitCircle_Classic_MySQL/**. The login page appears. Sign in with any previously registered SplitCircle user or one of the existing seeded demo users (`demo_alex`, `demo_ben`, etc. with the password from the old demo setup). If your database is empty, use **Register** to create accounts.
6. On login the **Classic dashboard** opens. All displayed balances, groups, debts, activity, and requests are loaded from MySQL. No records are loaded from localStorage.

### Install with a new database instead

In phpMyAdmin select **Import** and import `database/schema.sql` for a new database. The fresh schema already includes the repayment note column. **Do not** run `database/upgrade_existing.sql` on a fresh schema. Add users using **Register**, or optionally import `database/seed_demo.sql` **once** into an otherwise unseeded database.

## Current features and permissions

| Feature | MySQL behavior |
|---|---|
| Register, log in, log out, update display name/password | PHP sessions, password hashing, CSRF checks |
| Groups | Creator creates groups and invites **registered usernames**; invitee accepts/declines in Settings |
| Group members | Creator may remove non-creator members **only after unresolved debts/repayments are settled**. Historical transactions remain in the database. |
| Unlimited group members | No server-side five-member cap, following your newer decision (conflicts with the original university rubric; see below) |
| Debt request | Signed-in user is ALWAYS one party; user chooses "I owe someone" or "Someone owes me" and selects only the counterparty |
| Approve/reject debt | Only the **other involved member**, never the request author; pending debt never changes balances |
| Partial/full repayments | Only debtor can submit; only creditor can confirm/reject; amounts verified in DB transaction |
| CRUD of pending records | Creator may edit/cancel pending debts; debtor may edit/withdraw pending repayment claims; owner may edit/delete an empty group |
| Dashboard/history | Loaded from database; confirmed repayments alone reduce accepted debts |
| Profile pictures / initials avatars | **Not included**: no user photos, no upload/remove controls, and no letter-in-circle avatars; names are displayed as text only |

**Security:** The backend derives the actor ID from the authenticated PHP session. It does not accept impersonation fields such as `created_by`, arbitrary debtor/creditor IDs, or a client-selected signed-in account. All state-changing operations use POST+CSRF, prepared PDO SQL, server-side authorization, InnoDB transactions, and row locking for financial decisions.

## Files you'll use

```text
SplitCircle_Classic_MySQL/
├── index.php                     # Main entry: login redirect or Classic dashboard (replaces index.html)
├── classic.php                   # Original Classic dashboard HTML shell
├── api.php                       # Secure database read/write endpoint
├── login.php / register.php      # Real user accounts
├── assets/
│   ├── css/classic.css          # Classic dashboard styling
│   └── js/classic.js            # Browser interactivity; API calls, NOT localStorage
├── config/app.php                # MySQL settings; detects htdocs folder for base URL
├── includes/ and modules/       # Auth, PDO, shared SQL/money helpers
├── handlers/                    # Legacy PHP form handlers
└── database/
    ├── schema.sql                # New database setup
    ├── upgrade_existing.sql      # Existing database one-time migration
    └── seed_demo.sql             # Optional sample data
```

## Why is there no `index.html`?

The original HTML prototype used `index.html`, but this is now a PHP/MySQL website. `index.php` is the entry point: it redirects unauthenticated visitors to `login.php` and authenticated visitors to `classic.php`. `classic.php` renders the HTML shell and loads `assets/js/classic.js`, which requests live database data from `api.php`. `assets/css/classic.css` keeps the original design. **Do not rename `index.php` to `index.html`**; PHP sessions, CSRF protection, and the database integration require the PHP pages.

## Test the real approval flow

1. Log in as Teshan or any registered user; in **My Groups**, create a group and invite another existing username.
2. **Log out** and log in as the invited user. Go to **Settings** and accept the pending group invitation.
3. Log back in as Teshan. Click **Create Debt Request → Someone owes me**, select the group and the other member, and enter a positive amount.
4. Sign in as the other user. Open **Requests** and accept/reject the pending request. In the database, its `created_by` always remains Teshan's user ID; only the counterparty can decide.
5. For accepted debts, log in as the debtor and submit a repayment from **Debt Details**. The balance must remain unchanged until the creditor confirms it in **Requests**.
6. Try removing a member with an open debt in **My Groups**. It should be blocked. After resolving open debts, the creator can remove them and financial history stays intact.

## Known limitations and verification

- **This environment has PHP and Node.js but no MySQL server or PDO MySQL driver.** PHP syntax and JavaScript syntax can be checked here, but the actual SQL migration, DB connections, and full workflows **have not been run end-to-end on your Windows XAMPP machine**. Please run the checklist above and report the exact phpMyAdmin or browser errors, if any.
- This is a university/local-use project, not a regulated money movement or payment platform. It records claims; it does not send funds.
- **University requirement conflict:** the initial rubric specified **maximum five members per group**. Your newer request removed the limit, so this build has unlimited members. If the university still grades the original maximum-five rule, restore it on the server before submission.
- If your database already has a `note` column, do not run the migration; if you see a `Duplicate column name 'note'` error, the column is already present.
- If a fetch fails with `Database error`, check `C:\xampp\php\logs\php_error_log` or Apache PHP error logs, your database credentials, and the schema/migration.
- This is a teaching project. Review, test, back up, and harden before any public or production deployment.

## Troubleshooting

- **404:** Confirm `htdocs\SplitCircle_Classic_MySQL\index.php` exists. Use `http://localhost/SplitCircle_Classic_MySQL/` (not the browser-only `SplitCircle_Classic_UI` URL).
- **Database connection failed:** Start MySQL, verify `config/app.php`, verify database name `splitcircle`, import schema if missing and migration if old.
- **No groups:** The logged-in user has no active group memberships. Create a group or accept an invitation.
- **No incoming requests:** Only the counterparty sees an approval; the creator sees it as outgoing.
- **Account switching:** Log out and log in using the other person's real account. The insecure demo switcher has been removed.
- **Browser shows old UI:** Use Ctrl+Shift+R; verify you're on `SplitCircle_Classic_MySQL`, not `SplitCircle_Classic_UI`.

## UI update — search bar removal (9 Nov 2026)

The global header search bar and Ctrl+K shortcut have been removed. The header retains mobile navigation, notifications and the account menu. The Debts page shows the signed-in user's debts by default. This is a presentation-only change; it does not require a database migration.


## Typography update — Nov 9, 2026

This build applies the requested font set without changing app behavior:

- **Roboto** — text, forms and navigation.
- **Fredoka One** — SplitCircle logo, page headings and card headings.
- **Roboto Mono** — currency amounts and financial figures.
- **Font Awesome 5 Free / Brands** — optional icon CSS loaded from cdnjs. Existing outline SVG icons are intentionally retained for fidelity and offline fallback.

The fonts load via Google Fonts and cdnjs when online. If offline, the page uses Segoe UI/Arial, Roboto, or Consolas fallbacks. No font files are bundled.

To install, replace the previous `SplitCircle_Classic_MySQL` website folder in `C:\xampp\htdocs`, leaving the MySQL database untouched, then hard refresh the website. **No database migration required.**


## Navigation update (2026-11-09)

- The sign-in / register header now reuses the same interlocking-loop SplitCircle logo as the Classic dashboard. No extra image asset is required.
- **Profile** and **Settings** sit at the bottom of the desktop sidebar and the mobile navigation drawer.
- **Requests** now contains two top-level sections: **Debts** (incoming, outgoing, recent debt decisions / repayment confirmations) and **Group Req** (incoming group invitations, Accept / Decline).
- **Settings** contains only account/security information and logout. Group invitations were moved out.
- The request badge counts pending personal approvals **plus** incoming group invitations.
- Existing PHP session authentication, CSRF checks, database schema and server-side invitation approval rules remain unchanged. No SQL migration is needed.
