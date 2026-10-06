# SK Arabians Management System — user guide

Portal address: **https://skarabian.com/portal** (staff login link at the bottom of the website).
Each person has their own login. Never share it.

---

## Everyone

**Logging in.** Username or email + password. If your role needs 2-factor authentication, enter the 6-digit code from
your authenticator app (or the code emailed to you). After 3 wrong passwords a picture code appears; after 5 the account
is locked for 15 minutes and the Owner is told.

**First login.** You must choose a new password (10+ characters, upper and lower case, a number and a symbol), and set up
2FA if your role requires it: scan the QR code with Google/Microsoft Authenticator and type the code.

**On your phone.** Open the portal in Chrome (Android) or Safari (iPhone) → *Add to Home Screen*. It then opens like an app.

**Language.** The عربي / EN button at the top switches the whole portal (Arabic is right-to-left).

**Search.** The search bar at the top finds horses, embryos, staff, bills, suppliers and documents.

**Notifications.** The bell shows approvals, reminders and alerts meant for you. Important ones are also emailed.

**My account** (your name, top right): change password, 2FA, see where you are logged in, log out of all devices.

**My HR** (if your login is linked to your employee record): leave balance, request or cancel leave, your payslips and
HR letters to print, this month's attendance and overtime, your advances and loans, your document expiry dates.

**Lists.** Every list has search and filters (on a phone, tap *Filters*), sorting by column, pages, and — if your role
allows — export to Excel and print/PDF. An empty filter always means "show everything".

**Picking a horse, person or supplier.** Start typing in the box and choose from the list. If it is not there, use
*+ Add new* — names are never typed freely, so there are no spelling duplicates.

**Deleting.** Deleted items go to the Trash; only the Owner can restore or empty it. Records linked to others cannot be
deleted — archive them instead. Staff can edit their own entries for 24 hours; after that a manager edits.

---

## Owner

Everything below, plus:

- **Dashboard**: horses, pregnant mares, embryos, staff, money this month (approved and pending shown separately),
  cash, payroll due, alerts (expired / expiring documents, vet and farrier due, foalings, overdue bills, low stock,
  budgets) and *Waiting for my approval*.
- **Approvals**: approve or reject with one tap (bills over the limit, payroll, purchase orders, deleting financial
  records, new users, foals on the website, leave). Nobody can approve their own entry.
- **Daily summary** every morning by email (and WhatsApp if set up); **weekly security report** on Sundays.
- **Administration** (Owner only unless noted):
  - *Users*: create accounts (temporary password shown once), approve new users, unlock, reset password or 2FA,
    deactivate, give temporary extra access with an end date (e.g. while someone is on leave).
  - *Roles & permissions*: tick what each role may view, create, edit, delete, approve, export, print, and see sensitive
    data; 2FA, horse scope, 24-hour edit rule, allowed country, business hours. Create custom roles.
  - *Settings*: approval limit, company details, daily summary; *Scheduled tasks* status.
  - *Security settings*: failed logins, locked accounts, log out everyone.
  - *Active sessions*, *Backups* (back up now, download, test), *Trash*, *Activity log* (everything anyone did, with
    old and new values; *Check log integrity*), *Migration report*.
- **Website**: what the public sees (see Website Editor).

## General Manager

All modules like the Owner, and approves bills and payroll. No roles, backups, Trash or security settings.

## Accountant

- **Finance → Bills**: *New* → type (income / expense / liability), category, supplier or client, amount and currency
  (the QAR value is calculated with the day's rate and saved), dates, receipt photo/PDF, link to a horse, embryo,
  employee or item. Bills over the approval limit go to the Owner/GM. Record partial or full payments on the bill.
  Unpaid bills past their due date become *Overdue* automatically.
- **Accounts**: cash, bank, petty cash with opening balances; transfers between accounts.
- **Clients & suppliers**: everything linked to each, and their balance.
- **Invoices** (boarding and other) and **receipts**, printed on the letterhead from SK Arabian Studio.
- **Purchase orders**: create → approval → *Goods received* adds the stock and creates the supplier bill.
- **Payroll**: *New run* for the month → check each line (overtime, absences, unpaid leave and advances are calculated) →
  send for approval → pay all or selected employees from an account. Payslips are created automatically.
- **Budgets** per category, monthly or yearly; you are warned when one is exceeded.
- **Reports**: profit & loss, expenses by category / supplier / horse, cost per horse, cash flow, balances, payroll,
  receivables and payables aging. Approved, pending and total are always shown separately. Excel or print/PDF.
- Horses and employees are read-only for you, without HR sensitive fields.

## HR Officer

- **Employees**: profile, photo, position from the list, phone, QID / passport / visa / health card numbers and expiry
  dates (expired in red, within 60 days in amber), salary and bank details (sensitive), documents, assigned horses,
  *show on website* with a public bio.
- **Attendance**: daily sheet for everyone (present, absent, late, leave, sick, holiday, overtime hours).
- **Leave**: requests from staff arrive for approval; approved annual leave reduces the balance.
- **Loans & advances** with a monthly deduction taken in payroll.
- **SK Arabian Studio → HR documents**: salary certificate, offer letter, experience and other letters, ID cards,
  staff list. They are filled from the employee record and saved on the employee's Documents tab.
- When someone leaves: set status *Left* — their login is deactivated at once.
- Payroll is view-only for you.

## Veterinarian

- **Horses → Health**: vaccinations, vet visits, deworming, dental, farrier, treatments. Choose the medicine from the
  clinic stock: the stock goes down and the cost is added to the horse. Set the *next due* date — reminders follow.
- **Breeding**: covering / insemination / transfer, pregnancy checks, expected foaling (start + 340 days, editable),
  *Record foaling* creates the foal's profile with sire and dam.
- **Embryos**: donor mare and sire from the horse list, grade, stage, storage location, recipient mare, transfer,
  pregnancy checks, foaling.
- **Diet log** and clinic **inventory**. No finance or HR.

## Horse Trainer

- **Training** notes, **shows** and results (titles and medals appear on the horse page and the public Champions page),
  **diet log**. Embryos read-only. No finance or HR.

## Groom / Stable Staff

- **My horses**: only the horses assigned to you. Scan the QR code on the stable door to open a horse directly.
- Quick buttons: log feeding, add a note, add photos (straight from the phone camera).
- **My HR**: leave and payslips.

## Website Editor

- **Website → Homepage & texts**: which sections show and in what order, featured horse, main titles, story and pillars
  in English and Arabic.
- **Horses on website**: switch horses on or off, mark breeding stallions and featured horses; *Our experts* tab for
  staff shown on the site. Foals born at SK appear only after the Owner approves.
- **News & events** (with photo) and **Gallery** (photos or YouTube / Instagram / Vimeo links).
- **Contact details** and social links.
- **Inbox** if the Owner gives access: messages from the website forms — assign, add internal notes, reply by email,
  save the sender as a client.
- Prices, owners and medical records are never shown on the website.

## Technical Support

- **Settings** and the **activity log for logins and security events** only. No access to finance, salary or HR data
  unless the Owner grants it temporarily (it ends automatically on the date set).

---

## SK Arabian Studio (roles with access)

Official documents on the SK Arabian letterhead: horse profiles, diet and vet sheets, covering and embryo transfer
certificates, boarding contracts, invoices, receipts, payslips, purchase orders, HR letters, ID cards, reminders and the
document register.

1. *New document* → choose the type → choose the horse, employee, client or bill: the fields fill in from the records.
2. Check and complete, then **Issue**: the document gets a reference (e.g. `SKA-SAL-2026-0001`) and a QR code anyone can
   scan to verify it is genuine. It is saved in the archive and on the record's *Documents* tab.
3. Print, download PDF or email it. Printing, downloads and sharing are logged. A wrong document is *voided*, never deleted.
