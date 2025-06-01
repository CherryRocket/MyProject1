<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-6.9.3/src/Exception.php';
require 'PHPMailer-6.9.3/src/PHPMailer.php';
require 'PHPMailer-6.9.3/src/SMTP.php';

$data = json_decode(file_get_contents('php://input'), true);

$email = filter_var($data['email'], FILTER_VALIDATE_EMAIL);
$subject = $data['subject'];
$message = $data['message'];

if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Неверный email']);
    exit;
}

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.yandex.ru';
    $mail->SMTPAuth = true;
    $mail->Username = 'koroleffilyas@yandex.ru';
    $mail->Password = 'velomfifnssyerbb';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;
    $mail->CharSet = 'UTF-8';

    $mail->setFrom('koroleffilyas@yandex.ru', 'MyShop');
    $mail->addAddress($email);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto;'>
            <h2 style='color: #007BFF;'>MyShop</h2>
            <p>" . nl2br(htmlspecialchars($message)) . "</p>
        </div>
    ";
    $mail->AltBody = strip_tags($message);

    if ($mail->send()) {
        echo json_encode(['success' => true, 'message' => 'Письмо отправлено клиенту']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ошибка при отправке письма: ' . $mail->ErrorInfo]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()]);
}
?>