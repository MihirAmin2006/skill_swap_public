<?php
session_start();
require_once __DIR__ . '/../includes/scripts/connection.php';

header("Content-Type: application/json");

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$teacher_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT 
        sm.sub_name,
        m.meeting_id,
        m.status,
        DATE(m.meeting_time) as meeting_date,
        TIME(m.meeting_time) as meeting_clock
    FROM meetings m
    JOIN subject_master sm ON m.sub_id = sm.sub_id
    WHERE m.teacher_id = ? and (m.approved = '1' or m.approved = '2')
    ORDER BY m.meeting_time ASC
");

$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

$data = [];

while ($row = $result->fetch_assoc()) {

    $date = $row['meeting_date'];

    $data[$date][] = [
        "time" => date("H:i", strtotime($row['meeting_clock'])),
        "subject" => $row['sub_name'],
        "status" => $row['status'],
        "meeting_id" => $row['meeting_id']
    ];
}

echo json_encode($data);