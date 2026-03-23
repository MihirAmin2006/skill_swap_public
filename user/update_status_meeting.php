
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Kolkata');
require_once __DIR__ . '/../includes/scripts/connection.php';
require_once __DIR__ . '/../mailSender/main.php';

$selectStmt = $conn->prepare(
    "SELECT m.meeting_time, m.meeting_id, m.status, m.approved, m.teacher_id, m.student_id, t.full_name AS teacher_name, s.full_name AS student_name, t.email AS teacher_email, s.email AS student_email FROM meetings m JOIN user_master t ON t.user_id = m.teacher_id JOIN user_master s ON s.user_id = m.student_id WHERE m.status != 'completed'"
);
// $selectStmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
$selectStmt->execute();
$res = $selectStmt->get_result();

$now = new DateTime();

while ($row = $res->fetch_assoc()) {
    $approved = $row['approved'];
    $startTime = new DateTime($row['meeting_time']);

    $endTime = clone $startTime;
    $endTime->modify('+2 hour');

    $newStatus = null;
    if ($now < $startTime) {
        $newStatus = 'upcoming';
    } elseif ($now >= $startTime && $now <= $endTime) {
        $newStatus = 'started';
    } else {
        $newStatus = 'completed';
    }
    // Only update if status changed
    if ($newStatus !== $row['status']) {

        $updateStmt = $conn->prepare(
            "UPDATE `meetings` SET `status` = ? WHERE `meeting_id` = ?;"
        );

        $updateStmt->bind_param("ss", $newStatus, $row['meeting_id']);
        $updateStmt->execute();
        $updateStmt->close();
        if ($startTime <= $now and $newStatus == 'started') {
            $msg_teacher = "It is time for your lecture with {$row['student_name']}, Please start the meeting!";
            $stmt = $conn->prepare("INSERT INTO `notifications` (`user_id`, `msg`, `time`, `msg_read`) VALUES (?,?, CURRENT_TIMESTAMP, 'no')");
            $stmt->bind_param("is", $row['teacher_id'], $msg_teacher);
            $stmt->execute();
            $stmt->close();
            sendMail(
                $row['teacher_email'],
                "Lecture Meeting Started",
                "<!DOCTYPE html>
                        <html>
                        <head>
                        <meta charset='UTF-8'>
                        <style>
                        body{
                            font-family: Arial, Helvetica, sans-serif;
                            background-color:#f4f6f9;
                            margin:0;
                            padding:0;
                        }

                        .container{
                            max-width:600px;
                            margin:30px auto;
                            background:#ffffff;
                            border-radius:8px;
                            overflow:hidden;
                            box-shadow:0 4px 10px rgba(0,0,0,0.08);
                        }

                        .header{
                            background:#2563eb;
                            color:white;
                            padding:20px;
                            text-align:center;
                            font-size:22px;
                            font-weight:bold;
                        }

                        .content{
                            padding:25px;
                            color:#333;
                            line-height:1.6;
                        }

                        .button{
                            display:inline-block;
                            padding:12px 20px;
                            margin-top:15px;
                            background:#2563eb;
                            color:white !important;
                            text-decoration:none;
                            border-radius:5px;
                            font-weight:bold;
                        }

                        .footer{
                            text-align:center;
                            padding:15px;
                            font-size:13px;
                            color:#777;
                            background:#f4f6f9;
                        }
                        </style>
                        </head>

                        <body>

                        <div class='container'>

                        <div class='header'>
                        Online Lecture Notification
                        </div>

                        <div class='content'>

                        <p>Hello <b>{$row['teacher_name']}</b>,</p>

                        <p>
                        It is time for your scheduled lecture with 
                        <b>{$row['student_name']}</b>.
                        </p>

                        <p>
                        Please start the meeting now.
                        </p>

                        <p>
                        Meeting Time: <b>{$row['meeting_time']}</b>
                        </p>

                        <a href='http://localhost:80/skill_swap/Auth/sign_in' class='button'>
                        Start Meeting
                        </a>

                        <p style='margin-top:25px'>
                        If you have any issues joining the meeting, please contact the administrator.
                        </p>

                        </div>

                        <div class='footer'>
                        © " . date("Y") . " Virtual Meeting System<br>
                        Automated Notification
                        </div>

                        </div>

                        </body>
                        </html>
                        "
            );

            $msg_stud = "It is time for your lecture with {$row['teacher_name']}, Please join the meeting!";
            $stmt = $conn->prepare("INSERT INTO `notifications` (`user_id`, `msg`, `time`, `msg_read`) VALUES (?,?, CURRENT_TIMESTAMP, 'no')");
            $stmt->bind_param("is", $row['student_id'], $msg_stud);
            $stmt->execute();
            $stmt->close();

            sendMail(
                $row['student_email'],
                "Your Lecture is Starting Now",
                "<!DOCTYPE html>
                <html>
                <head>
                <meta charset='UTF-8'>
                <style>
                body{
                    font-family: Arial, Helvetica, sans-serif;
                    background-color:#f4f6f9;
                    margin:0;
                    padding:0;
                }

                .container{
                    max-width:600px;
                    margin:30px auto;
                    background:#ffffff;
                    border-radius:8px;
                    overflow:hidden;
                    box-shadow:0 4px 10px rgba(0,0,0,0.08);
                }

                .header{
                    background:#2563eb;
                    color:white;
                    padding:20px;
                    text-align:center;
                    font-size:22px;
                    font-weight:bold;
                }

                .content{
                    padding:25px;
                    color:#333;
                    line-height:1.6;
                }

                .button{
                    display:inline-block;
                    padding:12px 20px;
                    margin-top:15px;
                    background:#2563eb;
                    color:white !important;
                    text-decoration:none;
                    border-radius:5px;
                    font-weight:bold;
                }

                .footer{
                    text-align:center;
                    padding:15px;
                    font-size:13px;
                    color:#777;
                    background:#f4f6f9;
                }
                </style>
                </head>

                <body>

                <div class='container'>

                <div class='header'>
                Lecture Notification
                </div>

                <div class='content'>

                <p>Hello <b>{$row['student_name']}</b>,</p>

                <p>
                Your scheduled lecture with <b>{$row['teacher_name']}</b> is starting now.
                </p>

                <p>
                Please join the meeting to attend your lecture.
                </p>

                <p>
                <b>Meeting Time:</b> {$row['meeting_time']}
                </p>

                <a href='http://localhost:80/skill_swap/Auth/sign_in' class='button'>
                Join Meeting
                </a>

                <p style='margin-top:25px'>
                Make sure your internet connection, microphone, and camera are ready before joining.
                </p>

                </div>

                <div class='footer'>
                © " . date("Y") . " Virtual Meeting System<br>
                Automated Notification
                </div>

                </div>

                </body>
                </html>
                "
            );
        }
    }
}

$selectStmt->close();
echo "done";
?>