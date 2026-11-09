# SplitCircle — Navigation & logo update

Updated: 2026-11-09

## Changes

1. The sign-in / register page and all legacy PHP pages use the dashboard's two-loop SplitCircle symbol instead of the old placeholder symbol. This is drawn with CSS (no external image).
2. On the Classic dashboard, **Profile** and **Settings** are pinned to the bottom of the desktop sidebar and mobile menu.
3. **Requests** contains **Debts** and **Group Req** tabs.
   - **Debts** retains Incoming Requests, Outgoing Requests, Recent Decisions, and repayment confirmations.
   - **Group Req** displays invitations addressed to the signed-in user and uses the existing accept/decline backend actions.
4. Group invitations are absent from Settings. Settings shows account/security details and logout only.
5. Pending Requests badges show the combined number of incoming debt/repayment approvals and group invitations.

## Installation

Back up your old `C:\xampp\htdocs\SplitCircle_Classic_MySQL` folder, then replace it with this folder. Do not drop or re-import the `splitcircle` MySQL database. Apache and MySQL must be started in XAMPP. Visit `http://localhost/SplitCircle_Classic_MySQL/` and hard-refresh with Ctrl+Shift+R.

## Verification

- PHP syntax checks passed for all PHP files.
- JavaScript syntax check passed.
- Financial money/status unit tests passed.
- Desktop and mobile browser checks passed using a mocked API, including group-invitation POST action and active navigation.
- The login page was rendered using PHP CLI and inspected in a browser; the logo mark matches the classic dashboard's CSS shape.
- The complete application has **not** been end-to-end tested against a live MySQL server here. Existing secure PHP session, CSRF, and database logic is unchanged.
