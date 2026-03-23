<?php
// Fetch currenet URL/
$currentUrl = "http" . (isset($_SERVER['HTTPS']) ? "s" : "") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
// Fetch current Domain name from current URL.
$domain = parse_url($currentUrl, PHP_URL_HOST);
?>

<!DOCTYPE html>
<html lang="en" class="light">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SkillSwap – College Micro-Mentoring</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet" />
</head>

<!-- <script>
setInterval(function() {
    fetch('../../user/update_status_meeting.php');
}, 60000);
</script> -->

</html>