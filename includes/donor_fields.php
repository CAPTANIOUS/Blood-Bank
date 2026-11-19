<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(403); exit; }   // never run this file on its own
/*
 * includes/donor_fields.php
 * ONE place that checks the donor-specific form fields.
 * Used by: register.php (new donor), donor/profile.php (donor edits own data)
 * and, later, the admin's "edit donor" page. Same rules everywhere.
 */
require_once __DIR__ . '/functions.php';

/*
 * $in          = the typed values ($_POST)
 * $bloodGroups = rows from the blood_groups table
 * $districts   = rows from the districts table
 * Returns:  [ cleaned values, list of error messages ]
 */
function validate_donor_input(array $in, array $bloodGroups, array $districts): array
{
    $errors = [];
    $today  = date('Y-m-d');

    $data = [
        'date_of_birth'      => clean($in['date_of_birth'] ?? ''),
        'gender'             => clean($in['gender'] ?? ''),
        'blood_group_id'     => (int)($in['blood_group_id'] ?? 0),
        'address'            => clean($in['address'] ?? ''),
        'district_id'        => (int)($in['district_id'] ?? 0),
        'area'               => clean($in['area'] ?? ''),
        'last_donation_date' => clean($in['last_donation_date'] ?? ''),
        'is_available'       => (($in['is_available'] ?? '1') === '0') ? 0 : 1,
        'medical_issues'     => clean($in['medical_issues'] ?? ''),
    ];
    $dob = $data['date_of_birth'];

    if (!is_valid_date($dob)) {
        $errors[] = 'Choose a valid date of birth.';
    } elseif ($dob > $today) {
        $errors[] = 'The date of birth cannot be in the future.';
    } else {
        $age = (new DateTime($dob))->diff(new DateTime($today))->y;
        if ($age < 18) {
            $errors[] = 'You must be at least 18 years old to be a donor.';
        } elseif ($age > 100) {
            $errors[] = 'Check your date of birth.';
        }
    }

    if (!in_array($data['gender'], ['Male', 'Female', 'Other'], true)) {
        $errors[] = 'Choose your gender.';
    }
    if (!in_array($data['blood_group_id'], array_column($bloodGroups, 'blood_group_id'))) {
        $errors[] = 'Choose your blood group.';
    }
    if (strlen($data['address']) < 5 || strlen($data['address']) > 255) {
        $errors[] = 'Enter your full address (5 to 255 characters).';
    }
    if (!in_array($data['district_id'], array_column($districts, 'district_id'))) {
        $errors[] = 'Choose your district.';
    }
    if (strlen($data['area']) < 2 || strlen($data['area']) > 100) {
        $errors[] = 'Enter your area (2 to 100 characters).';
    }

    $last = $data['last_donation_date'];
    if ($last !== '') {
        if (!is_valid_date($last)) {
            $errors[] = 'Choose a valid last donation date, or leave it empty.';
        } elseif ($last > $today) {
            $errors[] = 'The last donation date cannot be in the future.';
        } elseif (is_valid_date($dob) && $last < $dob) {
            $errors[] = 'The last donation date cannot be before your date of birth.';
        }
    }
    // "none", "no", "n/a" ... mean "no issue": store nothing so the admin is not shown a false warning
    if (preg_match('/^(none|no|nil|n\/a|na|nothing|no issues?|not any|-+)\.?$/i', $data['medical_issues'])) {
        $data['medical_issues'] = '';
    }
    if (strlen($data['medical_issues']) > 1000) {
        $errors[] = 'Medical issues: please use 1000 characters or fewer.';
    }

    return [$data, $errors];
}
