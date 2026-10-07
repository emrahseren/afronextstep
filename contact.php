<?php
/**
 * AfroNextStep contact form handler (SMTP version).
 * Sends the form through your Namecheap Private Email mailbox, so the message
 * is delivered like any email you send yourself.
 *
 * SETUP: the mailbox password is NOT stored in this file (it is kept out of git).
 * On the server, copy contact-config.example.php to contact-config.php and put
 * the password there. Deploys never overwrite contact-config.php.
 */

const SMTP_HOST = 'ssl://mail.privateemail.com';
const SMTP_PORT = 465;
const SMTP_USER = 'nuh@afronextstep.com';
const MAIL_TO   = 'nuh@afronextstep.com';

$configFile = __DIR__ . '/contact-config.php';
$config = is_file($configFile) ? (include $configFile) : [];
define('SMTP_PASS', is_array($config) ? (string)($config['smtp_pass'] ?? '') : '');

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
$body  = "New message from the AfroNextStep website\r\n";
$body .= "-----------------------------------------\r\n";
$body .= "Name:  $name\r\n";
$body .= "Phone: " . ($phone !== '' ? $phone : '-') . "\r\n";
$body .= "Email: $email\r\n\r\n";
$body .= "Message:\r\n" . preg_replace('/\r\n|\r|\n/', "\r\n", $message) . "\r\n";

$headers  = 'Date: ' . date('r') . "\r\n";
$headers .= 'From: AfroNextStep Website <' . SMTP_USER . ">\r\n";
$headers .= 'To: <' . MAIL_TO . ">\r\n";
$headers .= "Reply-To: <$email>\r\n";
$headers .= 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n";
$headers .= 'Message-ID: <' . bin2hex(random_bytes(12)) . "@afronextstep.com>\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "Content-Transfer-Encoding: base64\r\n";

$data = $headers . "\r\n" . chunk_split(base64_encode($body), 76, "\r\n");

/** Minimal SMTP client: returns null on success or a short reason on failure. */
function smtp_send($data) {
    $fp = @stream_socket_client(SMTP_HOST . ':' . SMTP_PORT, $errno, $errstr, 15);
    if (!$fp) return "connect failed: $errstr";
    stream_set_timeout($fp, 15);

    $read = function () use ($fp) {
        $out = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $out .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;   // last line of a reply
        }
        return $out;
    };
    $cmd = function ($line, $expect) use ($fp, $read) {
        if ($line !== null) fwrite($fp, $line . "\r\n");
        $reply = $read();
        return (strpos($reply, $expect) === 0) ? null : trim($reply);
    };

    $steps = [
        [null, '220'],
        ['EHLO afronextstep.com', '250'],
        ['AUTH LOGIN', '334'],
        [base64_encode(SMTP_USER), '334'],
        [base64_encode(SMTP_PASS), '235'],
        ['MAIL FROM:<' . SMTP_USER . '>', '250'],
        ['RCPT TO:<' . MAIL_TO . '>', '250'],
        ['DATA', '354'],
        [rtrim($data, "\r\n") . "\r\n.", '250'],
    ];
    foreach ($steps as $i => $s) {
        $err = $cmd($s[0], $s[1]);
        if ($err !== null) { fclose($fp); return "step $i: " . ($i === 4 ? 'login rejected' : $err); }
    }
    fwrite($fp, "QUIT\r\n");
    fclose($fp);
    return null;
}

$err = SMTP_PASS === '' ? 'contact-config.php is missing or has no password' : smtp_send($data);
if ($err !== null) {
    error_log('AfroNextStep contact form: ' . $err);
    respond(500, false, 'Your message could not be sent. Please email ' . MAIL_TO . ' instead.');
}

$_SESSION['last_sent'] = $now;
respond(200, true);
