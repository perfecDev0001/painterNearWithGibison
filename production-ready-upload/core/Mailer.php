<?php
namespace Core;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

class Mailer {
    private $mail;
    private $logFile;

    public function __construct() {
        $config = require(__DIR__ . '/../config/email.php');
        $this->mail = new PHPMailer(true);
        $this->logFile = __DIR__ . '/../logs/email.log';
        try {
            $this->mail->isSMTP();
            $this->mail->Host = $config['host'];
            $this->mail->SMTPAuth = true;
            $this->mail->Username = $config['username'];
            $this->mail->Password = $config['password'];
            $this->mail->SMTPSecure = $config['encryption'];
            $this->mail->Port = $config['port'];
            $this->mail->setFrom($config['from_email'], $config['from_name']);
            $this->mail->addReplyTo($config['reply_to'], $config['from_name']);
        } catch (Exception $e) {
            $this->log('Mailer init error: ' . $e->getMessage());
        }
    }

    public function sendMail($to, $subject, $body, $altBody = '', $toName = '') {
        try {
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();
            $this->mail->addAddress($to, $toName);
            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body = $body;
            $this->mail->AltBody = $altBody ?: strip_tags($body);
            $this->mail->send();
            $this->log("Sent to $to: $subject");
            return true;
        } catch (Exception $e) {
            $this->log('Mailer send error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email with file attachments
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $body HTML email body
     * @param array $attachments Array of file paths to attach
     * @param string $altBody Plain text version
     * @param string $toName Recipient name
     * @return bool Success status
     */
    public function sendMailWithAttachments($to, $subject, $body, $attachments = [], $altBody = '', $toName = '') {
        try {
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();
            $this->mail->addAddress($to, $toName);
            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body = $body;
            $this->mail->AltBody = $altBody ?: strip_tags($body);
            
            // Add attachments
            if (!empty($attachments)) {
                foreach ($attachments as $attachment) {
                    if (is_array($attachment)) {
                        // Format: ['path' => '/path/to/file', 'name' => 'display_name.ext']
                        $this->mail->addAttachment($attachment['path'], $attachment['name']);
                    } else {
                        // Simple file path
                        $this->mail->addAttachment($attachment);
                    }
                }
            }
            
            $this->mail->send();
            $attachmentCount = count($attachments);
            $this->log("Sent to $to: $subject (with $attachmentCount attachments)");
            return true;
        } catch (Exception $e) {
            $this->log('Mailer send with attachments error: ' . $e->getMessage());
            return false;
        }
    }

    private function log($msg) {
        $line = date('Y-m-d H:i:s') . ' ' . $msg . "\n";
        @file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }
} 