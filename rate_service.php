<?php
require_once __DIR__ . '/includes/db.php';
hw_start_secure_session();
require_once __DIR__ . '/guest_notice.php';

if (!hw_is_owner()) {
    show_guest_notice("You need to sign in as a pet owner to rate a service.");
    exit();
}

try {
    $conn = hw_get_db();
} catch (Throwable $e) {
    show_guest_notice("Database connection failed. Please try again shortly.");
    exit();
}

$stmt = $conn->prepare('SELECT Owner_ID FROM owner WHERE username = ?');
$stmt->bind_param('s', $_SESSION['username']);
$stmt->execute();
$ownerResult = $stmt->get_result();
$ownerRow = $ownerResult->fetch_assoc();
$stmt->close();

if (!$ownerRow) {
    show_guest_notice("We couldn't find an owner account matching your session. Please sign in again.");
    exit();
}
$ownerId = (int)$ownerRow['Owner_ID'];
$scheduleId = (int)($_GET['schedule_id'] ?? 0);

// Verify this appointment belongs to the logged-in owner, is actually
// Completed, and hasn't already been rated — before showing the form.
$stmt = $conn->prepare(
    "SELECT s.Schedule_ID, s.Services, s.Appointment_Time, p.Pet_Name, r.Rating_ID
     FROM schedule s
     LEFT JOIN pet p ON s.Pet_ID = p.Pet_ID
     LEFT JOIN ratings r ON r.Schedule_ID = s.Schedule_ID
     WHERE s.Schedule_ID = ? AND s.Owner_ID = ? AND s.Status = 'Completed'"
);
$stmt->bind_param('ii', $scheduleId, $ownerId);
$stmt->execute();
$result = $stmt->get_result();
$appointment = $result->fetch_assoc();
$stmt->close();
$conn->close();

if (!$appointment) {
    show_guest_notice("That appointment couldn't be found, doesn't belong to you, or isn't completed yet.");
    exit();
}
if (!empty($appointment['Rating_ID'])) {
    show_guest_notice("You've already rated this appointment. Thanks for your feedback!");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="hw-csrf-token" content="<?php echo htmlspecialchars(hw_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <title>Rate Your Visit | HealthWing</title>
    <link rel="stylesheet" href="api/asset_proxy.php?f=assets/css/loader.css">
    <script src="api/asset_proxy.php?f=assets/js/loader.js" defer></script>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #F6FAFA; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; }
        .rate-card { background: #fff; border-radius: 16px; padding: 32px; max-width: 420px; width: 90%; box-shadow: 0 12px 36px rgba(0,0,0,0.1); border: 1px solid #E3EBEC; }
        .rate-card h1 { font-size: 19px; color: #16262A; margin: 0 0 6px 0; }
        .rate-card p.subtitle { font-size: 13.5px; color: #62787B; margin: 0 0 20px 0; }
        .stars { display: flex; gap: 6px; justify-content: center; margin-bottom: 18px; }
        .stars span { font-size: 34px; cursor: pointer; color: #E3EBEC; transition: color 0.1s ease; }
        .stars span.active { color: #E3A857; }
        textarea { width: 100%; min-height: 90px; padding: 10px 12px; border: 1px solid #E3EBEC; border-radius: 8px; font-family: inherit; font-size: 13.5px; box-sizing: border-box; margin-bottom: 16px; }
        button { width: 100%; background: #4A9DBB; color: #fff; border: none; padding: 11px; border-radius: 8px; font-weight: 700; font-size: 14px; cursor: pointer; }
        button:disabled { background: #B7CCD1; cursor: not-allowed; }
        .msg { text-align: center; font-size: 13px; margin-top: 12px; }
    </style>
</head>
<body>
    <div class="rate-card">
        <h1>Rate your visit</h1>
        <p class="subtitle"><?php echo htmlspecialchars($appointment['Services']); ?>for <?php echo htmlspecialchars($appointment['Pet_Name'] ?? 'your pet'); ?>— <?php echo htmlspecialchars($appointment['Appointment_Time']); ?></p>

        <form id="ratingForm">
            <div class="stars" id="starPicker">
                <span data-value="1">*</span>
                <span data-value="2">*</span>
                <span data-value="3">*</span>
                <span data-value="4">*</span>
                <span data-value="5">*</span>
            </div>
            <input type="hidden" id="ratingValue" value="0">
            <textarea id="comment" placeholder="Tell us about your experience (optional)"></textarea>
            <button type="submit" id="submitBtn"disabled>Submit Rating</button>
        </form>
        <div class="msg" id="msg"></div>
    </div>

    <script>
    const stars = document.querySelectorAll('#starPicker span');
    const ratingValueInput = document.getElementById('ratingValue');
    const submitBtn = document.getElementById('submitBtn');

    stars.forEach(star =>{
        star.addEventListener('click', () =>{
            const value = parseInt(star.dataset.value);
            ratingValueInput.value = value;
            stars.forEach(s => s.classList.toggle('active', parseInt(s.dataset.value) <= value));
            submitBtn.disabled = false;
        });
    });

    document.getElementById('ratingForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const rating = parseInt(ratingValueInput.value);
        if (!rating) return;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';

        fetch('submit_rating.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json'},
            body: JSON.stringify({
                scheduleId: <?php echo (int)$scheduleId; ?>,
                rating: rating,
                comment: document.getElementById('comment').value
            })
        })
            .then(res => res.json())
            .then(data =>{
                const msg = document.getElementById('msg');
                if (data.success) {
                    msg.style.color = '#2E8659';
                    msg.textContent = 'Thanks for your feedback!';
                    document.getElementById('ratingForm').style.display = 'none';
                    setTimeout(() =>{ window.location.href = 'owner_bookings.php'; }, 1500);
                } else {
                    msg.style.color = '#B23F58';
                    msg.textContent = data.message || 'Something went wrong.';
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Submit Rating';
                }
            })
            .catch(() =>{
                document.getElementById('msg').textContent = 'Could not reach the server.';
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit Rating';
            });
    });
    </script>
</body>
</html>
