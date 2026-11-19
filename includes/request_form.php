<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(403); exit; }   // never run this file on its own
/*
 * includes/request_form.php
 * The blood-request form fields (HTML). One file, used by:
 *   requester/create_request.php   (new request, with file upload)
 *   requester/edit_request.php     (edit, no upload here)
 *   admin/add_request.php          (later)
 *
 * The page that includes this file must set:
 *   $f           values to show in the fields (array)
 *   $bloodGroups, $districts   rows for the drop-downs
 *   $errors      list of error messages
 *   $showUpload  true = show the file box
 *   $uploadRequired (optional) false = the admin may skip the document
 *   $extraTop    (optional) extra HTML shown at the top of the form
 *   $submitLabel text of the button
 *   $cancelUrl   where "Cancel" goes
 */
if (!isset($bloodGroups, $districts, $errors)) { http_response_code(403); exit; }   // not a page of its own
$today = date('Y-m-d');
$urg   = ($f['urgency'] ?? 'Normal') === 'Emergency' ? 'Emergency' : 'Normal';
?>
<?php if ($errors): ?>
    <div class="alert alert-danger" role="alert">
        <strong>Please fix this:</strong>
        <ul class="mb-0 mt-1">
            <?php foreach ($errors as $err): ?><li><?php echo e($err); ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="" class="needs-validation" enctype="multipart/form-data" novalidate>
    <?php echo csrf_field(); ?>
    <?php if (!empty($extraTop)) { echo $extraTop; } /* extra HTML from the page, e.g. admin's "record under" box */ ?>

    <h2 class="bl-form-group-title">Patient</h2>
    <div class="row g-3">
        <div class="col-md-6">
            <label for="patient_name" class="form-label">Patient name</label>
            <input type="text" class="form-control" id="patient_name" name="patient_name" maxlength="100"
                   value="<?php echo e($f['patient_name'] ?? ''); ?>" required>
            <div class="invalid-feedback">Enter the patient name.</div>
        </div>
        <div class="col-md-3">
            <label for="blood_group_id" class="form-label">Blood group needed</label>
            <select class="form-select" id="blood_group_id" name="blood_group_id" required>
                <option value="">Choose...</option>
                <?php foreach ($bloodGroups as $bg): ?>
                    <option value="<?php echo (int)$bg['blood_group_id']; ?>"<?php echo selected($f['blood_group_id'] ?? '', $bg['blood_group_id']); ?>><?php echo e($bg['group_name']); ?></option>
                <?php endforeach; ?>
            </select>
            <div class="invalid-feedback">Choose a blood group.</div>
        </div>
        <div class="col-md-3">
            <label for="bags_required" class="form-label">Blood bags needed</label>
            <input type="number" class="form-control" id="bags_required" name="bags_required" min="1" max="20"
                   value="<?php echo e($f['bags_required'] ?? '1'); ?>" required>
            <div class="invalid-feedback">Enter a number from 1 to 20.</div>
        </div>
        <div class="col-12">
            <label for="medical_condition" class="form-label">Medical condition</label>
            <input type="text" class="form-control" id="medical_condition" name="medical_condition" maxlength="255"
                   placeholder="e.g. Heart surgery, dengue, accident" value="<?php echo e($f['medical_condition'] ?? ''); ?>" required>
            <div class="invalid-feedback">Describe the medical condition.</div>
        </div>
    </div>

    <h2 class="bl-form-group-title">Hospital</h2>
    <div class="row g-3">
        <div class="col-md-6">
            <label for="hospital_name" class="form-label">Hospital name</label>
            <input type="text" class="form-control" id="hospital_name" name="hospital_name" maxlength="150"
                   value="<?php echo e($f['hospital_name'] ?? ''); ?>" required>
            <div class="invalid-feedback">Enter the hospital name.</div>
        </div>
        <div class="col-md-6">
            <label for="hospital_address" class="form-label">Hospital address</label>
            <input type="text" class="form-control" id="hospital_address" name="hospital_address" maxlength="255"
                   value="<?php echo e($f['hospital_address'] ?? ''); ?>" required>
            <div class="invalid-feedback">Enter the hospital address.</div>
        </div>
        <div class="col-md-6">
            <label for="district_id" class="form-label">District</label>
            <select class="form-select" id="district_id" name="district_id" required>
                <option value="">Choose...</option>
                <?php foreach ($districts as $di): ?>
                    <option value="<?php echo (int)$di['district_id']; ?>"<?php echo selected($f['district_id'] ?? '', $di['district_id']); ?>><?php echo e($di['district_name']); ?></option>
                <?php endforeach; ?>
            </select>
            <div class="invalid-feedback">Choose the district.</div>
        </div>
        <div class="col-md-6">
            <label for="area" class="form-label">Area</label>
            <input type="text" class="form-control" id="area" name="area" maxlength="100"
                   value="<?php echo e($f['area'] ?? ''); ?>" required>
            <div class="invalid-feedback">Enter the area.</div>
        </div>
    </div>

    <h2 class="bl-form-group-title">Contact person</h2>
    <div class="row g-3">
        <div class="col-md-6">
            <label for="requester_name" class="form-label">Your name</label>
            <input type="text" class="form-control" id="requester_name" name="requester_name" maxlength="100"
                   value="<?php echo e($f['requester_name'] ?? ''); ?>" required>
            <div class="invalid-feedback">Enter your name.</div>
        </div>
        <div class="col-md-6">
            <label for="contact_phone" class="form-label">Contact number</label>
            <input type="tel" class="form-control" id="contact_phone" name="contact_phone" maxlength="20"
                   pattern="[0-9+\- ]{7,20}" value="<?php echo e($f['contact_phone'] ?? ''); ?>" required>
            <div class="invalid-feedback">Enter a valid phone number.</div>
        </div>
    </div>

    <h2 class="bl-form-group-title">When is the blood needed?</h2>
    <div class="mb-3">
        <input type="radio" class="btn-check" name="urgency" id="urg_normal" value="Normal"<?php echo $urg === 'Normal' ? ' checked' : ''; ?>>
        <label class="btn btn-outline-blood me-1" for="urg_normal">Normal</label>
        <input type="radio" class="btn-check" name="urgency" id="urg_emergency" value="Emergency"<?php echo $urg === 'Emergency' ? ' checked' : ''; ?>>
        <label class="btn btn-outline-blood" for="urg_emergency">Emergency</label>
        <div class="form-text">Emergency means the blood is needed today. Use it only when it really is urgent.</div>
    </div>

    <div class="row g-3" id="normalWhen"<?php echo $urg === 'Emergency' ? ' hidden' : ''; ?>>
        <div class="col-md-6">
            <label for="required_date" class="form-label">Date needed</label>
            <input type="date" class="form-control" id="required_date" name="required_date"
                   min="<?php echo $today; ?>" max="<?php echo date('Y-m-d', strtotime('+120 days')); ?>"
                   value="<?php echo e($f['required_date'] ?? ''); ?>"<?php echo $urg === 'Normal' ? ' required' : ' disabled'; ?>>
            <div class="invalid-feedback">Choose the date.</div>
        </div>
        <div class="col-md-6">
            <label for="required_time" class="form-label">Time needed</label>
            <input type="time" class="form-control" id="required_time" name="required_time"
                   value="<?php echo e(substr((string)($f['required_time'] ?? ''), 0, 5)); ?>"<?php echo $urg === 'Normal' ? ' required' : ' disabled'; ?>>
            <div class="invalid-feedback">Choose the time.</div>
        </div>
    </div>

    <h2 class="bl-form-group-title">More information</h2>
    <div class="row g-3">
        <div class="col-12">
            <label for="additional_info" class="form-label">Additional information (optional)</label>
            <textarea class="form-control" id="additional_info" name="additional_info" rows="3" maxlength="1000"
                      placeholder="Ward or bed number, doctor's name, anything a donor should know"><?php echo e($f['additional_info'] ?? ''); ?></textarea>
        </div>

        <?php if ($showUpload): ?>
            <div class="col-12">
                <label for="documents" class="form-label">Medical document</label>
                <input type="file" class="form-control" id="documents" name="documents[]" multiple<?php echo ($uploadRequired ?? true) ? ' required' : ''; ?>
                       accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                       data-max-mb="<?php echo (int)(MAX_UPLOAD_BYTES / 1048576); ?>"
                       data-max-files="<?php echo (int)MAX_DOCS_PER_REQUEST; ?>">
                <div class="form-text">
                    A prescription, doctor's note or hospital paper. PDF, JPG, JPEG or PNG, up to
                    <?php echo (int)(MAX_UPLOAD_BYTES / 1048576); ?> MB each, at most <?php echo (int)MAX_DOCS_PER_REQUEST; ?> files.
                    Only you and the admin can see it.
                </div>
                <div class="invalid-feedback" data-default="Choose a medical document.">Choose a medical document.</div>
            </div>
        <?php endif; ?>
    </div>

    <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary btn-lg"><?php echo e($submitLabel); ?></button>
        <a class="btn btn-outline-blood btn-lg" href="<?php echo e($cancelUrl); ?>">Cancel</a>
    </div>
</form>
