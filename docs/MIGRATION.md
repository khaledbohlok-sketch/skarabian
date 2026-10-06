# Bringing the old portal data across

The old "Finance & Management System" database is copied into the new system with a **preview first**. Nothing is
imported until the Owner has read the migration report and confirmed.

## What is imported

| Old data | New system | Clean-up done on the way |
|---|---|---|
| Roles and users | Users with the new default roles | "Onwer" → Owner; "Programmer" → Technical Support (no finance/HR access). **Old passwords are not copied**: every account is created *waiting for Owner approval* and must set a new password and 2FA at first login. Users already created in the new system (same email or username) are linked, not duplicated. |
| Horses (≈24) | Horses | Duplicate spellings merged into one record; category recalculated from age and sex (Foal / Colt / Filly / Stallion / Mare / Gelding); breed from the list ("Arabian Breed" → Purebred Arabian); sire and dam linked to real horse records — unknown parents are added as *external* (pedigree-only) horses. Sold / transferred horses are archived (the new system keeps no sales). |
| Embryos (≈13) | Embryos | Donor mare and sire linked to horse records, including misspelled names (AJ RAZENAH → AJ RAZNEH, SG SHAMMA / SG SHAMA → SG SHAMMAH, BADOOR AL BAHIYA → BDOOR AL BAYHA); an embryo with no dam gets a clearly named placeholder to fix; "Date of Birth" becomes **Expected Foaling**; storage location taken from the notes ("Stored in Stable 2" → Stable 2); recipient mare linked; codes renumbered EMB-YYYY-NNN (old code kept in notes); pregnant transfers get a breeding record on the recipient mare. |
| Employees (≈16) | Employees | Phone numbers found in the Position field moved to Phone; QID / passport / bank numbers encrypted; expired QIDs listed for follow-up. |
| Categories | Finance categories | Matched to the new list; typos corrected ("Employees Expenese" → Employee Expenses, "Puplic" → Public). |
| Bills (≈240) | Bills + payments | **Amounts converted to QAR, 2 decimals** (see below); new numbers BILL-YYYYMM-NNNN with the old number kept as reference; supplier/client names turned into Clients & Suppliers (similar spellings merged); paid bills get a payment record so account balances work; unpaid bills past their due date become Overdue; old "pending" bills over the approval limit wait for Owner/GM approval. |
| Inventories (6) and items (≈48) | Inventories, items | Quantity entered as opening stock with a movement record; expiry dates and minimum quantities kept. |
| Currencies and rates | Checked, not copied | The old rates are listed in the report with the correct value (QAR base). |

Any old column that has no place in the new system is written into the record's **notes** ("Old system — column: value"),
so nothing is silently lost. Old tables that are not imported (e.g. the old activity log) are listed in the report.

### How old amounts become QAR

For each bill, in this order:

1. A QAR amount column exists → used as is.
2. Original amount in **QAR** → used as is (rounded to 2 decimals, e.g. 4,604.31985 → 4,604.32).
3. Original amount in **USD** → × 3.64 (the QAR peg).
4. Other currency → × the rate saved on that bill. The old system stored some rates as "USD" that were really QAR values
   (1 GBP = 4.73, 1 KWD = 12.18…); the importer recognises which kind each rate is and converts correctly. If a saved rate
   fits neither, today's correct rate is used and the line is flagged.
5. Only a USD amount stored → × 3.64.

Every conversion is listed in the report (`Converted`) with the calculation.

## How it finds the old data

The layout of the old database is not documented, so the importer looks for each kind of record by **table and column
names** (for example horses in `horses` / `horse` / `tbl_horses`, the name in `horse_name` / `name` / `name_en`…).
The first lines of the report (*Old tables*) show exactly which old table and columns were used for what.

If something was not found or matched wrongly, copy `migration/mapping.sample.php` to `migration/mapping.php` and name the
table or column there, then run the preview again. With Terminal, `php tools/import_old.php --schema` prints the
mapping without importing.

## Steps

1. **Point to the old database.** Same cPanel account: add the existing database user to the old database
   (read access is enough) and put its details in `config.php`:

   ```php
   'old_db' => ['host' => 'localhost', 'name' => 'cpuser_oldportal', 'user' => 'cpuser_ska', 'pass' => '...'],
   ```

   The old database is only read; the importer never writes to it.

2. **Preview.** Owner → **Administration → Migration report → Run preview** (or `php tools/import_old.php`).
   The whole import runs and is then undone; only the report is kept. Nothing in the new database changes.

3. **Review.** Filter the report by type. Lines marked **Check** need a decision (e.g. a similar name that may or may not be
   the same mare, a bill pointing to a horse that does not exist). Mark lines as reviewed as you go.
   If something must be fixed in the mapping or the old data, fix it and run the preview again.

4. **Import.** When the latest run is a reviewed preview, type `IMPORT` in the box and press **Import now**
   (or `php tools/import_old.php --commit`). The report of the real import is kept under its own run.

5. **After the import:**
   - **Approvals**: approve the imported users you want to keep, and the old pending bills.
   - **Users**: check each account's role; deactivate anyone who has left.
   - **Finance → Accounts**: set the opening balances of the Cash and Bank accounts.
   - **Embryos**: replace any "UNKNOWN DAM …" placeholder with the right mare.
   - **Website → Horses on website**: choose the public horses and add photos.
   - Archive duplicate horses the report flagged as "similar names" if they are the same horse.

Running the import again is safe: records already imported are skipped (table `import_map`).

## Trying it without the real data

`tests/fixtures/old_system_sample.sql` is an **invented example** of an old database with every problem listed in the
specification. To try the import on a test system:

```bash
mysql -e "CREATE DATABASE skold"; mysql skold < tests/fixtures/old_system_sample.sql
php tools/import_old.php --db=skold --user=... --pass=...          # preview
php tools/import_old.php --db=skold --user=... --pass=... --commit # real import
```
