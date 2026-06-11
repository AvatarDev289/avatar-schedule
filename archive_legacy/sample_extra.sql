-- =====================================================================
--  Avatar Electric - Extra demo data (full end-to-end examples)
--  Safe to re-run: it removes its own DEMO-* projects first.
--  mysql -u root avatar_schedule < sample_extra.sql
-- =====================================================================
USE `avatar_schedule`;

-- clean previous demo rows (panels/tasks/milestones cascade via FK)
DELETE FROM `projects` WHERE `project_no` LIKE 'DEMO-2026-%';

-- ---------------------------------------------------------------------
-- DEMO 1 : ผลิตตู้ MCC โรงงานน้ำตาล  (กำลังดำเนินการ, หลายตู้ หลาย group)
-- ---------------------------------------------------------------------
INSERT INTO `projects`
 (`project_no`,`project_name`,`description`,`customer`,`department_id`,`responsible_id`,
  `start_date`,`due_date`,`delivery_date`,`completed_date`,`status`,`progress`,`amount`,`remark`)
VALUES
 ('DEMO-2026-001','ผลิตตู้ MCC โรงงานน้ำตาล','ออกแบบ-ผลิต-ติดตั้งตู้ MCC และ VSD สำหรับสายการผลิต',
  'บ. ชูการ์มิลล์ จำกัด',1,1,'2026-04-01','2026-08-15',NULL,NULL,'in_progress',0,6800000.00,'แบ่งส่งมอบ 3 group');
SET @p1 := LAST_INSERT_ID();

INSERT INTO `project_panels`
 (`project_id`,`panel_no`,`panel_name`,`panel_type`,`panel_size`,`delivery_group`,
  `target_delivery_date`,`actual_delivery_date`,`status`,`progress_percent`,`responsible`,`remark`,`sort_order`) VALUES
 (@p1,'MCC-A-01','ตู้ MCC Main','MCC','2200x800x600','A','2026-06-30',NULL,'wiring',65,'สมชาย วัฒนกุล','',1),
 (@p1,'MCC-A-02','ตู้ MCC Feeder 1','MCC','2000x800x600','A','2026-06-30',NULL,'production',45,'สมชาย วัฒนกุล','',2),
 (@p1,'MCC-A-03','ตู้ MCC Feeder 2','MCC','2000x800x600','A','2026-07-05',NULL,'production',45,'กฤษณะ มหาชัย','',3),
 (@p1,'VSD-B-01','ตู้ VSD ปั๊มหอย 1','VSD','1800x700x500','B','2026-07-10',NULL,'material',25,'กฤษณะ มหาชัย','',4),
 (@p1,'VSD-B-02','ตู้ VSD ปั๊มหอย 2','VSD','1800x700x500','B','2026-05-25',NULL,'material',25,'กฤษณะ มหาชัย','รออะไหล่นำเข้า (เลยกำหนด)',5),
 (@p1,'PLC-B-03','ตู้ PLC Control','PLC','1600x800x400','B','2026-07-20',NULL,'design',10,'อรพรรณ ศรีสุข','',6),
 (@p1,'SS-C-01','ตู้ Soft Starter','SS','1400x600x400','C','2026-08-01',NULL,'pending',0,'สมชาย วัฒนกุล','',7),
 (@p1,'CAP-C-02','ตู้ Capacitor Bank','CAP','1600x600x400','C','2026-06-20','2026-06-02','delivered',100,'กฤษณะ มหาชัย','ส่งมอบก่อนกำหนด',8);

INSERT INTO `project_tasks` (`project_id`,`task_name`,`start_date`,`end_date`,`progress`,`status`,`sort_order`) VALUES
 (@p1,'ออกแบบ Single Line & Layout','2026-04-01','2026-04-25',100,'completed',1),
 (@p1,'จัดซื้ออุปกรณ์หลัก','2026-04-20','2026-05-25',90,'in_progress',2),
 (@p1,'ผลิตโครงตู้ & พ่นสี','2026-05-10','2026-06-30',60,'in_progress',3),
 (@p1,'ประกอบ & Wiring','2026-06-15','2026-07-25',30,'in_progress',4),
 (@p1,'ทดสอบ QC & ส่งมอบ','2026-07-20','2026-08-15',0,'pending',5);

INSERT INTO `project_milestones` (`project_id`,`title`,`milestone_date`,`is_done`,`sort_order`) VALUES
 (@p1,'อนุมัติแบบ','2026-04-25',1,1),
 (@p1,'ส่งมอบ Group A','2026-06-30',0,2),
 (@p1,'ส่งมอบ Group B','2026-07-20',0,3),
 (@p1,'ส่งมอบ Group C & ปิดงาน','2026-08-15',0,4);

-- ---------------------------------------------------------------------
-- DEMO 2 : ตู้ DB อาคารพาณิชย์  (เสร็จสมบูรณ์ ทุกตู้ส่งมอบแล้ว)
-- ---------------------------------------------------------------------
INSERT INTO `projects`
 (`project_no`,`project_name`,`description`,`customer`,`department_id`,`responsible_id`,
  `start_date`,`due_date`,`delivery_date`,`completed_date`,`status`,`progress`,`amount`,`remark`)
VALUES
 ('DEMO-2026-002','ตู้ DB อาคารพาณิชย์ 5 ชั้น','ผลิตและติดตั้งตู้ DB ประจำชั้น',
  'บ. ซิตี้พลาซ่า จำกัด',1,7,'2026-02-01','2026-04-30','2026-04-28','2026-04-28','completed',0,1450000.00,'ปิดงานเรียบร้อย');
SET @p2 := LAST_INSERT_ID();

INSERT INTO `project_panels`
 (`project_id`,`panel_no`,`panel_name`,`panel_type`,`panel_size`,`delivery_group`,
  `target_delivery_date`,`actual_delivery_date`,`status`,`progress_percent`,`responsible`,`remark`,`sort_order`) VALUES
 (@p2,'DB-A-01','ตู้ DB ชั้น 1','DB','1600x600x400','A','2026-04-15','2026-04-14','delivered',100,'ปวีณา จันทร์เพ็ญ','',1),
 (@p2,'DB-A-02','ตู้ DB ชั้น 2','DB','1600x600x400','A','2026-04-15','2026-04-14','delivered',100,'ปวีณา จันทร์เพ็ญ','',2),
 (@p2,'DB-A-03','ตู้ DB ชั้น 3','DB','1600x600x400','A','2026-04-20','2026-04-20','delivered',100,'ปวีณา จันทร์เพ็ญ','',3),
 (@p2,'DB-A-04','ตู้ DB ชั้น 4-5','DB','1800x600x400','A','2026-04-28','2026-04-28','delivered',100,'กฤษณะ มหาชัย','',4);

INSERT INTO `project_tasks` (`project_id`,`task_name`,`start_date`,`end_date`,`progress`,`status`,`sort_order`) VALUES
 (@p2,'ออกแบบ & อนุมัติ','2026-02-01','2026-02-20',100,'completed',1),
 (@p2,'ผลิตตู้ทั้งหมด','2026-02-21','2026-04-10',100,'completed',2),
 (@p2,'ติดตั้ง & ส่งมอบ','2026-04-11','2026-04-28',100,'completed',3);

INSERT INTO `project_milestones` (`project_id`,`title`,`milestone_date`,`is_done`,`sort_order`) VALUES
 (@p2,'อนุมัติแบบ','2026-02-20',1,1),
 (@p2,'ส่งมอบครบทุกชั้น','2026-04-28',1,2);

-- ---------------------------------------------------------------------
-- DEMO 3 : ระบบตู้ควบคุมปั๊มน้ำเทศบาล  (รอเริ่มงาน, ยังไม่เริ่ม)
-- ---------------------------------------------------------------------
INSERT INTO `projects`
 (`project_no`,`project_name`,`description`,`customer`,`department_id`,`responsible_id`,
  `start_date`,`due_date`,`delivery_date`,`completed_date`,`status`,`progress`,`amount`,`remark`)
VALUES
 ('DEMO-2026-003','ระบบตู้ควบคุมปั๊มน้ำเทศบาล','ตู้ควบคุมปั๊มน้ำพร้อมระบบ Telemetry',
  'เทศบาลนครนนทบุรี',2,2,'2026-07-01','2026-10-31',NULL,NULL,'pending',0,2300000.00,'เซ็นสัญญาแล้ว รอเริ่มงาน');
SET @p3 := LAST_INSERT_ID();

INSERT INTO `project_panels`
 (`project_id`,`panel_no`,`panel_name`,`panel_type`,`panel_size`,`delivery_group`,
  `target_delivery_date`,`actual_delivery_date`,`status`,`progress_percent`,`responsible`,`remark`,`sort_order`) VALUES
 (@p3,'PMP-A-01','ตู้ควบคุมปั๊ม สถานี 1','PUMP','1800x700x500','A','2026-09-15',NULL,'pending',0,'อรพรรณ ศรีสุข','',1),
 (@p3,'PMP-A-02','ตู้ควบคุมปั๊ม สถานี 2','PUMP','1800x700x500','A','2026-09-30',NULL,'pending',0,'อรพรรณ ศรีสุข','',2),
 (@p3,'TEL-B-01','ตู้ Telemetry & SCADA','SCADA','1400x600x400','B','2026-10-20',NULL,'pending',0,'นภัสสร โพธิ์ทอง','',3);

-- sync project progress from panels
UPDATE `projects` p
JOIN (SELECT project_id, ROUND(AVG(progress_percent)) avg_p FROM `project_panels` GROUP BY project_id) x
  ON x.project_id = p.id
SET p.progress = x.avg_p
WHERE p.project_no LIKE 'DEMO-2026-%';

-- end of file
