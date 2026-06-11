-- =====================================================================
--  Avatar Electric - Schedule of Project Dashboard
--  Database schema + sample data
--  MySQL 5.7+ / MariaDB 10+
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `avatar_schedule`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `avatar_schedule`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `project_attachments`;
DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `projects`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `departments`;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
--  Departments
-- ---------------------------------------------------------------------
CREATE TABLE `departments` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(120) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dept_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Users / Responsible persons
-- ---------------------------------------------------------------------
CREATE TABLE `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(120) NOT NULL,
  `email`         VARCHAR(150) DEFAULT NULL,
  `phone`         VARCHAR(40)  DEFAULT NULL,
  `department_id` INT UNSIGNED DEFAULT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_user_dept` (`department_id`),
  CONSTRAINT `fk_user_dept` FOREIGN KEY (`department_id`)
    REFERENCES `departments`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Projects
-- ---------------------------------------------------------------------
CREATE TABLE `projects` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_no`     VARCHAR(40)  NOT NULL,
  `project_name`   VARCHAR(255) NOT NULL,
  `description`    TEXT         DEFAULT NULL,
  `customer`       VARCHAR(180) DEFAULT NULL,
  `department_id`  INT UNSIGNED DEFAULT NULL,
  `responsible_id` INT UNSIGNED DEFAULT NULL,
  `start_date`     DATE         DEFAULT NULL,
  `due_date`       DATE         DEFAULT NULL,
  `delivery_date`  DATE         DEFAULT NULL,
  `completed_date` DATE         DEFAULT NULL,
  -- stored status (may be overridden / recomputed by application logic)
  `status`         ENUM('pending','in_progress','near_due','completed','overdue')
                   NOT NULL DEFAULT 'pending',
  `progress`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `amount`         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `remark`         TEXT         DEFAULT NULL,
  `attachment`     VARCHAR(255) DEFAULT NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_no` (`project_no`),
  KEY `fk_prj_dept` (`department_id`),
  KEY `fk_prj_user` (`responsible_id`),
  KEY `idx_due_date` (`due_date`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_prj_dept` FOREIGN KEY (`department_id`)
    REFERENCES `departments`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_prj_user` FOREIGN KEY (`responsible_id`)
    REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Project attachments (optional - multiple files per project)
-- ---------------------------------------------------------------------
CREATE TABLE `project_attachments` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT UNSIGNED NOT NULL,
  `file_path`  VARCHAR(255) NOT NULL,
  `file_name`  VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_att_prj` (`project_id`),
  CONSTRAINT `fk_att_prj` FOREIGN KEY (`project_id`)
    REFERENCES `projects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Activity logs (optional - audit trail)
-- ---------------------------------------------------------------------
CREATE TABLE `activity_logs` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT UNSIGNED DEFAULT NULL,
  `action`     VARCHAR(40)  NOT NULL,
  `detail`     VARCHAR(255) DEFAULT NULL,
  `actor`      VARCHAR(120) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_log_prj` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  Sample data
-- =====================================================================

INSERT INTO `departments` (`id`,`name`) VALUES
  (1,'Electrical Engineering'),
  (2,'Automation & Control'),
  (3,'Solar & Energy'),
  (4,'Installation & Service'),
  (5,'Sales & Project Management');

INSERT INTO `users` (`id`,`name`,`email`,`phone`,`department_id`) VALUES
  (1,'สมชาย วัฒนกุล','somchai@avatar-electric.com','081-111-1111',1),
  (2,'อรพรรณ ศรีสุข','orapan@avatar-electric.com','081-222-2222',2),
  (3,'ธนกร ไพศาล','thanakorn@avatar-electric.com','081-333-3333',3),
  (4,'ปวีณา จันทร์เพ็ญ','paweena@avatar-electric.com','081-444-4444',4),
  (5,'วิชัย ตันติกุล','wichai@avatar-electric.com','081-555-5555',5),
  (6,'นภัสสร โพธิ์ทอง','napatsorn@avatar-electric.com','081-666-6666',2),
  (7,'กฤษณะ มหาชัย','kritsana@avatar-electric.com','081-777-7777',1);

-- NOTE: dates are written relative to mid-2026 so the dashboard shows a
--       realistic mix of completed / in-progress / near-due / overdue work.
INSERT INTO `projects`
 (`project_no`,`project_name`,`description`,`customer`,`department_id`,`responsible_id`,
  `start_date`,`due_date`,`delivery_date`,`completed_date`,`status`,`progress`,`amount`,`remark`) VALUES
 ('PRJ-2026-001','ติดตั้งระบบไฟฟ้าโรงงาน Phase 1','งานเดินระบบไฟฟ้าแรงสูง-แรงต่ำ โรงงานผลิตชิ้นส่วน','บ. ไทยออโต้พาร์ท จำกัด',1,1,'2026-01-05','2026-03-30','2026-03-28','2026-03-28','completed',100,2850000.00,'ส่งมอบครบถ้วน'),
 ('PRJ-2026-002','ระบบควบคุมอัตโนมัติสายการผลิต','ออกแบบและติดตั้งตู้ควบคุม PLC + SCADA','บ. ฟู้ดเทค อินดัสทรี',2,2,'2026-02-01','2026-05-15',NULL,NULL,'in_progress',65,1750000.00,'รอชิ้นส่วนนำเข้า'),
 ('PRJ-2026-003','ติดตั้ง Solar Rooftop 500kW','ระบบโซลาร์บนหลังคาโรงงาน','บ. กรีนพาวเวอร์ จำกัด',3,3,'2026-03-10','2026-06-10',NULL,NULL,'near_due',55,4200000.00,'รอ inverter ชุดสุดท้าย'),
 ('PRJ-2026-004','ปรับปรุงระบบไฟฟ้าอาคารสำนักงาน','เปลี่ยนตู้ MDB และระบบแสงสว่าง LED','บ. ซิตี้ออฟฟิศ จำกัด',1,7,'2026-01-20','2026-04-20','2026-04-22','2026-04-22','completed',100,980000.00,'ลูกค้าตอบรับดี'),
 ('PRJ-2026-005','บำรุงรักษาหม้อแปลงไฟฟ้า','สัญญาบำรุงรักษาประจำปี','บ. เมกะมอลล์ จำกัด',4,4,'2026-04-01','2026-05-25',NULL,NULL,'overdue',80,650000.00,'ล่าช้าจากการเข้าพื้นที่'),
 ('PRJ-2026-006','ติดตั้งระบบ EV Charger','สถานีชาร์จรถยนต์ไฟฟ้า 8 หัวจ่าย','บ. อีวีสเตชั่น จำกัด',3,3,'2026-05-01','2026-07-31',NULL,NULL,'in_progress',30,3100000.00,'อยู่ระหว่างขออนุญาตการไฟฟ้า'),
 ('PRJ-2026-007','ระบบไฟฟ้าสำรอง Generator','ติดตั้งเครื่องกำเนิดไฟฟ้า 1000kVA','รพ. ศรีนครินทร์',4,4,'2026-02-15','2026-04-30','2026-05-02','2026-05-02','completed',100,5400000.00,'งานสำคัญ ผ่านการทดสอบ'),
 ('PRJ-2026-008','ตู้ MDB อาคารคลังสินค้า','ผลิตและติดตั้งตู้จ่ายไฟหลัก','บ. โลจิสติกส์โปร',1,1,'2026-04-10','2026-06-15',NULL,NULL,'near_due',70,1250000.00,''),
 ('PRJ-2026-009','ระบบ Building Automation','ควบคุมแอร์-ไฟ-ระบบความปลอดภัยอัตโนมัติ','บ. สมาร์ทบิลดิ้ง',2,6,'2026-03-01','2026-08-30',NULL,NULL,'in_progress',45,2980000.00,'แบ่งส่งมอบ 3 งวด'),
 ('PRJ-2026-010','ติดตั้งระบบไฟส่องสว่างถนน','โคมไฟ LED Solar Cell 120 ต้น','เทศบาลเมืองสมุทรปราการ',3,3,'2026-01-15','2026-03-15','2026-03-10','2026-03-10','completed',100,1680000.00,'ผ่านการตรวจรับ'),
 ('PRJ-2026-011','ปรับปรุงระบบกราวด์','ระบบสายดินและป้องกันฟ้าผ่า','บ. ดาต้าเซ็นเตอร์ไทย',1,7,'2026-05-10','2026-06-12',NULL,NULL,'near_due',60,720000.00,'งานเร่งด่วน'),
 ('PRJ-2026-012','ระบบ UPS ห้อง Server','ติดตั้ง UPS 100kVA พร้อมแบตเตอรี่','บ. ไอทีโซลูชั่น',2,2,'2026-03-20','2026-05-10',NULL,NULL,'overdue',85,1450000.00,'รอแบตเตอรี่นำเข้า ล่าช้า'),
 ('PRJ-2026-013','ติดตั้งมอเตอร์และอินเวอร์เตอร์','งานปรับปรุงสายพานลำเลียง','บ. ซีเมนต์ไทย',2,6,'2026-04-25','2026-07-20',NULL,NULL,'in_progress',25,890000.00,''),
 ('PRJ-2026-014','โซลาร์ฟาร์ม 2MW','โครงการโรงไฟฟ้าพลังงานแสงอาทิตย์','บ. รีนิวเอเบิล เอนเนอร์ยี',3,3,'2026-02-01','2026-09-30',NULL,NULL,'in_progress',40,18500000.00,'โครงการขนาดใหญ่'),
 ('PRJ-2026-015','ระบบไฟฟ้าห้องเย็น','งานไฟฟ้าและควบคุมห้องเย็น -20°C','บ. ฟรอเซ่นฟู้ด',1,1,'2026-05-05','2026-06-08',NULL,NULL,'near_due',75,1320000.00,''),
 ('PRJ-2026-016','ตรวจสอบและรับรองระบบไฟฟ้า','ตรวจสอบประจำปีตามกฎหมาย','บ. อุตสาหกรรมพลาสติก',4,4,'2026-06-01','2026-06-30',NULL,NULL,'in_progress',10,180000.00,'งานบริการ'),
 ('PRJ-2026-017','ระบบไฟฟ้าโชว์รูมรถยนต์','งานไฟฟ้าและไฟตกแต่งโชว์รูม','บ. ออโต้แกลเลอรี่',1,7,'2025-12-01','2026-02-28','2026-02-25','2026-02-25','completed',100,2240000.00,'ส่งมอบก่อนกำหนด'),
 ('PRJ-2026-018','Smart Factory IoT','ติดตั้งเซ็นเซอร์ IoT ตรวจสอบเครื่องจักร','บ. แมนูแฟคเจอริ่ง 4.0',2,6,'2026-04-15','2026-08-15',NULL,NULL,'in_progress',35,3600000.00,'เฟสแรกเซ็นเซอร์ 50 จุด'),
 ('PRJ-2026-019','ระบบดับเพลิงอัตโนมัติ','ติดตั้งระบบ Fire Alarm + Sprinkler control','บ. เซฟตี้เฟิร์ส',4,4,'2026-03-25','2026-05-20',NULL,NULL,'overdue',90,2100000.00,'รอใบรับรอง ล่าช้าเล็กน้อย'),
 ('PRJ-2026-020','งานเดินสายระบบเครือข่าย','โครงสร้างพื้นฐานสาย LAN/Fiber','บ. เทเลคอมเซอร์วิส',2,2,'2026-06-10','2026-08-10',NULL,NULL,'pending',0,560000.00,'รอเริ่มงาน เซ็นสัญญาแล้ว'),
 ('PRJ-2026-021','ปรับปรุงตู้สวิตช์เกียร์','เปลี่ยน Air Circuit Breaker','บ. เพาเวอร์แพลนท์',1,1,'2026-06-15','2026-07-15',NULL,NULL,'pending',0,1980000.00,'รอแผนหยุดเครื่อง'),
 ('PRJ-2026-022','ระบบมอนิเตอร์พลังงาน','ติดตั้ง Power Meter + Dashboard','บ. อีโคแฟคทอรี่',3,3,'2026-05-20','2026-07-05',NULL,NULL,'in_progress',20,740000.00,'');

-- end of file
