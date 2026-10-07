<?php
/**
 * AfroNextStep contact form handler.
 * Receives the form on index.html and emails it to $TO using the server's mail service.
 */

$TO   = 'nuh@afronextstep.com';        // where messages are delivered
$FROM = 'no-reply@afronextstep.com';   // must be an address on this domain

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function respond($code, $ok, $error = null) {
    http_response_code($code);
    echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $error]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, false, 'Method not allowed.');
}

// Spam trap: real visitors never see or fill this field.
if (!empty($_POST['website'])) {
    respond(200, true);
}

function clean_line($v, $max) {
    $v = trim((string)($v ?? ''));
    $v = preg_replace('/[\r\n\t]+/', ' ', $v);   // no line breaks: blocks header injection
    return mb_substr($v, 0, $max);
}

$name    = clean_line($_POST['name']  ?? '', 100);
$phone   = clean_line($_POST['phone'] ?? '', 40);
$email   = clean_line($_POST['email'] ?? '', 150);
$message = mb_substr(trim((string)($_POST['message'] ?? '')), 0, 5000);

if ($name === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(422, false, 'Please fill in your name, a valid email and a message.');
}

// Basic rate limit: one message per 30 seconds per visitor.
session_start();
$now = time();
if (isset($_SESSION['last_sent']) && $now - $_SESSION['last_sent'] < 30) {
    respond(429, false, 'Please wait a moment before sending another message.');
}

$subject = 'Website enquiry from ' . $name;
$body  = "New message from the AfroNextStep website\n";
$body .= "-----------------------------------------\n";
$body .= "Name:  $name\n";
$body .= "Phone: " . ($phone !== '' ? $phone : '-') . "\n";
$body .= "Email: $email\n\n";
$body .= "Message:\n$message\n";

$headers  = "From: AfroNextStep Website <$FROM>\r\n";
$headers .= "Reply-To: $email\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "Content-Transfer-Encoding: 8bit\r\n";

$encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
$sent = @mail($TO, $encodedSubject, $body, $headers, '-f' . $FROM);

if (!$sent) {
    respond(500, false, 'Your message could not be sent. Please email ' . $TO . ' instead.');
}

$_SESSION['last_sent'] = $now;
respond(200, true);
