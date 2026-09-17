# Installation directory

The installation wizard lives in the application itself (`/install`), not in
this folder — there is no PHP to delete here.

Installation state is tracked by two files:

| File | Purpose |
|---|---|
| `storage/installed.lock` | Written when installation completes. While it exists the installer refuses to run again and `/install` redirects to the home page. |
| `install/.installed` | A human-readable copy of the same record (version, date, PHP version). |

To reinstall on a throwaway environment, delete `storage/installed.lock` and
drop the database. **Never do this on a live site** — it would let a visitor
walk through the wizard and take over the installation.
