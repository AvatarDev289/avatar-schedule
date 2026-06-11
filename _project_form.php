<?php
/**
 * _project_form.php — shared add/edit form markup.
 * Expects: $p (array of project values, may be empty defaults),
 *          $isEdit (bool),
 *          $existingAttachments (array, edit mode only)
 */

$thaiProvinces = [
    'กระบี่','กรุงเทพมหานคร','กาญจนบุรี','กาฬสินธุ์','กำแพงเพชร',
    'ขอนแก่น','จันทบุรี','ฉะเชิงเทรา','ชลบุรี','ชัยนาท','ชัยภูมิ','ชุมพร',
    'เชียงราย','เชียงใหม่','ตรัง','ตราด','ตาก',
    'นครนายก','นครปฐม','นครพนม','นครราชสีมา','นครศรีธรรมราช','นครสวรรค์',
    'นนทบุรี','นราธิวาส','น่าน','บึงกาฬ','บุรีรัมย์',
    'ปทุมธานี','ประจวบคีรีขันธ์','ปราจีนบุรี','ปัตตานี',
    'พระนครศรีอยุธยา','พะเยา','พังงา','พัทลุง','พิจิตร','พิษณุโลก',
    'เพชรบุรี','เพชรบูรณ์','แพร่','ภูเก็ต',
    'มหาสารคาม','มุกดาหาร','แม่ฮ่องสอน',
    'ยโสธร','ยะลา','ร้อยเอ็ด','ระนอง','ระยอง','ราชบุรี',
    'ลพบุรี','ลำปาง','ลำพูน','เลย','ศรีสะเกษ','สกลนคร','สงขลา','สตูล',
    'สมุทรปราการ','สมุทรสงคราม','สมุทรสาคร','สระแก้ว','สระบุรี','สิงห์บุรี',
    'สุโขทัย','สุพรรณบุรี','สุราษฎร์ธานี','สุรินทร์',
    'หนองคาย','หนองบัวลำภู','อ่างทอง','อำนาจเจริญ',
    'อุดรธานี','อุตรดิตถ์','อุทัยธานี','อุบลราชธานี',
];
$existingAttachments = $existingAttachments ?? [];
?>
<form method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
  <div class="grid-form">

    <!-- ข้อมูลทั่วไป -->
    <div class="col-12">
      <div class="panel">
        <div class="panel-head"><i class="bi bi-info-circle"></i> ข้อมูลทั่วไป</div>
        <div class="panel-body">
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label">Project No. <span class="req">*</span></label>
              <input name="project_no" class="form-control" required value="<?= e($p['project_no'] ?? '') ?>" placeholder="เลข SO">
            </div>
            <div class="col-md-9">
              <label class="form-label">ชื่อโครงการ <span class="req">*</span></label>
              <input name="project_name" class="form-control" required value="<?= e($p['project_name'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">ลูกค้า</label>
              <input name="customer" class="form-control" value="<?= e($p['customer'] ?? '') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Sale</label>
              <input name="sale_name" class="form-control" placeholder="ชื่อพนักงานขาย"
                     value="<?= e($p['sale_name'] ?? '') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">สถานที่จัดส่ง</label>
              <input name="delivery_location" id="deliveryLocationInput"
                     class="form-control" list="provinceList"
                     placeholder="พิมพ์หรือเลือกจังหวัด..."
                     autocomplete="off"
                     value="<?= e($p['delivery_location'] ?? '') ?>">
              <datalist id="provinceList">
                <?php foreach ($thaiProvinces as $pv): ?>
                  <option value="<?= e($pv) ?>">
                <?php endforeach; ?>
              </datalist>
            </div>
            <div class="col-md-6">
              <label class="form-label">รายละเอียดสถานที่จัดส่ง <small class="text-muted fw-normal">(ไม่บังคับ)</small></label>
              <textarea name="delivery_location_detail" class="form-control" rows="2"
                        placeholder="เช่น ที่อยู่, ชื่ออาคาร, ชั้น, หน่วยงานผู้รับ — หากไม่มีรายละเอียดเพิ่มเติมข้ามช่องนี้ได้"
              ><?= e($p['delivery_location_detail'] ?? '') ?></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">เงื่อนไขการชำระ</label>
              <input name="payment_terms" class="form-control" placeholder="เช่น 30 วัน, ชำระล่วงหน้า 30%..."
                     value="<?= e($p['payment_terms'] ?? '') ?>">
            </div>
            <?php if (!empty($isEdit)): ?>
            <div class="col-12">
              <label class="form-label">รายละเอียดงาน</label>
              <textarea name="description" class="form-control" rows="2"><?= e($p['description'] ?? '') ?></textarea>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- กำหนดการ -->
    <div class="col-12">
      <div class="panel">
        <div class="panel-head"><i class="bi bi-calendar-range"></i> กำหนดการ<?= !empty($isEdit) ? ' และสถานะ' : '' ?></div>
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
            <?php if (!empty($isEdit)): ?>
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
                     value="<?= e($p['progress'] ?? '0') ?>"
                     <?= (!empty($isEdit) && !empty($panelProgExists)) ? 'readonly title="คำนวณอัตโนมัติจากตู้"' : '' ?>>
              <?php if (!empty($isEdit) && !empty($panelProgExists)): ?>
                <small class="text-warning d-block mt-1">
                  <i class="bi bi-info-circle"></i> คำนวณอัตโนมัติจากตู้ในโครงการ — ไม่สามารถแก้ไขด้วยตนเอง
                </small>
              <?php else: ?>
                <small class="text-muted d-block mt-1">หากมีตู้ในโครงการ ค่านี้จะถูกคำนวณอัตโนมัติ</small>
              <?php endif; ?>
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
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- หมายเหตุ + ไฟล์แนบ -->
    <div class="col-12">
      <div class="panel">
        <div class="panel-head"><i class="bi bi-paperclip"></i> หมายเหตุ และเอกสารแนบ</div>
        <div class="panel-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">หมายเหตุ</label>
              <textarea name="remark" class="form-control" rows="2"><?= e($p['remark'] ?? '') ?></textarea>
            </div>

            <!-- Multiple file upload -->
            <div class="col-12">
              <label class="form-label">
                <i class="bi bi-file-earmark-arrow-up"></i> แนบไฟล์เพิ่มเติม
                <small class="text-muted fw-normal">(PDF, Excel, รูปภาพ — ไม่จำกัดจำนวน)</small>
              </label>
              <div class="file-drop-zone" id="fileDropZone">
                <input type="file" id="attachmentsInput" name="attachments[]"
                       multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.png,.jpg,.jpeg,.gif,.webp,.dwg,.zip,.rar"
                       class="file-drop-input">
                <div class="file-drop-label">
                  <i class="bi bi-cloud-upload fs-3 text-muted"></i>
                  <div class="mt-1">คลิกเพื่อเลือกไฟล์ หรือลากไฟล์มาวางที่นี่</div>
                  <small class="text-muted">PDF, Word, Excel, รูปภาพ, DWG, ZIP</small>
                </div>
                <div id="fileList" class="file-list mt-2"></div>
              </div>
            </div>

            <?php if ($existingAttachments): ?>
            <!-- Existing attachments (edit mode) -->
            <div class="col-12">
              <label class="form-label"><i class="bi bi-paperclip"></i> ไฟล์แนบที่มีอยู่ (<?= count($existingAttachments) ?> ไฟล์)</label>
              <div class="existing-attachments">
                <?php foreach ($existingAttachments as $att): ?>
                  <?php
                    $ext  = strtolower(pathinfo($att['original_name'], PATHINFO_EXTENSION));
                    $icon = match(true) {
                        in_array($ext, ['pdf'])                  => 'bi-file-earmark-pdf text-danger',
                        in_array($ext, ['xls','xlsx','csv'])     => 'bi-file-earmark-excel text-success',
                        in_array($ext, ['doc','docx'])           => 'bi-file-earmark-word text-primary',
                        in_array($ext, ['png','jpg','jpeg','gif','webp']) => 'bi-file-earmark-image text-info',
                        in_array($ext, ['dwg'])                  => 'bi-file-earmark-code text-warning',
                        in_array($ext, ['zip','rar'])            => 'bi-file-earmark-zip text-secondary',
                        default                                  => 'bi-file-earmark text-muted',
                    };
                    $kb = $att['file_size'] > 0 ? round($att['file_size'] / 1024, 1) . ' KB' : '';
                  ?>
                  <div class="att-row" id="att-<?= (int)$att['id'] ?>">
                    <i class="bi <?= $icon ?> att-icon"></i>
                    <a href="<?= e(UPLOAD_URL . '/' . ($att['file_path'] ?? $att['file_name'])) ?>" target="_blank" class="att-name">
                      <?= e($att['original_name'] ?? $att['file_name']) ?>
                    </a>
                    <?php if ($kb): ?><span class="att-size text-muted"><?= $kb ?></span><?php endif; ?>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-att-del"
                            data-id="<?= (int)$att['id'] ?>"
                            data-name="<?= e($att['original_name'] ?? $att['file_name']) ?>">
                      <i class="bi bi-trash3"></i>
                    </button>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($isEdit) && !empty($p['attachment'])): ?>
            <!-- Legacy single attachment -->
            <div class="col-12">
              <small class="text-muted">
                ไฟล์แนบเดิม (legacy):
                <a href="<?= e(UPLOAD_URL . '/' . $p['attachment']) ?>" target="_blank"><?= e($p['attachment']) ?></a>
              </small>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 d-flex gap-2">
      <button class="btn btn-success btn-lg"><i class="bi bi-save"></i> บันทึก</button>
      <a href="<?= !empty($isEdit) ? 'project_view.php?id=' . (int)($p['id'] ?? 0) : 'projects.php' ?>" class="btn btn-light btn-lg">ยกเลิก</a>
    </div>
  </div>
</form>

<style>
.file-drop-zone {
  position: relative;
  border: 2px dashed #CBD5E1;
  border-radius: 10px;
  padding: 20px;
  text-align: center;
  cursor: pointer;
  transition: border-color .2s, background .2s;
  background: #F8FAFC;
}
.file-drop-zone.dragover { border-color: #FF7A00; background: #FFF7ED; }
.file-drop-input {
  position: absolute; inset: 0; width: 100%; height: 100%;
  opacity: 0; cursor: pointer; z-index: 2;
}
.file-drop-label { pointer-events: none; }
.file-list { text-align: left; }
.file-item {
  display: flex; align-items: center; gap: 8px;
  padding: 5px 10px; border-radius: 6px; background: #fff;
  border: 1px solid #E2E8F0; margin-bottom: 4px; font-size: 13px;
}
.file-item-name { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.file-item-size { color: #94A3B8; white-space: nowrap; }
.file-item-del { border: none; background: none; color: #EF4444; cursor: pointer; padding: 0 4px; font-size: 14px; }
.existing-attachments { display: flex; flex-direction: column; gap: 6px; }
.att-row {
  display: flex; align-items: center; gap: 8px;
  padding: 6px 10px; border-radius: 6px; background: #F8FAFC;
  border: 1px solid #E2E8F0; font-size: 13px;
}
.att-icon { font-size: 16px; flex-shrink: 0; }
.att-name { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-decoration: none; }
.att-name:hover { text-decoration: underline; }
.att-size { white-space: nowrap; font-size: 11px; }
</style>

<script>
(function () {
  var input    = document.getElementById('attachmentsInput');
  var zone     = document.getElementById('fileDropZone');
  var listEl   = document.getElementById('fileList');
  var allFiles = [];

  if (!input) return;

  function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
  }

  function renderList() {
    listEl.innerHTML = '';
    allFiles.forEach(function (f, i) {
      var row = document.createElement('div');
      row.className = 'file-item';
      row.innerHTML =
        '<i class="bi bi-file-earmark"></i>' +
        '<span class="file-item-name" title="' + f.name + '">' + f.name + '</span>' +
        '<span class="file-item-size">' + formatSize(f.size) + '</span>' +
        '<button type="button" class="file-item-del" data-i="' + i + '" title="ลบ"><i class="bi bi-x-lg"></i></button>';
      listEl.appendChild(row);
    });
    listEl.querySelectorAll('.file-item-del').forEach(function (btn) {
      btn.onclick = function () {
        allFiles.splice(parseInt(this.dataset.i), 1);
        syncInput();
        renderList();
      };
    });
  }

  function syncInput() {
    var dt = new DataTransfer();
    allFiles.forEach(function (f) { dt.items.add(f); });
    input.files = dt.files;
  }

  input.addEventListener('change', function () {
    Array.from(this.files).forEach(function (f) { allFiles.push(f); });
    syncInput();
    renderList();
  });

  zone.addEventListener('dragover',  function (e) { e.preventDefault(); zone.classList.add('dragover'); });
  zone.addEventListener('dragleave', function ()  { zone.classList.remove('dragover'); });
  zone.addEventListener('drop', function (e) {
    e.preventDefault();
    zone.classList.remove('dragover');
    Array.from(e.dataTransfer.files).forEach(function (f) { allFiles.push(f); });
    syncInput();
    renderList();
  });
})();

// Delete existing attachment (AJAX)
document.querySelectorAll('.btn-att-del').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var id   = this.dataset.id;
    var name = this.dataset.name;
    if (!confirm('ลบไฟล์ "' + name + '" ?')) return;
    var self = this;
    fetch('api/attachment_delete.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({id: parseInt(id)})
    })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      if (d.ok) {
        var row = document.getElementById('att-' + id);
        if (row) row.remove();
      } else {
        alert('ลบไม่สำเร็จ: ' + (d.error || ''));
      }
    });
  });
});
</script>
