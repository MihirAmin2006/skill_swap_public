<?php
/**
 * user/get_mentors.php
 * Returns teachers who teach subjects the current user studies.
 * The booking modal only shows overlapping subjects (not all teacher subjects).
 */

session_start();
require_once __DIR__ . '/../includes/scripts/connection.php';

header('Content-Type: application/json');

// ── Auth check ────────────────────────────────────────────────────────────────
if (
    empty($_SESSION['isloggedin']) ||
    $_SESSION['isloggedin'] !== true ||
    empty($_SESSION['login_token'])
) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized', 'mentors' => []]);
    exit;
}

define('SESSION_COST', 10);
$current_user_id = (int) $_SESSION['user_id'];

// ── Helpers ───────────────────────────────────────────────────────────────────
function getAvailability(string $last_login): string {
    $diff = time() - strtotime($last_login);
    if ($diff < 900)  return 'online';
    if ($diff < 7200) return 'busy';
    return 'offline';
}

function isNew(?string $join_date): bool {
    if (!$join_date) return false;
    return (time() - strtotime($join_date)) < (30 * 86400);
}

function avatarUrl(?string $pic): ?string {
    if (!$pic) return null;
    return '../uploads/profiles/' . $pic;
}

/**
 * Returns [{id, name}] for subjects this teacher teaches
 * filtered to only those in $allowedSubIds (the current user's student subjects).
 */
function getOverlappingSubjects(mysqli $conn, int $teacher_id, array $allowedSubIds): array {
    if (empty($allowedSubIds)) return [];
    $placeholders = implode(',', array_fill(0, count($allowedSubIds), '?'));
    $types        = str_repeat('i', count($allowedSubIds) + 1);
    $params       = array_merge([$teacher_id], $allowedSubIds);

    $stmt = $conn->prepare("
        SELECT ur.sub_id AS id, sm.sub_name AS name
        FROM user_roles ur
        JOIN subject_master sm ON sm.sub_id = ur.sub_id
        WHERE ur.user_id = ?
          AND ur.user_role = 'teacher'
          AND ur.sub_id IN ($placeholders)
    ");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function shapeMentor(array $row, array $overlappingSubjects): array {
    return [
        'user_id'             => (int)   $row['user_id'],
        'user_name'           => $row['user_name']  ?? '',
        'full_name'           => $row['full_name']  ?? 'Unknown',
        'avatar_url'          => avatarUrl($row['profile_pic'] ?? null),
        'bio'                 => $row['bio']         ?? '',
        'speciality'          => 'Mentor / Teacher',
        // skills shown on the card — only the subjects relevant to this user
        'skills'              => array_column($overlappingSubjects, 'name'),
        // subject dropdown in the booking modal — only overlapping subjects
        'subject_ids_map'     => $overlappingSubjects,
        'credits_per_session' => SESSION_COST,
        'rating'              => (float) ($row['rating'] ?? 0),
        'review_count'        => (int)   ($row['total_sessions'] ?? 0),
        'total_sessions'      => (int)   ($row['total_sessions'] ?? 0),
        'shared_subjects'     => count($overlappingSubjects),
        'availability'        => getAvailability((string)($row['last_login'] ?? 'now')),
        'is_new'              => isNew($row['join_date'] ?? null),
        'last_login'          => $row['last_login'] ?? '',
    ];
}

// ── Step 1: Get subjects where current user is a STUDENT ──────────────────────
$stmt = $conn->prepare("
    SELECT ur.sub_id AS id, sm.sub_name AS name
    FROM user_roles ur
    JOIN subject_master sm ON sm.sub_id = ur.sub_id
    WHERE ur.user_id = ? AND ur.user_role = 'student'
");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$myStudentSubjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); // [{id, name}]
$stmt->close();

$myStudentSubIds = array_column($myStudentSubjects, 'id');

// ── Step 2: Find teachers who teach ANY of those subjects ─────────────────────
$mentors = [];

if (!empty($myStudentSubIds)) {
    $placeholders = implode(',', array_fill(0, count($myStudentSubIds), '?'));
    $types        = str_repeat('i', count($myStudentSubIds)) . 'i';
    $params       = array_merge($myStudentSubIds, [$current_user_id]);

    $stmt = $conn->prepare("
        SELECT DISTINCT user_id
        FROM user_roles
        WHERE user_role = 'teacher'
          AND sub_id IN ($placeholders)
          AND user_id != ?
    ");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $teacherIds = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'user_id');
    $stmt->close();

    if (!empty($teacherIds)) {
        $idList = implode(',', array_map('intval', $teacherIds)); // safe: all ints

        $res = $conn->query("
            SELECT um.user_id, um.user_name, um.full_name, um.profile_pic,
                   um.bio, um.rating, um.join_date, um.last_login,
                   (SELECT COUNT(*) FROM meetings m
                    WHERE (m.teacher_id = um.user_id OR m.student_id = um.user_id)
                      AND m.status = 'completed') AS total_sessions
            FROM user_master um
            WHERE um.user_id IN ($idList)
            ORDER BY um.rating DESC
        ");

        while ($row = $res->fetch_assoc()) {
            $uid = (int)$row['user_id'];

            // Only return the subjects this teacher teaches that YOU study
            $overlapping = getOverlappingSubjects($conn, $uid, $myStudentSubIds);

            $mentors[] = shapeMentor($row, $overlapping);
        }
    }
}

// ── Current user credits ──────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT credit, rating FROM user_master WHERE user_id = ?");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo json_encode([
    'success'   => true,
    'debug'     => [
        'current_user_id'  => $current_user_id,
        'my_student_subs'  => $myStudentSubjects,
        'total_returned'   => count($mentors),
        'session_cost'     => SESSION_COST,
    ],
    'my_credit' => (int)   ($me['credit'] ?? 0),
    'my_rating' => (float) ($me['rating'] ?? 0),
    'count'     => count($mentors),
    'mentors'   => $mentors,
]);
