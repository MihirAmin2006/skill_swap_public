<?php
session_start();
require_once __DIR__ . '/../includes/scripts/connection.php';
include_once __DIR__ . '/validation.php';
if (
    empty($_SESSION['isloggedin']) ||
    $_SESSION['isloggedin'] !== true ||
    empty($_SESSION['login_token'])
) {
    session_unset();
    session_destroy();
    header('Location: ../Auth/sign_in.php');
    exit;
}

$stmt = $conn->prepare("SELECT * FROM `user_master` where `user_id` = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$res = $stmt->get_result();
$user_detials = $res->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as `count_lectures`,
                        (SELECT COUNT(*) from meetings where status = 'upcoming' and `student_id` = ?) as `count_upcoming`,
                        (SELECT COUNT(*) from meetings where status = 'completed' and `student_id` = ?) as `count_completed` from meetings 
                        where student_id = ?;");
$stmt->bind_param("iii", $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']);
$stmt->execute();
$res1 = $stmt->get_result();
$total_counts = $res1->fetch_assoc();
$stmt->close();

if (empty($user_detials['profile_pic'])) {
    $default_dir = '../uploads/profiles/';
    $default_images = glob($default_dir . 'pfp{1, 2}.{jpg, jpeg, png, gif}', GLOB_BRACE);
    if (!empty($default_images)) {
        $random_pfp = basename($default_images[array_rand($default_images)]);
        $stmt = $conn->prepare('update `user_master` set `profile_pic` = ? where user_id = ?');
        $stmt->bind_param('si', $random_pfp, $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();
        $user_detials['profile_pic'] = $random_pfp;
    } else {
        $user_detials['profile_pic'] = 'pfp1.jpg'; // Fallback
    }
}

$name = explode(" ", $user_detials['full_name']);

$stmt = $conn->prepare("SELECT sub.sub_name FROM user_roles usr 
                        JOIN subject_master sub 
                        ON sub.sub_id = usr.sub_id 
                        WHERE usr.user_id = ? AND usr.user_role = 'teacher';");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$res = $stmt->get_result();
$user_role = $res->fetch_all(MYSQLI_ASSOC);
$user_role = array_column($user_role, 'sub_name');

$stmt1 = $conn->prepare("SELECT sub.sub_name FROM user_roles usr 
                        JOIN subject_master sub 
                        ON sub.sub_id = usr.sub_id 
                        WHERE usr.user_id = ? AND usr.user_role = 'student';");
$stmt1->bind_param("i", $_SESSION['user_id']);
$stmt1->execute();
$res = $stmt1->get_result();
$user_role_student = $res->fetch_all(MYSQLI_ASSOC);
$user_role_student = array_column($user_role_student, 'sub_name');


// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_profile') {
        $success_message = "Profile updated successfully!";
    }
}

//profile edit

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    header('Content-Type: application/json');

    $full_name = trim($_POST['name'] ?? '');
    $user_name = trim($_POST['username'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $user_id = $_SESSION['user_id'];

    if (empty($full_name) || empty($user_name)) {
        echo json_encode(['success' => false, 'error' => 'Name and username required']);
        exit;
    }

    // Photo upload
    $photo_path = null;
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/profiles/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $file_ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($file_ext, $allowed) || $_FILES['profile_photo']['size'] > 2000000) {
            echo json_encode(['success' => false, 'error' => 'Invalid photo (2MB, JPG/PNG/GIF)']);
            exit;
        }

        $new_filename = $user_id . '_' . time() . '.' . $file_ext;
        $photo_path = $new_filename;
        if (!move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_dir . $new_filename)) {
            echo json_encode(['success' => false, 'error' => 'Upload failed']);
            exit;
        }
    }

    if ($photo_path) {
        $stmt = $conn->prepare("UPDATE user_master SET user_name = ?, full_name = ?, phone = ?, profile_pic = ?, bio = ? WHERE user_id = ?");
        $stmt->bind_param("sssssi", $user_name, $full_name, $phone, $photo_path, $bio, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE user_master SET user_name = ?, full_name = ?, phone = ?, bio = ? WHERE user_id = ?");
        $stmt->bind_param("ssssi", $user_name, $full_name, $phone, $bio, $user_id);
    }

    $success = $stmt->execute();
    $stmt->close();

    if (!$success) {
        echo json_encode(['success' => false, 'error' => 'Update failed']);
        exit;
    }

    // ---- Save Skills ----
    $skills_offer_raw = trim($_POST['skills_offer'] ?? '');
    $skills_learn_raw = trim($_POST['skills_learn'] ?? '');

    // Helper: get existing sub_id or insert new subject into subject_master
    function getOrCreateSubject($conn, $name)
    {
        $name = trim($name);
        if ($name === '') return null;
        $s = $conn->prepare("SELECT sub_id FROM subject_master WHERE sub_name = ?");
        $s->bind_param("s", $name);
        $s->execute();
        $r = $s->get_result()->fetch_assoc();
        $s->close();
        if ($r) return (int)$r['sub_id'];
        $s = $conn->prepare("INSERT INTO subject_master (sub_name, total_teachers, total_students) VALUES (?, 0, 0)");
        $s->bind_param("s", $name);
        $s->execute();
        $id = $conn->insert_id;
        $s->close();
        return $id;
    }

    // Helper: recalculate and update total_teachers / total_students for a subject
    function updateSubjectCount($conn, $sub_id)
    {
        // Recount from actual user_roles rows — avoids drift
        $s = $conn->prepare("UPDATE subject_master SET
            total_teachers = (SELECT COUNT(*) FROM user_roles WHERE sub_id = ? AND user_role = 'teacher'),
            total_students = (SELECT COUNT(*) FROM user_roles WHERE sub_id = ? AND user_role = 'student')
            WHERE sub_id = ?");
        $s->bind_param("iii", $sub_id, $sub_id, $sub_id);
        $s->execute();
        $s->close();
    }

    // Helper: delete old role entries and insert new ones, then recount affected subjects
    function syncSkills($conn, $user_id, $role, $raw)
    {
        // Collect sub_ids the user HAD before (so we can recount them after removal)
        $old = $conn->prepare("SELECT sub_id FROM user_roles WHERE user_id = ? AND user_role = ?");
        $old->bind_param("is", $user_id, $role);
        $old->execute();
        $old_rows = $old->get_result()->fetch_all(MYSQLI_ASSOC);
        $old->close();
        $affected_ids = array_column($old_rows, 'sub_id');

        // Delete old entries for this user+role
        $del = $conn->prepare("DELETE FROM user_roles WHERE user_id = ? AND user_role = ?");
        $del->bind_param("is", $user_id, $role);
        $del->execute();
        $del->close();

        // Insert new entries
        if (trim($raw) !== '') {
            foreach (array_filter(array_map('trim', explode(',', $raw))) as $name) {
                $sub_id = getOrCreateSubject($conn, $name);
                if (!$sub_id) continue;
                $ins = $conn->prepare("INSERT IGNORE INTO user_roles (sub_id, user_id, user_role) VALUES (?, ?, ?)");
                $ins->bind_param("iis", $sub_id, $user_id, $role);
                $ins->execute();
                $ins->close();
                // Track newly added sub_ids too
                if (!in_array($sub_id, $affected_ids)) {
                    $affected_ids[] = $sub_id;
                }
            }
        }

        // Recount totals for every subject that was added or removed
        foreach (array_unique($affected_ids) as $sub_id) {
            updateSubjectCount($conn, (int)$sub_id);
        }
    }

    syncSkills($conn, $user_id, 'teacher', $skills_offer_raw);
    syncSkills($conn, $user_id, 'student', $skills_learn_raw);

    echo json_encode(['success' => true]);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en" class="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - SkillSwap</title>
    <?php
    include_once __DIR__ . '/../includes/scripts/common.php';
    ?>
    <script src="../assets/js/tailwind.js.php" defer></script>

    <link rel="stylesheet" href="../assets/styles/user/profile.css">
    <link rel="stylesheet" href="../assets/styles/user/common.css">
</head>

<body class="font-sans bg-page-light text-ink-light overflow-x-hidden dark:bg-page-dark dark:text-ink-dark transition-colors duration-300 relative">

   <!-- ════════════ MOBILE OVERLAY ════════════ -->
    <div id="mobileOverlay" onclick="closeMobileSidebar()"></div>

    <?php
    include_once __DIR__ . '/../animated-bg.php';
    ?>

    <!-- ════════════ MOBILE OVERLAY ════════════ -->
    <div id="mobileOverlay" onclick="closeMobileSidebar()"></div>


    <!-- Mesh Background -->
    <div class="mesh-bg fixed inset-0 pointer-events-none"></div>

    <!-- ════════════ SIDEBAR ════════════ -->
    <?php include_once __DIR__ . '/navbar.php'; ?>

    <!-- Main Content -->
    <main class="min-h-screen transition-all duration-300">
        <header class="sticky top-0 z-40 bg-white/80 dark:bg-slate-900/80 backdrop-blur-xl border-b border-slate-200 dark:border-slate-700 transition-colors">
            <div class="flex items-center justify-between px-4 sm:px-6 lg:px-8 py-4">

                <div class="flex items-center gap-3">
                    <button onclick="openMobileSidebar()"
                            class="lg:hidden p-2 rounded-xl bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-800 dark:text-blue-300 transition-all">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="3" y1="6"  x2="21" y2="6"/>
                            <line x1="3" y1="12" x2="21" y2="12"/>
                            <line x1="3" y1="18" x2="21" y2="18"/>
                        </svg>
                    </button>
                     <div>
                        <h1 class="font-display font-bold text-xl sm:text-2xl text-royal-basic dark:text-royal-soft">Profile</h1>
                        <p class="text-xs sm:text-sm text-muted-light dark:text-muted-dark hidden sm:block">View and Update your profile!</p>
                    </div>
                </div>

                <div class="flex items-center gap-2 sm:gap-3">
                    <!-- Dark mode -->
                    <div id="toggleWrap"
                         class="relative cursor-pointer rounded-full bg-slate-300 dark:bg-blue-800 transition-colors duration-300"
                         style="width:52px;height:28px;"
                         onclick="toggleTheme()" role="button" aria-label="Toggle dark mode" tabindex="0">
                        <div id="toggleKnob"
                             class="absolute top-0.5 left-0.5 rounded-full bg-white shadow flex items-center justify-center transition-transform duration-300"
                             style="width:22px;height:22px;">
                            <svg id="iconSun" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="#1e3a8a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="5"/>
                                <line x1="12" y1="1"  x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/>
                                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                                <line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/>
                                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                            </svg>
                            <svg id="iconMoon" class="w-3.5 h-3.5 hidden" viewBox="0 0 24 24" fill="none" stroke="#93c5fd" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                            </svg>
                        </div>
                    </div>
                     <button id="logoutButton" class="border border-red-500 bg-red-500 text-white hover:bg-red-600 dark:hover:bg-red-700 flex items-center gap-2 px-3 py-2 rounded-lg transition-colors">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                            <polyline points="16 17 21 12 16 7" />
                            <line x1="21" y1="12" x2="9" y2="12" />
                        </svg>
                        <span class="text-sm font-semibold">Logout</span>
                    </button>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <div class="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8 relative z-10">
            <?php if (isset($success_message)): ?>
                <div class="mb-6 p-4 bg-green-100 dark:bg-green-900/30 border border-green-400 dark:border-green-600 rounded-xl text-green-700 dark:text-green-300">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <!-- Profile Header Card -->
            <div class="bg-white/90 dark:bg-card-dark/90 backdrop-blur-sm rounded-2xl shadow-card border border-royal-basic/10 dark:border-royal-violet/20 overflow-hidden mb-6">
                <div class="p-6 sm:p-8">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-6 w-full">
                            <!-- Profile Photo -->
                            <div class="relative group" id="photoContainer">
                                <img src="../uploads/profiles/<?php echo htmlspecialchars($user_detials['profile_pic']); ?>"
                                    alt="Profile Photo"
                                    id="profilePhotoDisplay"
                                    class="w-24 h-24 sm:w-32 sm:h-32 rounded-2xl object-cover profile-photo-preview border-4 border-royal-light dark:border-royal-deep">
                                <button type="button"
                                    onclick="document.getElementById('photoInput').click()"
                                    id="changePhotoBtn"
                                    class="absolute inset-0 bg-black/60 rounded-2xl opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center hidden">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                </button>
                                <input type="file" id="photoInput" accept="image/*" class="hidden" onchange="previewPhoto(event)">
                            </div>

                            <!-- User Info -->
                            <div class="flex-1">
                                <div id="viewMode">
                                    <h2 class="font-display text-3xl font-bold text-ink-light dark:text-ink-dark mb-1">
                                        <?php echo htmlspecialchars($user_detials['full_name']); ?>
                                    </h2>
                                    <p class="text-muted-light dark:text-muted-dark mb-3">
                                        @<?php echo htmlspecialchars($user_detials['user_name']); ?>
                                    </p>
                                    <div class="flex flex-wrap items-center gap-4 text-sm text-muted-light dark:text-muted-dark">
                                        <div class="flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                            </svg>
                                            <?php echo htmlspecialchars($user_detials['email']); ?>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                            </svg>
                                            <?php echo htmlspecialchars($user_detials['phone']); ?>
                                        </div>
                                    </div>
                                </div>

                                <div id="editMode" class="hidden space-y-3">
                                    <div>
                                        <label class="block text-sm font-medium text-muted-light dark:text-muted-dark mb-1">Full Name</label>
                                        <input type="text" id="editName" value="<?php echo htmlspecialchars($user_detials['full_name']); ?>"
                                            class="w-full px-4 py-2 bg-white dark:bg-card-dark/70 border border-indigo-200 dark:border-indigo-500/28 rounded-lg text-ink-light dark:text-ink-dark focus:outline-none focus:ring-2 focus:ring-royal-mid dark:focus:ring-royal-violet">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-muted-light dark:text-muted-dark mb-1">Username</label>
                                        <input type="text" id="editUsername" value="<?php echo htmlspecialchars($user_detials['user_name']); ?>"
                                            class="w-full px-4 py-2 bg-white dark:bg-card-dark/70 border border-indigo-200 dark:border-indigo-500/28 rounded-lg text-ink-light dark:text-ink-dark focus:outline-none focus:ring-2 focus:ring-royal-mid dark:focus:ring-royal-violet">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-muted-light dark:text-muted-dark mb-1">Phone</label>
                                        <input type="tel" id="editPhone" value="<?php echo htmlspecialchars($user_detials['phone']); ?>"
                                            class="w-full px-4 py-2 bg-white dark:bg-card-dark/70 border border-indigo-200 dark:border-indigo-500/28 rounded-lg text-ink-light dark:text-ink-dark focus:outline-none focus:ring-2 focus:ring-royal-mid dark:focus:ring-royal-violet">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Edit Button -->
                        <div class="flex gap-3">
                            <button onclick="toggleEditMode()"
                                id="editToggleBtn"
                                class="gradient-btn text-white px-6 py-2.5 rounded-lg font-medium shadow-btn hover:shadow-btn-hov transition-all duration-300 whitespace-nowrap">
                                Edit Profile
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <div class="bg-white/90 dark:bg-card-dark/90 backdrop-blur-sm rounded-xl shadow-card border border-royal-basic/10 dark:border-royal-violet/20 p-6">
                    <div class="text-muted-light dark:text-muted-dark text-sm font-medium mb-1">Sessions Completed</div>
                    <div class="text-3xl font-bold text-royal-basic dark:text-royal-soft"><?= htmlspecialchars($total_counts['count_completed'] ?? 0) ?></div>
                </div>
                <div class="bg-white/90 dark:bg-card-dark/90 backdrop-blur-sm rounded-xl shadow-card border border-royal-basic/10 dark:border-royal-violet/20 p-6">
                    <div class="text-muted-light dark:text-muted-dark text-sm font-medium mb-1">Average Rating</div>
                    <div class="text-3xl font-bold text-gold-basic dark:text-gold-bright flex items-center gap-1">
                        <?= htmlspecialchars($user_detials['rating'] ?? 0) ?>
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                        </svg>
                    </div>
                </div>
                <div class="bg-white/90 dark:bg-card-dark/90 backdrop-blur-sm rounded-xl shadow-card border border-royal-basic/10 dark:border-royal-violet/20 p-6">
                    <div class="text-muted-light dark:text-muted-dark text-sm font-medium mb-1">Member Since</div>
                    <div class="text-3xl font-bold text-royal-basic dark:text-royal-soft">
                        <?php echo date('M Y', strtotime($user_detials['join_date'])); ?>
                    </div>
                </div>
            </div>

            <!-- Bio Section -->
            <div class="bg-white/90 dark:bg-card-dark/90 backdrop-blur-sm rounded-2xl shadow-card border border-royal-basic/10 dark:border-royal-violet/20 p-6 sm:p-8 mb-6">
                <h3 class="font-display font-bold text-lg text-royal-basic dark:text-royal-soft mb-4">About Me</h3>
                <div id="bioViewMode">
                    <p class="text-ink-light dark:text-ink-dark leading-relaxed">
                        <?= nl2br(htmlspecialchars($user_detials['bio'])); ?>
                    </p>
                </div>
                <div id="bioEditMode" class="hidden">
                    <textarea id="editBio" rows="4"
                        class="w-full px-4 py-3 bg-white dark:bg-card-dark/70 border border-indigo-200 dark:border-indigo-500/28 rounded-lg text-ink-light dark:text-ink-dark focus:outline-none focus:ring-2 focus:ring-royal-mid dark:focus:ring-royal-violet resize-none"><?= htmlspecialchars($user_detials['bio']); ?></textarea>
                </div>
            </div>

            <!-- Skills Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Skills I Offer -->
                <div class="bg-white/90 dark:bg-card-dark/90 backdrop-blur-sm rounded-2xl shadow-card border border-royal-basic/10 dark:border-royal-violet/20 p-6 sm:p-8">
                    <h3 class="font-display font-bold text-lg text-royal-basic dark:text-royal-soft mb-4">Skills I Offer</h3>
                    <!-- View Mode -->
                    <div id="skillsOfferView" class="flex flex-wrap gap-2">
                        <?php if (!empty($user_role)): foreach ($user_role as $skill): ?>
                                <span class="px-4 py-2 bg-royal-basic/7 dark:bg-royal-indigo/13 text-royal-basic dark:text-royal-violet rounded-lg font-medium text-sm border border-royal-basic/13 dark:border-royal-violet/18">
                                    <?php echo htmlspecialchars($skill); ?>
                                </span>
                            <?php endforeach;
                        else: ?>
                            <p class="text-muted-light dark:text-muted-dark text-sm italic">No skills added yet. Click Edit Profile to add.</p>
                        <?php endif; ?>
                    </div>
                    <!-- Edit Mode -->
                    <div id="skillsOfferEdit" class="hidden">
                        <input type="text" id="editSkillsOffer"
                            value="<?php echo htmlspecialchars(implode(', ', $user_role)); ?>"
                            placeholder="e.g. Math, Physics, Guitar"
                            class="w-full px-4 py-2 bg-white dark:bg-card-dark/70 border border-indigo-200 dark:border-indigo-500/28 rounded-lg text-ink-light dark:text-ink-dark focus:outline-none focus:ring-2 focus:ring-royal-mid dark:focus:ring-royal-violet">
                        <p class="text-xs text-muted-light dark:text-muted-dark mt-2">Separate skills with commas, e.g. Math, Guitar, Python</p>
                    </div>
                </div>

                <!-- Skills I Want to Learn -->
                <div class="bg-white/90 dark:bg-card-dark/90 backdrop-blur-sm rounded-2xl shadow-card border border-royal-basic/10 dark:border-royal-violet/20 p-6 sm:p-8">
                    <h3 class="font-display font-bold text-lg text-royal-basic dark:text-royal-soft mb-4">Skills I Want to Learn</h3>
                    <!-- View Mode -->
                    <div id="skillsLearnView" class="flex flex-wrap gap-2">
                        <?php if (!empty($user_role_student)): foreach ($user_role_student as $skill): ?>
                                <span class="px-4 py-2 bg-gold-basic/10 dark:bg-gold-bright/15 text-gold-basic dark:text-gold-bright rounded-lg font-medium text-sm border border-gold-basic/20 dark:border-gold-bright/25">
                                    <?php echo htmlspecialchars($skill); ?>
                                </span>
                            <?php endforeach;
                        else: ?>
                            <p class="text-muted-light dark:text-muted-dark text-sm italic">No skills added yet. Click Edit Profile to add.</p>
                        <?php endif; ?>
                    </div>
                    <!-- Edit Mode -->
                    <div id="skillsLearnEdit" class="hidden">
                        <input type="text" id="editSkillsLearn"
                            value="<?php echo htmlspecialchars(implode(', ', $user_role_student)); ?>"
                            placeholder="e.g. Spanish, Coding, Drawing"
                            class="w-full px-4 py-2 bg-white dark:bg-card-dark/70 border border-indigo-200 dark:border-indigo-500/28 rounded-lg text-ink-light dark:text-ink-dark focus:outline-none focus:ring-2 focus:ring-royal-mid dark:focus:ring-royal-violet">
                        <p class="text-xs text-muted-light dark:text-muted-dark mt-2">Separate skills with commas, e.g. Spanish, Coding, Drawing</p>
                    </div>
                </div>
            </div>

            <!-- Save/Cancel Buttons (Hidden by default) -->
            <div id="editActions" class="hidden flex justify-end gap-3">
                <button onclick="cancelEdit()"
                    class="px-6 py-2.5 rounded-lg font-medium border border-royal-basic/13 dark:border-royal-violet/18 text-royal-basic dark:text-royal-violet hover:bg-royal-basic/5 dark:hover:bg-royal-indigo/8 transition-all">
                    Cancel
                </button>
                <button onclick="saveProfile()"
                    class="gradient-btn text-white px-6 py-2.5 rounded-lg font-medium shadow-btn hover:shadow-btn-hov transition-all duration-300">
                    Save Changes
                </button>
            </div>
        </div>
    </main>
    <script src="../assets/js/profile.js.php" defer></script>
<script src="../assets/js/dashboard.js.php" defer></script>
    <script src="../assets/js/darkmodeToggle.js.php" defer></script>

</body>

</html>