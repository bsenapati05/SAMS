
# Student Management System – Detailed Workflow (Step by Step)

## 1) Admission Import
- Admin logs in and uploads `admission.xlsx` (single source of truth).
- System validates required columns (Roll No, Name, Father’s Name, DOB, Department, Year, Email, Phone).
- Cleans data (trims spaces, normalizes case, removes duplicates).

## 2) Database Create/Update
- For each row, create **Student** and linked **User (role=STUDENT)** with default password policy.
- Link **Department**; create if missing (optional policy).
- Record an **Import Batch** entry and write **Audit Logs** for traceability.

## 3) Year-Based Access
- Student logs in using Roll No/username and password.
- System detects their `year` from **Student** table and routes to a year-specific dashboard.

## 4) Marksheet
- Admin uploads marks via Excel/CSV mapped as: Roll No, Subject Code, Exam (Mid/Final), Marks.
- System resolves foreign keys (Student, Subject, Exam) and writes **ExamResult** rows.
- Student views auto-generated marksheet (per exam/term), with totals/grades derived from **ExamResult**.

## 5) Timetable
- Admin creates **Exam** (e.g., Mid, Final) and uploads a year-wise **Timetable**.
- Students see timetable filtered by their `year` (and department/subject as applicable).

## 6) Syllabus
- Admin uploads **SyllabusDocument** per year/subject (PDF).
- Students can view/download syllabus appropriate to their year/department.

## 7) Correction Requests
- If a student finds a mismatch (e.g., father_name), they submit a **CorrectionRequest** with proof.
- Admin verifies against admission Excel/source documents, updates **Student**, and closes the request.
- All changes are recorded in **Audit Logs** (who changed what and when).

## 8) Security & Policies
- Passwords are hashed; password resets via admin or email/OTP.
- Role-based authorization: **Admin** can import/upload; **Students** have read-only access to academic records.
- Backups: database backups and original Excel stored securely.

## 9) Outputs
- PDF Marksheets and reports are generated from DB only—no manual typing—eliminating name mismatches.
  