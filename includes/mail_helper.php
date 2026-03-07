<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Require PHPMailer's autoloader
require __DIR__ . '/../vendor/autoload.php';

/**
 * Helper function to send email via SMTP
 */
function send_notification_email($to_email, $to_name, $subject, $body_content) {
    // Load config
    $config = require __DIR__ . '/mail_config.php';
    
    // Create an instance of PHPMailer
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['username'];
        $mail->Password   = $config['password'];
        $mail->SMTPSecure = $config['encryption'];
        $mail->Port       = $config['port'];
        
        // Disable SSL verification for development (optional but helpful for some local envs)
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Recipients
        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($to_email, $to_name);

        // Content
        $mail->isHTML(false); // Plain text
        $mail->Subject = $subject;
        $mail->Body    = $body_content;

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log errors would be a good idea
        return false;
    }
}
?>
