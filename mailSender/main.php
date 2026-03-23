<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/config.php';

function sendMail($to, $subject, $message)
{
    global $config;

    $mail = new PHPMailer(true);

    try {

        $mail->isSMTP();
        $mail->Host = $config['mail_host'];
        $mail->SMTPAuth = true;
        $mail->isHTML(true);

        $mail->Username = $config['mail_user'];
        $mail->Password = $config['mail_pass'];

        $mail->SMTPSecure = "tls";
        $mail->Port = $config['mail_port'];

        $mail->setFrom($config['mail_from_email'], $config['mail_from_name']);
        $mail->addAddress($to);


        $mail->Subject = $subject;
        $mail->Body = $message;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
