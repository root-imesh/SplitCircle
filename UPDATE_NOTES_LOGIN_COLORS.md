# SplitCircle — Login page color and footer update

Changes made:
- Sign in and Create account primary buttons use the same teal gradient as the Classic dashboard primary buttons (`#05a694` → `#007c72`).
- Auth form link and focus accents use matching teal.
- Footer text is now exactly: `Null Society . Financial Records Manage Assistant`.
- CSS cache version bumped to `v=9` so changes show after deployment.

No database or backend logic was changed. Existing MySQL records are preserved.

## Install

1. Back up the existing `C:\xampp\htdocs\SplitCircle_Classic_MySQL` folder.
2. Extract the project ZIP to `C:\xampp\htdocs\`, replacing the existing website folder.
3. Reload `http://localhost/SplitCircle_Classic_MySQL/login.php` using Ctrl+Shift+R.
4. No SQL import is required.
