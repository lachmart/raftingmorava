<?php
/**
 * Example WordPress mu-plugin: sends a Contact Form 7 inquiry to the Rafting Morava system.
 *
 * INSTALLATION:
 *   1. Copy this file into wp-content/mu-plugins/ (mu = must-use, activates itself).
 *   2. Set the RAFTINGMORAVA_ENDPOINT and RAFTINGMORAVA_TOKEN constants below
 *      (the token must match INQUIRY_WEBHOOK_SECRET in the Laravel app's .env).
 *   3. In the CF7 form use the fields: your-name, your-email, telefon, pocet, termin,
 *      your-message and the hidden field dynamicname (the exact trip name/title = for program pairing).
 *
 * Note: if the POST fails (system outage), the inquiry still arrives by email as before –
 * this hook does not block the standard CF7 submission.
 */

if (! defined('ABSPATH')) {
    exit;
}

// --- Configuration -------------------------------------------------------
define('RAFTINGMORAVA_ENDPOINT', 'https://system.raftingmorava.cz/api/inquiries');
define('RAFTINGMORAVA_TOKEN', 'REDACTED'); // = INQUIRY_WEBHOOK_SECRET
// -------------------------------------------------------------------------

add_action('wpcf7_before_send_mail', function ($contact_form) {
    $submission = WPCF7_Submission::get_instance();
    if (! $submission) {
        return;
    }

    $data = $submission->get_posted_data();

    // Forward only the relevant fields (names match the mapping in the Laravel API).
    $payload = [
        'dynamicname'  => isset($data['dynamicname']) ? (is_array($data['dynamicname']) ? reset($data['dynamicname']) : $data['dynamicname']) : '',
        'termin'       => $data['termin']       ?? '',
        'your-name'    => $data['your-name']    ?? '',
        'your-email'   => $data['your-email']   ?? '',
        'telefon'      => $data['telefon']      ?? '',
        'pocet'        => $data['pocet']        ?? '',
        'your-message' => $data['your-message'] ?? '',
    ];

    // Non-blocking POST; any error is just logged.
    $response = wp_remote_post(RAFTINGMORAVA_ENDPOINT, [
        'timeout'  => 8,
        'blocking' => true,
        'headers'  => [
            'Accept'          => 'application/json',
            'X-Inquiry-Token' => RAFTINGMORAVA_TOKEN,
        ],
        'body'     => $payload,
    ]);

    if (is_wp_error($response)) {
        error_log('[RaftingMorava] Inquiry submission failed: ' . $response->get_error_message());
    }
}, 10, 1);
