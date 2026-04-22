# Smart Academic Management System (SAMS)

A robust, centralized academic administration portal built using the **LAMP stack**. SAMS is designed to digitize and streamline institutional workflows, including student data governance, department management, and secure academic reporting.

## 🚀 Key Features

* **Universal Search Engine:** An AJAX-powered global search interface for real-time cross-table data retrieval (Students, Faculty, HODs) with minimal latency.
* **Bulk Data Migration:** Integrated automated CSV upload tool for seamless transition of large academic datasets into the MySQL relational database.
* **Role-Based Access Control (RBAC):** Granular permission levels for Students, Faculty, and HODs, ensuring secure data governance and proctorial verification.
* **Secure Authentication:** Multi-role login system featuring an advanced Email OTP-based "Forgot Password" workflow.
* **Dynamic Dashboards:** Interactive UI built with PHP and AJAX for real-time data filtering and reporting.

## 🛠️ Technical Stack

* **Frontend:** HTML5, CSS3 (Glassmorphism UI), JavaScript, AJAX
* **Backend:** PHP (Procedural/OOP)
* **Database:** MySQL
* **Environment:** Ubuntu Linux, Apache Server
* **Tools:** XAMPP, Samba (File Sharing), Git

## 📂 CSV Format for Bulk Upload

To use the bulk migration tool, ensure your CSV follows this structure:

| student_id | first_name | last_name | email | department | semester | phone_number |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| D2026-001 | Rahul | Kumar | rahul@example.com | CS | 4 | 9876543210 |

## 🔧 Installation & Setup

1.  **Clone the repository:**
    ```bash
    git clone https://github.com/your-username/SAMS.git
    ```
2.  **Move to Web Root:** Place the project folder in your XAMPP `htdocs` or Apache `/var/www/html` directory.
3.  **Database Setup:**
    * Open phpMyAdmin.
    * Create a database named `sams_db`.
    * Import the provided `.sql` file located in the `/database` folder.
4.  **Configure Connection:** Edit `config.php` (or your database connection file) with your MySQL credentials.
5.  **Access:** Open your browser and navigate to `http://localhost/SAMS/index.php`.

## 🖥️ System Requirements

* **OS:** Windows 10/11 or Ubuntu 20.04+ (Recommended)
* **Server:** Apache 2.4+
* **PHP:** 7.4 or 8.x
* **Database:** MySQL 5.7+ / MariaDB

## 👤 Author

* **Computer Science Student** - Dhenkanal Autonomous College
* Focus: Full-Stack Web Development & System Administration

# 📧 SAMS Mailer Implementation Guide (SMTP & App Passwords)

This guide provides the theoretical background and step-by-step instructions to configure the automated mailing system for the **Student Attendance & Management System (SAMS)** using PHPMailer and Gmail SMTP.

---

## 1. The Theory: Why "App Passwords"?

Google uses **OAuth 2.0** (the "Sign in with Google" buttons) for standard security. However, a PHP script is considered a **"Legacy Client"** because it lacks a user interface to click "Allow."

* **The Conflict:** Google blocks "Less Secure Apps" to prevent hackers from brute-forcing your account.
* **The Solution:** An **App Password** is a unique, 16-character "Backdoor Key." It bypasses 2-Step Verification for a specific script/task only.
* **Safety Advantage:** If your script is ever compromised, you can delete this specific key without changing your main Gmail password.



---

## 2. Setting Up the "Courier" (SMTP Configuration)

The `sendmail.php` file defines the rules for how your email is delivered.

### A. The Host & Port (`smtp.gmail.com` : `587`)
* **Host:** Think of this as the address of Google’s Post Office.
* **Port 587:** This is the specific "Window" used for **STARTTLS**. Your email starts as plain text but becomes encrypted before it leaves your local XAMPP server.

### B. Authentication (`SMTPAuth = true`)
* This tells Google: *"I am not a spammer. I have a valid ID (Email) and a valid Key (App Password)."* Without this, Google rejects the connection to prevent spam bot activity.

---

## 3. Implementation Guide

### Phase 1: Securing the Google Account
1.  **Enable 2-Step Verification:** Go to your Google Account > Security. This is mandatory.
2.  **Generate the Key:**
    * Search for **"App Passwords"** in your Google Account.
    * Select **"Other (Custom Name)"** and name it `SAMS_PROJECT_MAILER`.
    * **Crucial:** Copy the 16-character code immediately. Once you close the window, you cannot see it again.



### Phase 2: Wiring the `sendmail.php` Script
Fill out your script as if you are completing a shipping label:

* **Username:** The exact Gmail address that generated the App Password.
* **Password:** Paste the 16 characters (remove spaces for better compatibility).
* **setFrom:** Must match your Username to prevent your emails from being marked as "Spoofed" or Spam by providers like Yahoo or Outlook.

---

## 4. The "Connection" Test (Troubleshooting)

For the mail to send successfully, three things must happen in order:

1.  **Handshake:** Your XAMPP server successfully contacts `smtp.gmail.com` (Requires active internet).
2.  **Encryption:** Your server starts a **TLS session** (Requires **OpenSSL** enabled in XAMPP).
3.  **Validation:** Google accepts your 16-character App Password.



### 💡 Pro-Tip: The "90% Fix"
If your mail fails to send, it is almost always because **OpenSSL** is disabled.
1.  Open **XAMPP Control Panel**.
2.  Click **Config** (next to Apache) > **php.ini**.
3.  Search for `;extension=openssl`.
4.  **Remove the semicolon (`;`)** at the start of the line.
5.  **Restart Apache.**

---

## 5. Security Summary
By using this method, the SAMS project adheres to modern security standards:
* **No Hardcoded Passwords:** Your real Gmail password is never stored in the code.
* **End-to-End Encryption:** Student data remains private while traveling across the web.

---
*Developed as a Final Year Project for academic administration digitization.*
