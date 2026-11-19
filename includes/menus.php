<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(403); exit; }   // never run this file on its own
/*
 * includes/menus.php
 * The sidebar menu of each dashboard:  file  =>  text shown.
 * A link only becomes clickable when its file exists (see page_url()),
 * so the menu grows by itself while we build the project.
 */
function dash_menu(string $role): array
{
    $menus = [
        'admin' => [
            'admin/dashboard.php'       => 'Dashboard',
            'admin/manage_requests.php' => 'Blood requests',
            'admin/add_request.php'     => 'Add a request',
            'admin/manage_donors.php'   => 'Donors',
            'admin/manage_users.php'    => 'Users',
            'admin/donations.php'       => 'Donation records',
            'admin/reports.php'         => 'Reports',
        ],
        'donor' => [
            'donor/dashboard.php'        => 'Dashboard',
            'donor/requests.php'         => 'Matching requests',
            'donor/donation_history.php' => 'Donation history',
            'donor/notifications.php'    => 'Notifications',
            'donor/profile.php'          => 'My profile',
        ],
        'requester' => [
            'requester/dashboard.php'      => 'Dashboard',
            'requester/create_request.php' => 'New request',
            'requester/my_requests.php'    => 'My requests',
            'requester/notifications.php'  => 'Notifications',
        ],
    ];
    return $menus[$role] ?? [];
}
