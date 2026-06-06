<?php
/**
 * _project_form.php — shared add/edit form markup.
 * Expects: $p (array of project values, may be empty defaults),
 *          $departments, $users, $action (form action url), $isEdit (bool)
 */
?>
<form method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
  <div class="grid-form">

    <div class="col-12">
      <div class="panel">
        <div class="panel-head"><i class="bi bi-info-circle"></i> ข้อมูลทั่วไป</div>
        <div class="panel-body">
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label">Project No. <span class="req">*</span></label>
              <input name="project_no" class="form-control" required value="<?= e($p['project_no'] ?? '') ?>">
            </div>
            <div class="col-md-9">
              <label class="form-label">ชื่อโครงการ <span class="req">*</span></label>
              <input name="project_name" class="form-control" required value="<?= e($p['project_name'] ?? '') ?>">
            </div>
            <div class="col-12">
              <label class="form-label">รายละเอียดงาน</label>
              <textarea name="description" class="form-control" rows="2"><?= e($p['description'] ?? '') ?></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">ลูกค้า</label>
              <input name="customer" class="form-control" value="<?= e($p['customer'] ?? '') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">แผนก</label>
              <select name="department_id" class="form-select">
                <option value="">- เลือก -</option>
                <?php foreach ($departments as $d): ?>
                  <option value="<?= (int)$d['id'] ?>" <?= (string)($p['department_id']??'')===(string)$d['id']?'selected':'' ?>>
                    <?= e($d['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">ผู้รับผิดชอบ</label>
              <select name="responsible_id" class="form-select">
                <option value="">- เลือก -</option>
                <?php foreach ($users as $u): ?>
                  <option value="<?= (int)$u['id'] ?>" <?= (string)($p['responsible_id']??'')===(string)$u['id']?'selected':'' ?>>
                    <?= e($u['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="panel">
        <div class="panel-head"><i class="bi bi-calendar-range"></i> กำหนดการ และสถานะ</div>
        <div class="panel-body">
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label">วันเริ่ม</label>
              <input type="date" name="start_date" class="form-control" value="<?= e($p['start_date'] ?? '') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">กำหนดส่ง (Due)</label>
              <input type="date" name="due_date" class="form-control" value="<?= e($p['due_date'] ?? '') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">วันส่งมอบ</label>
              <input type="date" name="delivery_date" class="form-control" value="<?= e($p['delivery_date'] ?? '') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">วันเสร็จงาน (Completed)</label>
              <input type="date" name="completed_date" class="form-control" value="<?= e($p['completed_date'] ?? '') ?>">
              <small class="text-muted">หากระบุ ระบบจะถือว่าเสร็จแล้ว</small>
            </div>
            <div class="col-md-3">
              <label class="form-label">ความคืบหน้า (%)</label>
              <input type="number" name="progress" class="form-control" min="0" max="100"
                     value="<?= e($p['progress'] ?? '0') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">สถานะ (ตั้งค่าเอง)</label>
              <select name="status" class="form-select">
                <?php foreach (status_labels() as $k => $lb): ?>
                  <option value="<?= e($k) ?>" <?= ($p['status']??'pending')===$k?'selected':'' ?>><?= e($lb) ?></option>
                <?php endforeach; ?>
              </select>
              <small class="text-muted">สถานะจริงจะคำนวณจากวันที่อัตโนมัติ</small>
            </div>
            <div class="col-md-6">
              <label class="form-label">มูลค่างาน (บาท)</label>
              <input type="number" step="0.01" name="amount" class="form-control"
                     value="<?= e($p['amount'] ?? '0') ?>">
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="panel">
        <div class="panel-head"><i class="bi bi-paperclip"></i> หมายเหตุ และเอกสารแนบ</div>
        <div class="panel-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">หมายเหตุ</label>
              <textarea name="remark" class="form-control" rows="2"><?= e($p['remark'] ?? '') ?></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">ไฟล์แนบ (PDF/รูป/เอกสาร)</label>
              <input type="file" name="attachment" class="form-control">
              <?php if (!empty($p['attachment'])): ?>
                <small class="d-block mt-1">
                  ไฟล์ปัจจุบัน:
                  <a href="<?= e(UPLOAD_URL . '/' . $p['attachment']) ?>" target="_blank"><?= e($p['attachment']) ?></a>
                </small>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 d-flex gap-2">
      <button class="btn btn-success btn-lg"><i class="bi bi-save"></i> บันทึก</button>
      <a href="projects.php" class="btn btn-light btn-lg">ยกเลิก</a>
    </div>
  </div>
</form>
