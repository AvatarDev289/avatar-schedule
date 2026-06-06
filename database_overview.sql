-- =====================================================================
--  Avatar Electric - Project Overview Image  (extra tables)
--  Run AFTER database.sql
--  mysql -u root avatar_schedule < database_overview.sql
--
--  NOTE: project_panels now lives in database_panels.sql (richer schema).
--        Run order:  database.sql -> database_overview.sql -> database_panels.sql
-- =====================================================================

USE `avatar_schedule`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `project_tasks`;
DROP TABLE IF EXISTS `project_milestones`;
DROP TABLE IF EXISTS `delivery_groups`;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
--  Tasks (timeline / Gantt rows)
-- ---------------------------------------------------------------------
CREATE TABLE `project_tasks` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT UNSIGNED NOT NULL,
  `task_name`  VARCHAR(180) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date`   DATE NOT NULL,
  `progress`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `status`     ENUM('pending','in_progress','completed','overdue') NOT NULL DEFAULT 'pending',
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_task_prj` (`project_id`),
  CONSTRAINT `fk_task_prj` FOREIGN KEY (`project_id`)
    REFERENCES `projects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Milestones (key dates)
-- ---------------------------------------------------------------------
CREATE TABLE `project_milestones` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id`     INT UNSIGNED NOT NULL,
  `title`          VARCHAR(160) NOT NULL,
  `milestone_date` DATE NOT NULL,
  `is_done`        TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order`     INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_ms_prj` (`project_id`),
  CONSTRAINT `fk_ms_prj` FOREIGN KEY (`project_id`)
    REFERENCES `projects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Delivery groups (legacy installment list - kept for compatibility)
-- ---------------------------------------------------------------------
CREATE TABLE `delivery_groups` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id`    INT UNSIGNED NOT NULL,
  `group_name`    VARCHAR(160) NOT NULL,
  `delivery_date` DATE DEFAULT NULL,
  `qty`           INT NOT NULL DEFAULT 0,
  `amount`        DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `status`        ENUM('pending','in_progress','completed','overdue') NOT NULL DEFAULT 'pending',
  `remark`        VARCHAR(255) DEFAULT NULL,
  `sort_order`    INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_dg_prj` (`project_id`),
  CONSTRAINT `fk_dg_prj` FOREIGN KEY (`project_id`)
    REFERENCES `projects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  Sample data
-- =====================================================================

-- ---- Tasks ----
INSERT INTO `project_tasks` (`project_id`,`task_name`,`start_date`,`end_date`,`progress`,`status`,`sort_order`) VALUES
 (1,'สำรวจหน้างาน & ออกแบบเบื้องต้น','2026-01-05','2026-01-18',100,'completed',1),
 (1,'ออกแบบระบบไฟฟ้า & อนุมัติแบบ','2026-01-15','2026-02-05',100,'completed',2),
 (1,'จัดซื้ออุปกรณ์ & ตู้ควบคุม','2026-02-01','2026-02-22',100,'completed',3),
 (1,'เดินระบบไฟฟ้าแรงสูง-แรงต่ำ','2026-02-18','2026-03-15',100,'completed',4),
 (1,'ทดสอบระบบ & ส่งมอบ','2026-03-16','2026-03-28',100,'completed',5),
 (2,'ออกแบบระบบ PLC & I/O List','2026-02-01','2026-02-28',100,'completed',1),
 (2,'จัดทำตู้ Control','2026-03-01','2026-04-10',90,'in_progress',2),
 (2,'เขียนโปรแกรม PLC & SCADA','2026-03-15','2026-04-30',60,'in_progress',3),
 (2,'ติดตั้งหน้างาน & Commissioning','2026-05-01','2026-05-15',0,'pending',4),
 (3,'สำรวจหลังคา & ออกแบบ','2026-03-10','2026-03-25',100,'completed',1),
 (3,'ติดตั้งโครงสร้างรองรับ (Mounting)','2026-03-26','2026-04-25',100,'completed',2),
 (3,'ติดตั้งแผง Solar 500kW','2026-04-26','2026-05-20',80,'in_progress',3),
 (3,'ติดตั้ง Inverter & สายไฟ DC/AC','2026-05-21','2026-06-05',30,'in_progress',4),
 (3,'ทดสอบ & ขนานไฟการไฟฟ้า','2026-06-06','2026-06-10',0,'pending',5),
 (6,'ขออนุญาตการไฟฟ้า','2026-05-01','2026-06-10',60,'in_progress',1),
 (6,'งานโยธา & ฐานราก','2026-05-20','2026-06-20',30,'in_progress',2),
 (6,'ติดตั้งหัวชาร์จ 8 หัว','2026-06-21','2026-07-20',0,'pending',3),
 (6,'ทดสอบ & เปิดใช้งาน','2026-07-21','2026-07-31',0,'pending',4);

-- ---- Milestones ----
INSERT INTO `project_milestones` (`project_id`,`title`,`milestone_date`,`is_done`,`sort_order`) VALUES
 (1,'เริ่มโครงการ','2026-01-05',1,1),
 (1,'อนุมัติแบบ Construction','2026-02-05',1,2),
 (1,'ติดตั้งแล้วเสร็จ','2026-03-15',1,3),
 (1,'ส่งมอบงาน (Hand over)','2026-03-28',1,4),
 (2,'เริ่มโครงการ','2026-02-01',1,1),
 (2,'อนุมัติแบบระบบควบคุม','2026-02-28',1,2),
 (2,'ตู้ Control พร้อมส่ง','2026-04-10',0,3),
 (2,'Commissioning เสร็จ','2026-05-15',0,4),
 (3,'เริ่มโครงการ','2026-03-10',1,1),
 (3,'ติดตั้งโครงสร้างเสร็จ','2026-04-25',1,2),
 (3,'ติดตั้งแผงครบ 100%','2026-05-20',0,3),
 (3,'COD / ขนานไฟ','2026-06-10',0,4),
 (6,'เซ็นสัญญา','2026-05-01',1,1),
 (6,'อนุมัติจากการไฟฟ้า','2026-06-10',0,2),
 (6,'เปิดให้บริการ','2026-07-31',0,3);

-- ---- Delivery groups (legacy) ----
INSERT INTO `delivery_groups` (`project_id`,`group_name`,`delivery_date`,`qty`,`amount`,`status`,`remark`,`sort_order`) VALUES
 (1,'งวดที่ 1 - งานเดินระบบ','2026-03-15',1,1700000,'completed','รับเงินแล้ว',1),
 (1,'งวดที่ 2 - ส่งมอบสมบูรณ์','2026-03-28',1,1150000,'completed','ปิดงาน',2),
 (2,'งวดที่ 1 - ออกแบบ','2026-02-28',1,350000,'completed','',1),
 (2,'งวดที่ 2 - ส่งตู้ Control','2026-04-10',4,900000,'in_progress','รอชิ้นส่วนนำเข้า',2),
 (2,'งวดที่ 3 - ส่งมอบงาน','2026-05-15',1,500000,'pending','',3),
 (3,'งวดที่ 1 - โครงสร้าง','2026-04-25',1,1400000,'completed','',1),
 (3,'งวดที่ 2 - แผง + Inverter','2026-05-20',1,2100000,'in_progress','',2),
 (3,'งวดที่ 3 - COD','2026-06-10',1,700000,'pending','',3),
 (6,'งวดที่ 1 - งานโยธา','2026-06-20',1,900000,'in_progress','',1),
 (6,'งวดที่ 2 - ติดตั้งหัวจ่าย','2026-07-31',8,2200000,'pending','',2);

-- end of file
