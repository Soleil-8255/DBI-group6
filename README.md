<div align="center">
<a id="top" aria-label="Top"></a>

# 🎓 Internship Result Management System

### *IRMS — University of Nottingham Malaysia (UNM)*

**Role-based internship grading portal · PHP · MySQL · Server-rendered UI**

<br />

[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![MariaDB](https://img.shields.io/badge/MariaDB-10%2B-003545?style=for-the-badge&logo=mariadb&logoColor=white)](https://mariadb.org/)
[![Apache](https://img.shields.io/badge/Apache-2.4-D22128?style=for-the-badge&logo=apache&logoColor=white)](https://httpd.apache.org/)

[![Course](https://img.shields.io/badge/COMP1044-Integrated%20DBMS%20Coursework-0e7490?style=flat-square)]()
[![No Node](https://img.shields.io/badge/Runtime-No%20Node%2FReact%2FVue-64748b?style=flat-square)]()

<br />

· [Table of contents](#table-of-contents)

</div>

---

<a id="overview"></a>
## ✨ Overview

A **role-based web application** for the **University of Nottingham Malaysia (UNM)** to manage **internship placements**, **assessor evaluations**, and **student-visible results**.

Built for the **COMP1044** database-integrated assignment:

| | |
| :---: | --- |
| 🖥️ | **Server-rendered PHP** — strict types, no SPA framework required |
| 🗄️ | **MySQL** — integrity in the **database** (triggers, foreign keys) |
| 📊 | **Vanilla JavaScript** + **Chart.js** for dashboards and radar — **no React, Vue, or Node.js runtime** |

---

<a id="table-of-contents"></a>
## 📋 Table of contents

| | Section |
|---|--------|
| 1 | [At a glance](#at-a-glance) |
| 2 | [Tech stack](#tech-stack) |
| 3 | [Requirements](#requirements) |
| 4 | [Installation & configuration](#installation-configuration) |
| 5 | [How to run](#how-to-run) |
| 6 | [Default accounts (seed)](#default-accounts) |
| 7 | [Feature overview](#feature-overview) |
| 8 | [Database & advanced behaviour](#database-advanced) |
| 9 | [Project layout](#project-layout) |
| 10 | [Security practices](#security-practices) |
| 11 | [Further documentation](#further-documentation) |

---

<a id="at-a-glance"></a>
## 🎯 At a glance

| Goal | How **IRMS** supports it |
| :--- | :--- |
| 🔐 **SSO by role** | `Admin`, `Assessor`, and `Student` sign in on **`COMP1044_SRC/index.php`**; sessions route to the correct dashboard. |
| 🧩 **Data integrity** | Internships link **students**, **companies**, and **assessors**; **one assessment per internship**; **total marks** from **MySQL triggers** (fixed weighting). |
| 🛠️ **Administration** | User CRUD, internship records, result search, workload view, grade-related **alerts**. |
| 📝 **Assessor workflow** | Eight criteria per placement, comments, **CSV** export (assessor-scoped). |
| 🎨 **Student experience** | Results for **completed** placements, **radar** chart, **PNG** export. |

---

<a id="tech-stack"></a>
## 🛠️ Tech stack

| Layer | Choice |
|------|--------|
| **Language** | PHP 8+ (`declare(strict_types=1);` throughout) |
| **Database** | MySQL / MariaDB (`utf8mb4`) |
| **Data access** | **PDO** + **prepared statements** |
| **Front end** | HTML + CSS (external stylesheets; no inline `style` in templates) + JavaScript (Chart.js, etc.) |
| **Server** | Apache (e.g. **XAMPP** on Windows) |

---

<a id="requirements"></a>
## 📦 Requirements

- **PHP** `>= 8.0` (PDO MySQL enabled)
- **MySQL 5.7+** or **MariaDB 10.x**
- **Apache** (or equivalent) with document root / vhost → this project
- Modern **browser** (Chrome / Edge / Firefox)

---

<a id="installation-configuration"></a>
## ⚙️ Installation & configuration

### 1 · Clone or copy the project

Place the folder under your web root, for example:

| OS | Example path on disk |
|----|----------------------|
| 🪟 **XAMPP (Windows)** | `C:\xampp\htdocs\<YourFolder>\` (e.g. `A-DBI-CW` or `COMP1044_CW_G6`) |
| 🐧 **Linux** | `/var/www/<YourFolder>/` |

> [!TIP]
> **Course layout:** put **all application source** under **`COMP1044_SRC/`** (PHP, `assets/`, `pages/`, `backend/`, and **`index.php` / `logout.php`** for entry).  
> At the **same level** (next to `COMP1044_SRC/`, not inside it), place **non-code deliverables**: `COMP1044_database.sql`, ERD / assignment PDFs, `README.md`, `VIDEO_SUBMISSION_LINKS.txt`, etc. Those files are not “extra code in the wrong place”—they are the usual way to hand in SQL, docs, and video links.

**Folder sketch**

```text
<YourFolder>/                            ← e.g. A-DBI-CW (name = first segment in http://localhost/.../ )
  COMP1044_SRC/                          ← all runnable web app code
    index.php                            ← only login/entry; open this URL (see [How to run](#how-to-run))
    logout.php
    includes/db_connect.php              ← edit DB credentials here
    pages/   assets/   backend/   …
  COMP1044_database.sql                  ← import in phpMyAdmin (not inside SRC)
  COMP1044_ERD.pdf, Assignment-*.pdf, README.md, VIDEO_SUBMISSION_LINKS.txt  …
```

---

### 2 · Import the database

1. Start **MySQL** (e.g. XAMPP Control Panel).
2. In **phpMyAdmin** or a client, import **`COMP1044_database.sql`** from the project root.

This creates `internship_system` (as defined in the script), including tables, views, triggers, and seed data.

> [!IMPORTANT]
> For **course submission**, use the bundled **`COMP1044_database.sql`** so behaviour matches the spec.

---

### 3 · Configure `db_connect.php`

Edit **`COMP1044_SRC/includes/db_connect.php`:**

| Setting | Description |
|--------|-------------|
| `$dsn` | PDO DSN, e.g. `mysql:host=127.0.0.1;port=3306;dbname=internship_system;charset=utf8mb4` |
| `$dbUser` / `$dbPass` | MySQL credentials (XAMPP default is often `root` + **empty** password) |
| `IRMS_DB_DEBUG_CONNECTION` | **`false`** in production → generic user-facing message; **`true`** only for local **connection** debugging (shows PDO error in browser) |

> [!CAUTION]
> When debug is **off**, connection failures are **logged** server-side; users never see raw PDO output.

---

### 4 · Subdirectory deploy (optional)

If the app lives under a path like `http://localhost/<YourFolder>/`, base path is inferred automatically—no extra config for `pages/...` or `assets/`.

---

### 5 · Optional: PHP lint

```bash
php -l COMP1044_SRC/index.php
php -l COMP1044_SRC/includes/db_connect.php
# repeat for other entry points as needed
```

---

<a id="how-to-run"></a>
## 🚀 How to run & **which URL to open (login page)**

1. **Start** **Apache** and **MySQL** in XAMPP (or your stack).
2. Import **`COMP1044_database.sql`** (from the project **parent** folder) and set **`COMP1044_SRC/includes/db_connect.php`** to your MySQL user, password, and `internship_system` database.

### Login URL (pick the one that matches **your** folder name)

The **login page** is always: **`http://localhost/<FOLDER_NAME>/COMP1044_SRC/index.php`**

**`<FOLDER_NAME>` is not a magic name** — it must be **exactly the same** as the project folder you see in **`C:\xampp\htdocs\`**. If that folder does not exist, Apache returns **404 Not Found**.

> [!CAUTION]
> **If you get “Not Found / 404”**  
> The name right after `http://localhost/` must match your folder under `C:\xampp\htdocs\`. For example, use **`http://localhost/A-DBI-CW/COMP1044_SRC/index.php`** if the folder is **`A-DBI-CW`**.

| Your folder under `htdocs\` (examples) | Open this in the browser |
|:---|:---|
| `A-DBI-CW` | **`http://localhost/A-DBI-CW/COMP1044_SRC/index.php`** |
| `COMP1044_CW_G6` (if the folder is renamed) | **`http://localhost/COMP1044_CW_G6/COMP1044_SRC/index.php`** |
| Any other name, e.g. `MyGroup` | **`http://localhost/MyGroup/COMP1044_SRC/index.php`** |
| **DocumentRoot** = `COMP1044_SRC` only (advanced) | **`http://localhost/index.php`** |

**Adding** `README`, `COMP1044_database.sql`, PDFs, or `VIDEO_SUBMISSION_LINKS.txt` **beside** `COMP1044_SRC/` does **not** change the app URL. Entry remains **`…/COMP1044_SRC/index.php`** (unless the server root is set to `COMP1044_SRC` as in the last row).

> [!NOTE]
> If the page is blank or shows a **database** error, Apache **did** find the file — then check `COMP1044_SRC/includes/db_connect.php` and that MySQL is **running** in XAMPP.

3. On the **login** screen, sign in as **Admin**, **Assessor**, or **Student** (see [default accounts](#default-accounts)).
4. Use **Logout** in the header to end the session.

> [!NOTE]
> **Students** who are already signed in and open the login page get a short link to the student dashboard.

---

<a id="default-accounts"></a>
## 🔑 Default accounts (seed data)

The seed file documents a **shared test password** (see comments in `COMP1044_database.sql`, often `123123`).

| Role | Try (examples) |
|------|------------------|
| 🛡️ **Admin** | e.g. `admin` |
| 👨‍🏫 **Assessor** | e.g. `yasir_s` (and other rows in the seed) |
| 🧑‍🎓 **Student** | accounts linked in `Users` + `Students` |

If `password_verify` fails, **re-import** the SQL or reset a password in **Admin → user management** (see the Chinese runbook).

---

<a id="feature-overview"></a>
## 🧩 Feature overview

### Module 1 — 🔐 Authentication

- Login with **bcrypt** hashes and `password_verify`
- **Session**-gated pages by **role**
- **Generic** error on bad credentials (no SQL leaks in the UI)
- **Logout** clears the session

### Module 2 — 🛡️ Admin

| Area | What you get |
|------|----------------|
| **Home** | Dashboard + navigation |
| **Users** | Students & assessors: CRUD, confirmations, programme/cohort fields (rules apply for deletes) |
| **Bulk import** | **CSV** for students & assessors → `api_import_*.php` · templates in `assets/csv/` |
| **Internships** | Full internship CRUD, companies, status, dates (as implemented) |
| **Results** | Search by student id / name, etc. |
| **Workload** | View from **`Assessor_Workload_View`** |
| **Alerts** | Grade / audit context from **`Audit_Logs`**, aligned with DB logic |

### Module 3 — 👨‍🏫 Assessor

| Area | What you get |
|------|----------------|
| **Dashboard** | **Your** assigned internships only |
| **Evaluate** | **8** component scores + comments · save & re-edit · total from **triggers** + server rules |
| **Export** | **CSV** — assessor-scoped rows only |

### Module 4 — 🧑‍🎓 Student

| Area | What you get |
|------|----------------|
| **Dashboard** | Profile + placement list; messaging for **ongoing** vs **completed** |
| **Results** | **Total** + **8** components + **comments** when published |
| **Radar** | **Chart.js** multi-axis · selector when multiple **completed** placements |
| **Export** | **PNG** of the radar |

### Innovation mapping (in code)

Comment blocks in **`COMP1044_SRC/includes/functions.php`** map “innovation” ideas (radar, workload-style views, alerts, export, self-vs-assessor future work) to **`assets/js/modules/`** — see the repo for the exact map.

---

<a id="database-advanced"></a>
## 🗄️ Database & advanced behaviour

- **Script:** `COMP1044_database.sql` — DDL, seed, **FKs**, **triggers** (total recalculation), **`Assessor_Workload_View`**
- **Model:** one assessment per internship (unique / FK as in the SQL)
- **Audit** rows for grade-related **admin** visibility

> [!NOTE]
> Pair this repo with your submitted **ERD / DD PDF** for full column- and trigger-level detail.

---

<a id="project-layout"></a>
## 📁 Project layout

| Path | Role |
|------|------|
| `COMP1044_SRC/index.php` | **Login page** in the browser + role routing |
| `COMP1044_SRC/logout.php` | End session |
| `COMP1044_SRC/includes/` | DB bootstrap, `functions.php` (routes, CSRF, `h()`, helpers) |
| `COMP1044_SRC/pages/*/` | Page controllers |
| `COMP1044_SRC/backend/modules/` | Dashboards & business logic |
| `COMP1044_SRC/assets/…` | CSS & JS |
| `COMP1044_database.sql` (beside `COMP1044_SRC/`) | **Import** for a working DB (not inside SRC) |
| `README.md`, `VIDEO_SUBMISSION_LINKS.txt`, ERD/assignment PDFs (beside `COMP1044_SRC/`) | Documentation & submission; **not** application code |

---

<a id="security-practices"></a>
## 🔒 Security practices

- ✅ **Prepared statements** — no ad-hoc string SQL
- ✅ **`h()`** for dynamic HTML
- ✅ **CSRF** on state-changing **POST** forms
- ✅ **No** PDO / stack traces in the browser when `IRMS_DB_DEBUG_CONNECTION === false`
- ✅ **Role** checks on sensitive actions

---

<a id="further-documentation"></a>
## 📚 Further documentation (in the project root)

| File | Purpose |
|------|---------|
| `Assignment-COMP1044.pdf` | Official coursework brief and submission rules |
| `COMP1044_ERD.pdf` | ERD / data-dictionary hand-in |
| `COMP1044_database.sql` | Database to import in phpMyAdmin (beside `COMP1044_SRC/`) |
| `VIDEO_SUBMISSION_LINKS.txt` | Demo video (YouTube / Google Drive links), if used |
| `README.md` | This file |

If you add optional docs (runbooks, demo scripts) later, you can create a `docs/` folder; they are not required to run the app.

---

<div align="center">

### 📌 Course

**COMP1044** — Database Systems · *Integrated coursework*

*No `npm start` — this stack is **PHP + MySQL** only.*

<br />

[⬆ Back to top](#top)

</div>
