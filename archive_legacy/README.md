# Avatar Electric — Schedule of Project Dashboard

ระบบบริหารแผนงานโครงการ (Project Schedule) สำหรับบริษัท Avatar Electric
พัฒนาด้วย **PHP 8 + MySQL/MariaDB + Bootstrap 5.3 + Chart.js**
ใช้ **PDO + Prepared Statement** ทุกจุดที่ติดต่อฐานข้อมูล

---

## ✨ ฟีเจอร์

- **Dashboard** ผู้บริหาร: การ์ดสรุป 6 สถานะ, Doughnut chart สัดส่วนสถานะ,
  Bar chart จำนวนงานตามเดือน, ตารางแผนงาน, panel มูลค่าตามสถานะ,
  ตารางงานล่าช้า (Overdue), กำหนดส่งมอบที่กำลังจะถึง และ Summary bar
- **CRUD โครงการ**: เพิ่ม / แก้ไข / ดูรายละเอียด / ลบ — ใช้งานได้จริง พร้อมแนบไฟล์
- **ค้นหา & กรอง** ตามคำค้น / สถานะ / แผนก
- **Export Excel** (.xls รองรับภาษาไทย UTF-8)
- **Print Report** รายงานผู้บริหาร (ทั้งรายการ และรายโครงการ) พร้อมช่องลงนาม
- คำนวณ **สถานะอัตโนมัติ** จากวันที่จริง

### กฎการคำนวณสถานะ (`compute_status()` ใน `functions.php`)
| เงื่อนไข | สถานะ |
|---|---|
| มี `completed_date` | `completed` เสร็จแล้ว |
| ยังไม่เสร็จ และ `due_date` < วันนี้ | `overdue` ล่าช้า |
| ยังไม่เสร็จ และ `due_date` ภายใน 7 วัน | `near_due` ใกล้ครบกำหนด |
| เริ่มงานแล้ว (`start_date` ≤ วันนี้) ยังไม่เสร็จ | `in_progress` กำลังดำเนินการ |
| นอกเหนือจากนั้น | `pending` รอเริ่มงาน |

> ปรับจำนวนวัน "ใกล้ครบกำหนด" ได้ที่ค่า `NEAR_DUE_DAYS` ใน `db.php`

---

## 📁 โครงสร้างไฟล์

```
schedule/
├── index.php           # Dashboard
├── projects.php        # รายการโครงการทั้งหมด + ค้นหา/กรอง
├── project_add.php     # เพิ่มโครงการ
├── project_edit.php    # แก้ไขโครงการ
├── project_view.php    # ดูรายละเอียด
├── project_delete.php  # ลบโครงการ
├── _project_form.php   # ฟอร์มร่วม (add/edit)
├── export_excel.php    # ส่งออก Excel
├── print_report.php    # รายงานสำหรับพิมพ์
├── db.php              # การตั้งค่า + เชื่อมต่อฐานข้อมูล (PDO)
├── functions.php       # helper + layout (header/footer)
├── database.sql        # โครงสร้างตาราง + ข้อมูลตัวอย่าง 22 โครงการ
├── README.md
├── assets/
│   ├── css/style.css
│   ├── js/app.js
│   └── img/logo.png
└── uploads/            # ที่เก็บไฟล์แนบ (ห้ามรันสคริปต์ — มี .htaccess)
```

---

## 🚀 การติดตั้งบน XAMPP

1. คัดลอกโฟลเดอร์นี้ไว้ที่ `xampp/htdocs/schedule`
2. เปิด **XAMPP Control Panel** → Start **Apache** และ **MySQL**
3. นำเข้าฐานข้อมูล (เลือกวิธีใดวิธีหนึ่ง):
   - **phpMyAdmin**: เปิด `http://localhost/phpmyadmin` → Import → เลือก `database.sql`
   - **Command line**:
     ```bash
     mysql -u root < database.sql
     ```
   ไฟล์ `database.sql` จะสร้างฐานข้อมูล `avatar_schedule` ให้อัตโนมัติ
4. ตรวจสอบค่าเชื่อมต่อใน `db.php` (ค่าเริ่มต้นของ XAMPP คือ user `root` รหัสผ่านว่าง)
5. เปิดใช้งานที่ `http://localhost/schedule/`

### ติดตั้งบน Hosting ทั่วไป
- อัปโหลดไฟล์ทั้งหมดผ่าน FTP/cPanel File Manager
- สร้างฐานข้อมูล MySQL แล้ว Import `database.sql`
- แก้ค่า `DB_HOST / DB_NAME / DB_USER / DB_PASS` ใน `db.php`
- ให้สิทธิ์เขียนแก่โฟลเดอร์ `uploads/` (เช่น `chmod 775 uploads`)

---

## 🖼️ ฟีเจอร์ Generate Project Overview Image

สร้างรูปภาพรายงานภาพรวมโครงการ (ขนาดคงที่ **1536 × 1024 px**) ด้วย HTML + CSS + **html2canvas**

**Flow:** หน้า `project_view.php` → ปุ่ม **“Generate Project Image”** → `project_overview_image.php?id=...`
→ ดึงข้อมูลจริงจาก MySQL → render เป็น report canvas → Preview → Export

**Export ได้:** Download PNG · Download JPG · Print/Save PDF · Fullscreen Preview
(ใช้ `html2canvas` `scale: 2` ให้ตัวหนังสือคมชัด, รอ `document.fonts.ready` ก่อน capture เพื่อให้ฟอนต์ไทยถูกต้อง)

**Timeline (Gantt)** คำนวณตำแหน่ง bar เป็น % จากวันเริ่ม–วันจบจริงของแต่ละกิจกรรม
เทียบกับช่วงเวลารวมของโครงการ พร้อมเส้น “วันนี้” และเส้นแบ่งเดือน

ไฟล์ที่เกี่ยวข้อง:
```
project_overview_image.php          # report canvas + ดึงข้อมูล
assets/css/project_overview.css     # layout 1536x1024 (CSS Grid/Flexbox)
assets/js/project_overview_export.js# PNG / JPG / PDF / Fullscreen
database_overview.sql               # 4 ตารางใหม่ + sample data
```

**ตารางใหม่ (นำเข้าหลัง `database.sql`):**
```bash
mysql -u root avatar_schedule < database_overview.sql
```
- `project_tasks` — กิจกรรมในแผน (timeline/Gantt)
- `project_panels` — ชุดงาน/ตู้ไฟ (work packages)
- `project_milestones` — หมุดหมายสำคัญ
- `delivery_groups` — งวดส่งมอบ

> มีข้อมูลตัวอย่างให้สำหรับโครงการ id 1, 2, 3, 6 — โครงการที่ยังไม่มีข้อมูลย่อยจะแสดงสถานะว่างอย่างเรียบร้อย

---

## 🗄️ ระบบติดตามสถานะรายตู้ / Panel (Cabinet) Tracking

1 โครงการมีได้หลายตู้ — แต่ละตู้มีสถานะ/ความคืบหน้า/กำหนดส่งของตัวเอง

**ไฟล์:** `panels.php` (หน้าติดตามรวม), `panel_add.php`, `panel_edit.php`,
`panel_delete.php`, `panel_update_status.php`, `_panel_form.php`, ตาราง `project_panels`

**สถานะรายตู้ → progress อัตโนมัติ** (ระบบบังคับให้สัมพันธ์กันเสมอ):

| สถานะ | ความหมาย | progress |
|---|---|---:|
| pending | รอเริ่ม | 0% |
| design | ออกแบบ | 10% |
| material | เตรียมอุปกรณ์ | 25% |
| production | ผลิตโครงตู้ | 45% |
| wiring | ประกอบ & Wiring | 65% |
| qc | ตรวจสอบ QC | 85% |
| ready_delivery | พร้อมส่ง | 95% |
| delivered | ส่งมอบแล้ว | 100% |

**กฎคำนวณสถานะจริง (`compute_panel_status()`):**
- มี `actual_delivery_date` → `delivered`
- ยังไม่ส่ง & `target_delivery_date` < วันนี้ → `overdue`
- นอกนั้น → สถานะ workflow ที่บันทึกไว้

**ความสามารถ:**
- เพิ่ม / แก้ไข / ลบ ตู้ จากหน้า Project View (แท็บ Panel Tracking)
- เปลี่ยนสถานะรายตู้ได้ทันทีจาก dropdown ในตาราง (`panel_update_status.php`)
- `delivery_group` (A/B/C/D), `target_delivery_date` แยกรายตู้, คำนวณ overdue รายตู้
- **Progress รวมของโครงการ = ค่าเฉลี่ย `progress_percent` ของทุกตู้** (อัปเดต `projects.progress` อัตโนมัติทุกครั้งที่ตู้เปลี่ยน)

**หน้า Panel Tracking (`panels.php`):** การ์ดสรุป (ทั้งหมด/ส่งแล้ว/กำลังทำ/ล่าช้า/progress เฉลี่ย),
Pie chart สถานะรายตู้, ตารางรายตู้ + ตัวกรอง **โครงการ / สถานะ / Delivery Group / ผู้รับผิดชอบ**
และ Dashboard หลักก็มีสรุป Panel + Pie เพิ่มเข้ามา

**Generate Project Image** ดึงข้อมูลรายตู้: Panel List (ล่างซ้าย), Delivery Schedule by Group (ล่างกลาง),
Key Milestones (ล่างขวา), Overall Progress คำนวณจากตู้ — และ **ถ้ามีตู้เกิน 16 รายการจะแบ่งหน้า report อัตโนมัติ**

### 🎯 เลือกขอบเขตการสร้าง Overview Report
จากหน้า Project View กดปุ่ม **“Generate Overview Report”** → เข้าหน้า `project_report_select.php`
เลือกได้ 3 แบบ แล้วกด Preview Report (เปิด `project_overview_image.php`):

| Scope | URL | Report Title | ชื่อไฟล์ export |
|---|---|---|---|
| ทั้ง Project | `?project_id=X&scope=all` | PROJECT OVERVIEW REPORT | `project-overview-<no>` |
| เลือกตู้เดียว | `?project_id=X&scope=single&panel_ids=1` | PANEL OVERVIEW REPORT | `panel-overview-<panelno>` |
| เลือกหลายตู้ | `?project_id=X&scope=multiple&panel_ids=1,2,3` | SELECTED PANELS OVERVIEW REPORT | `selected-panels-overview-<no>` |

- หน้าเลือก: ค้นหา + กรอง (ประเภท/สถานะ/group/ช่วงวันส่ง), Select All/Clear, badge สี + progress bar รายตู้
- JS บังคับกฎ: all=ปิด checkbox, single=เลือกได้ 1, multiple=หลายตู้, กัน submit เมื่อยังไม่เลือก
- **ความปลอดภัย:** `panel_ids` ผ่าน `validate_panel_ids()` ด้วย prepared statement (placeholder ต่อ id) —
  ตรวจว่าทุก id อยู่ใน `project_id` จริง, id ต่างโครงการ/ไม่ถูกต้องถูกตัดทิ้ง (single/multiple ที่ไม่มี id ที่ถูกต้อง → HTTP 400)
- Progress / Timeline / Delivery Group / Milestones / Target Completion คำนวณเฉพาะตู้ที่เลือก

**นำเข้าตาราง (run หลัง `database_overview.sql`):**
```bash
mysql -u root avatar_schedule < database_panels.sql
```
> ลำดับ import ทั้งหมด: `database.sql` → `database_overview.sql` → `database_panels.sql`

---

## 🎨 Design System (Avatar Electric Brand)
ธีมทั้งระบบใช้ **Design Token** (CSS variables ใน `:root` ของ `assets/css/style.css` และ `project_overview.css`) — ไม่ hardcode สีในเทมเพลต

| Token | สี | ใช้กับ |
|---|---|---|
| `--primary-color` | `#FF7A00` | สีหลักแบรนด์, ปุ่ม, หัวตาราง, active menu, กราฟ |
| `--primary-hover` | `#E96500` | hover |
| `--sidebar-color` | `#111111` | Sidebar (ดำ Enterprise) |
| `--sidebar-hover` | `#1F2937` | hover menu |
| `--success/warning/info/danger` | `#10B981 / #F59E0B / #3B82F6 / #EF4444` | สถานะ |
| `--gray-100/200/500/900` | `#F8F9FB / #E5E7EB / #6B7280 / #111111` | พื้นหลัง/เส้น/ข้อความ |

- **Sidebar** พื้นดำ + แถบส้ม, โลโก้ Avatar บนพื้นขาว, active = ส้ม, hover = `#1F2937`
- **ตาราง** หัวสีส้ม ตัวอักษรขาว, hover `#FFF4E6`, แถวสลับ `#FAFAFA`
- **Status badge / Panel status** สีตามสเปก (ผ่าน `status_colors()` / `panel_status_colors()` ใน functions.php = single source)
- **Gantt / Chart** ใช้พาเลตเดียวกัน, สีหลักส้ม, ดู Professional
- **Typography** Prompt/Sarabun — H1 32, H2 24, H3 18, Body 15, Small 13
- Business logic / Database / Flow **เดิมทั้งหมด ไม่เปลี่ยน**

## 🔧 หมายเหตุทางเทคนิค
- ใช้ **PDO** + Prepared Statement ทั้งหมด ป้องกัน SQL Injection
- Escape ทุก output ด้วยฟังก์ชัน `e()` ป้องกัน XSS
- อัปโหลดไฟล์จำกัดนามสกุล + เปลี่ยนชื่อไฟล์แบบสุ่ม + ปิดการรันสคริปต์ในโฟลเดอร์ `uploads/`
- รองรับฟอนต์ไทย **Prompt / Sarabun** (Google Fonts) และวันที่แบบ พ.ศ.
- Responsive: Desktop เป็นหลัก, รองรับ Tablet (sidebar ย่อเป็นไอคอน)

ทดสอบแล้วบน PHP 8 + MariaDB 10.4 (XAMPP) — ทุกหน้า return HTTP 200 และวงจร เพิ่ม→แก้ไข→ลบ ทำงานครบถ้วน
