# Migration Cleanup Report
## Avatar Electric — Schedule of Project Dashboard
**วันที่สร้าง:** 2026-06-10  
**โปรเจกต์เดิม:** `/Applications/XAMPP/xamppfiles/htdocs/schedule/`  
**โปรเจกต์ใหม่:** `/Applications/XAMPP/xamppfiles/htdocs/schedule-laravel/`  
**สถานะ Migration:** Laravel 12.62.0 + Livewire 4.3.1 — **สมบูรณ์ 100%** (45 routes, 0 placeholder)

---

## สรุป

| สถานะ | จำนวนไฟล์ | รายละเอียด |
|--------|-----------|------------|
| KEEP   | 6         | Assets ที่ใช้งานอยู่ใน Laravel (logo, uploads dir, git config) |
| MIGRATE| 24        | Logic ถูกย้ายไป Laravel แล้วครบ 100% |
| ARCHIVE| 7         | SQL backups + docs ควรเก็บสำรอง |
| DELETE | 3         | ไฟล์ระบบ OS (.DS_Store) ไม่มีค่า |

---

## รายละเอียดแต่ละไฟล์

### PHP Files — Root Level

| ไฟล์เดิม | หน้าที่เดิม | Laravel Component ใหม่ | สถานะ | เหตุผล |
|---------|------------|----------------------|-------|-------|
| `index.php` | Executive Dashboard (KPI, Gantt, charts) | `DashboardController@index` + `views/dashboard/index.blade.php` + `DashboardService.php` | **MIGRATE** | Logic ย้ายครบ, chart inline ใน Blade |
| `projects.php` | รายการ Project ทั้งหมด + filter | `ProjectController@index` + `views/projects/index.blade.php` | **MIGRATE** | CRUD ครบ, filter ย้ายเข้า controller |
| `project_view.php` | Project detail (6 tabs: Overview, ตู้, Timeline, ส่งมอบ, รายงาน, Activity) | `ProjectController@show` + `views/projects/show.blade.php` | **MIGRATE** | ทุก tab ถูก port แล้ว รวม Phase 11 |
| `project_add.php` | สร้าง Project ใหม่ | `ProjectController@create/store` + `views/projects/create.blade.php` + `_form.blade.php` | **MIGRATE** | Validation ย้ายใน FormRequest |
| `project_edit.php` | แก้ไข Project | `ProjectController@edit/update` + `views/projects/edit.blade.php` + `_form.blade.php` | **MIGRATE** | รวม `_project_form.php` เดิม |
| `project_delete.php` | ลบ Project (cascade) | `ProjectController@destroy` | **MIGRATE** | Cascade delete ผ่าน Eloquent |
| `_project_form.php` | Partial form Project (include) | `views/projects/_form.blade.php` | **MIGRATE** | Blade partial แทน PHP include |
| `panels.php` | Panel/Cabinet tracking dashboard | `CabinetController` + redirect to `projects.index` | **MIGRATE** | รูปแบบ navigation เปลี่ยน: ตู้อยู่ใต้ project |
| `panel_add.php` | เพิ่มตู้ใน Project | `CabinetController@create/store` + `views/cabinets/create.blade.php` | **MIGRATE** | Task template auto-apply ผ่าน `CabinetService` |
| `panel_edit.php` | แก้ไขตู้ | `CabinetController@edit/update` + `views/cabinets/edit.blade.php` | **MIGRATE** | รวม `_panel_form.php` เดิม |
| `panel_delete.php` | ลบตู้ (cascade tasks) | `CabinetController@destroy` | **MIGRATE** | Cascade ผ่าน Eloquent `HasMany` |
| `_panel_form.php` | Partial form ตู้ (include) | `views/cabinets/_form.blade.php` | **MIGRATE** | Blade partial แทน PHP include |
| `panel_update_status.php` | Legacy AJAX status update (deprecated ใน original ด้วย) | `Livewire/TaskStatusDropdown.php` | **MIGRATE** | ถูกแทนที่ด้วย Livewire realtime |
| `deliveries.php` | Delivery tracking dashboard | `DeliveryController@index` + `views/deliveries/index.blade.php` | **MIGRATE** | Logic overdue/upcoming/delivered ครบ |
| `reports.php` | Reports hub | `ReportController@index` + `views/reports/index.blade.php` | **MIGRATE** | Hub ครบ, ลิงก์ไปทุก report type |
| `project_report_select.php` | Scope selector (all/single/multiple) | `ReportController@scope` + `views/reports/scope.blade.php` | **MIGRATE** | Filter ตู้ด้วย search/type/status/group |
| `project_overview_image.php` | Overview Report HTML→PNG/JPG (multi-page) | `ReportController@overview` + `views/reports/overview.blade.php` | **MIGRATE** | html2canvas export ใช้งานได้ครบ |
| `print_report.php` | พิมพ์รายงาน A4 | `ReportController@printReport` + `views/reports/print.blade.php` | **MIGRATE** | รองรับ ?id= (single) หรือทุก project |
| `export_excel.php` | Export Excel (.xls HTML table) | `ReportController@exportExcel` + `views/reports/excel.blade.php` | **MIGRATE** | UTF-8 BOM + same Content-Type trick |
| `task_templates.php` | Task Template CRUD | `TaskTemplateController` (7 routes) + `views/task-templates/index.blade.php` | **MIGRATE** | Reorder, items CRUD ครบ |
| `settings.php` | System settings (departments, users) | `SettingsController@index` + `views/settings/index.blade.php` | **MIGRATE** | แสดง PHP/Laravel/Livewire versions |
| `functions.php` | Helper functions ทั้งหมด + page layout | `AppHelper.php`, `StatusHelper.php`, `ProjectService.php`, `DashboardService.php`, `CabinetService.php`, `layouts/app.blade.php` | **MIGRATE** | แยก concern ชัดเจนกว่าเดิม |
| `db.php` | PDO connection (credentials hardcoded) | `config/database.php` + `.env` | **MIGRATE** | Credentials ย้ายเข้า `.env` แล้ว |

### PHP Files — api/

| ไฟล์เดิม | หน้าที่เดิม | Laravel Component ใหม่ | สถานะ | เหตุผล |
|---------|------------|----------------------|-------|-------|
| `api/task_update.php` | AJAX endpoint: update task status + dates, return JSON | `Livewire/TaskStatusDropdown.php` + `Livewire/TaskDateEditor.php` | **MIGRATE** | Livewire wire:model แทน raw AJAX; ไม่มี JSON endpoint แยก |

---

### Assets

| ไฟล์เดิม | หน้าที่เดิม | Laravel Component ใหม่ | สถานะ | เหตุผล |
|---------|------------|----------------------|-------|-------|
| `assets/css/style.css` | Stylesheet หลัก (503 บรรทัด) | `public/css/app.css` (identical) | **MIGRATE** | Copy ไป Laravel แล้ว, diff = 0 |
| `assets/css/project_overview.css` | Styles สำหรับ Overview Report | `public/css/project_overview.css` (identical) | **MIGRATE** | Copy ไป Laravel แล้ว, diff = 0 |
| `assets/js/project_overview_export.js` | html2canvas export (PNG/JPG) | `public/js/project_overview_export.js` (identical) | **MIGRATE** | Copy ไป Laravel แล้ว, diff = 0 |
| `assets/js/app.js` | Dashboard JS: form validation, Chart.js initialization | Inline ใน Blade + Livewire components | **MIGRATE** | Logic กระจายใน Blade + Livewire; Chart.js ผ่าน CDN; **ยังไม่ถูก copy ไป `public/js/`** — ไม่จำเป็นต้อง copy เพราะ Laravel ไม่ใช้ไฟล์นี้ |
| `assets/img/logo.png` | โลโก้บริษัท | `public/assets/img/logo.png` | **KEEP** | Asset ที่ใช้งานอยู่ใน Laravel, มี copy แล้ว |

---

### Database / SQL Files

| ไฟล์เดิม | หน้าที่เดิม | สถานะ | เหตุผล |
|---------|------------|-------|-------|
| `database.sql` | Full schema + sample data (161 บรรทัด) | **ARCHIVE** | Reference สำหรับ schema เดิม; Laravel ใช้ Migrations แทน |
| `database_overview.sql` | Extra tables สำหรับ overview report (127 บรรทัด) | **ARCHIVE** | Reference schema ส่วน overview; ควรเก็บเผื่อ restore |
| `database_panels.sql` | Panel/Cabinet tracking schema (112 บรรทัด) | **ARCHIVE** | Reference schema ส่วน panels |
| `sample_extra.sql` | Demo data (100 บรรทัด) | **ARCHIVE** | Useful สำหรับ dev/test environment ในอนาคต |

---

### Directory Structure

| Directory/ไฟล์ | หน้าที่เดิม | สถานะ | เหตุผล |
|--------------|------------|-------|-------|
| `uploads/` (dir + `.gitkeep` + `.htaccess` + `index.html`) | Upload storage (ยังว่างอยู่) | **KEEP** | Directory structure + security files ยังใช้ได้ถ้ามีการ upload ในอนาคต |
| `README.md` | Documentation โปรเจกต์เดิม | **ARCHIVE** | เก็บเป็น historical record |
| `.gitignore` | Git ignore rules | **KEEP** | ส่วนหนึ่งของ git repo |

---

### OS / System Files (DELETE)

| ไฟล์ | เหตุผล |
|------|-------|
| `.DS_Store` (root) | macOS metadata, ไม่มีประโยชน์ |
| `assets/img/.DS_Store` | macOS metadata, ไม่มีประโยชน์ |
| `assets/.DS_Store` | macOS metadata, ไม่มีประโยชน์ |

---

## สรุปการดำเนินการที่ต้องทำ

### ARCHIVE — ย้ายไป `/archive_legacy/` (คงโครงสร้างเดิม)

```
archive_legacy/
├── _panel_form.php
├── _project_form.php
├── api/
│   └── task_update.php
├── assets/
│   ├── css/
│   │   ├── project_overview.css
│   │   └── style.css
│   └── js/
│       ├── app.js
│       └── project_overview_export.js
├── db.php
├── deliveries.php
├── export_excel.php
├── functions.php
├── index.php
├── panel_add.php
├── panel_delete.php
├── panel_edit.php
├── panel_update_status.php
├── panels.php
├── print_report.php
├── project_add.php
├── project_delete.php
├── project_edit.php
├── project_overview_image.php
├── project_report_select.php
├── project_view.php
├── projects.php
├── reports.php
├── settings.php
├── task_templates.php
├── database.sql
├── database_overview.sql
├── database_panels.sql
├── sample_extra.sql
└── README.md
```

### DELETE — ลบได้ทันที (3 ไฟล์)

```
.DS_Store
assets/.DS_Store
assets/img/.DS_Store
```

### KEEP — ไม่ต้องทำอะไร (คงอยู่ใน `/schedule/`)

```
.gitignore
.git/          (ทั้ง directory)
uploads/       (dir + .gitkeep + .htaccess + index.html)
assets/img/logo.png
```

---

## หมายเหตุสำคัญ

1. **`assets/js/app.js`** — ถูกจัด ARCHIVE (ไม่ KEEP) เพราะ Laravel ไม่ได้ copy หรือ reference ไฟล์นี้ โดย logic ถูกกระจายเข้า Livewire + inline Blade scripts แล้ว
2. **`assets/img/logo.png`** — มี copy อยู่ใน `public/assets/img/logo.png` ของ Laravel แล้ว; เก็บต้นฉบับไว้เพราะยังอยู่ใน git repo
3. **`uploads/`** — ยังว่างอยู่; ถ้า Laravel ต้องการ user uploads ควรใช้ `storage/app/public/` แทน
4. **`panel_update_status.php`** — ใน original ก็ deprecated แล้ว (comment บอกว่า legacy redirect); ใน Laravel ถูกแทนที่ด้วย Livewire ครบ
5. **`api/` folder** — มีแค่ `task_update.php` ไฟล์เดียว ทั้ง folder จะถูก archive พร้อมกัน
6. **ไม่มี timeline.php** ในโปรเจกต์เดิม — `TimelineController` และ `timeline/index.blade.php` ใน Laravel เป็นฟีเจอร์ใหม่ที่สร้างขึ้นระหว่าง migration

---

**รายงานนี้สร้างโดย Claude Code — รอการยืนยันก่อนดำเนินการใด ๆ**
