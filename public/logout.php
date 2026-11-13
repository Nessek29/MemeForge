<?php
require_once __DIR__ . '/../src/helpers.php';

// Logging out changes state, so it is a CSRF-protected POST, never a GET link.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    abort(405, 'Use the Log out button to sign out.');
}
verify_csrf();

log_out();
flash('info', 'You have been logged out.');
redirect('index.php');
