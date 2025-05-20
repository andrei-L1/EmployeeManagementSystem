<?php
require_once '../../auth/check_login.php';
require_once(__DIR__ . '/../../config/dbcon.php');

// Check current attendance status
$todayRecord = null;
$stmt = $conn->prepare("SELECT * FROM attendance_records 
                      WHERE employee_id = ? AND date = CURDATE()");
$stmt->execute([$_SESSION['user_data']['employee_id']]);
if ($stmt->rowCount() > 0) {
    $todayRecord = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Time Tracking | EmployeeTrack Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .time-card {
            max-width: 500px;
            margin: 0 auto;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        #selfie-preview {
            max-width: 100%;
            display: none;
        }
    </style>
</head>
<body>
    <!--  <?php include '../../partials/header.php'; ?> -->
    
    <div class="container py-5">
        <div class="card time-card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Daily Time Record</h4>
            </div>
            <div class="card-body text-center">
                <h5><?= date('l, F j, Y') ?></h5>
                
                <?php if ($todayRecord && $todayRecord['time_in'] && !$todayRecord['time_out']): ?>
                <!-- Clock Out Section -->
                <div class="my-4">
                    <p>You clocked in at: <?= date('h:i A', strtotime($todayRecord['time_in'])) ?></p>
                    <button id="clockOutBtn" class="btn btn-danger btn-lg px-5">
                        <i class="fas fa-sign-out-alt"></i> Clock Out
                    </button>
                </div>
                
                <?php elseif ($todayRecord && $todayRecord['time_out']): ?>
                <!-- Already Completed Section -->
                <div class="alert alert-success my-4">
                    <p>You've completed your time record for today:</p>
                    <p>Clock In: <?= date('h:i A', strtotime($todayRecord['time_in'])) ?></p>
                    <p>Clock Out: <?= date('h:i A', strtotime($todayRecord['time_out'])) ?></p>
                </div>
                
                <?php else: ?>
                <!-- Clock In Section -->
                <div class="my-4">
                    <div class="mb-3">
                        <video id="video" width="320" height="240" autoplay class="d-none"></video>
                        <canvas id="canvas" width="320" height="240" class="d-none"></canvas>
                        <img id="selfie-preview" alt="Selfie Preview">
                    </div>
                    <button id="captureBtn" class="btn btn-secondary mb-3">
                        <i class="fas fa-camera"></i> Take Verification Photo
                    </button>
                    <button id="clockInBtn" class="btn btn-primary btn-lg px-5" disabled>
                        <i class="fas fa-sign-in-alt"></i> Clock In
                    </button>
                </div>
                <?php endif; ?>
                
                <div id="locationStatus" class="text-muted small mt-3">
                    <i class="fas fa-map-marker-alt"></i> <span id="locationText">Acquiring location...</span>
                </div>
            </div>
        </div>
        
        <!-- Recent Records -->
        <div class="card mt-4">
            <div class="card-header">
                <h5>Your Recent Attendance</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Clock In</th>
                                <th>Clock Out</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $conn->prepare("SELECT * FROM attendance_records 
                                                  WHERE employee_id = ? 
                                                  ORDER BY date DESC LIMIT 7");
                            $stmt->execute([$_SESSION['user_data']['employee_id']]);
                            while ($record = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr>
                                <td><?= date('M j, Y', strtotime($record['date'])) ?></td>
                                <td><?= $record['time_in'] ? date('h:i A', strtotime($record['time_in'])) : '-' ?></td>
                                <td><?= $record['time_out'] ? date('h:i A', strtotime($record['time_out'])) : '-' ?></td>
                                <td>
                                    <span class="badge bg-<?= 
                                        $record['status'] === 'Present' ? 'success' : 
                                        ($record['status'] === 'Late' ? 'warning' : 
                                        ($record['status'] === 'On Leave' ? 'info' : 'secondary')) 
                                    ?>">
                                        <?= $record['status'] ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Geolocation
        let currentLocation = null;
        
        navigator.geolocation.getCurrentPosition(
            (position) => {
                currentLocation = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude
                };
                document.getElementById('locationText').textContent = 'Location captured';
            },
            (error) => {
                console.error("Geolocation error:", error);
                document.getElementById('locationText').textContent = 'Location access denied';
            }
        );

        // Camera setup for clock in
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const selfiePreview = document.getElementById('selfie-preview');
        const captureBtn = document.getElementById('captureBtn');
        const clockInBtn = document.getElementById('clockInBtn');
        
        if (captureBtn) {
            captureBtn.addEventListener('click', () => {
                // Access camera
                if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                    navigator.mediaDevices.getUserMedia({ video: true })
                        .then(stream => {
                            video.srcObject = stream;
                            video.classList.remove('d-none');
                            
                            // Capture photo
                            canvas.getContext('2d').drawImage(video, 0, 0, 320, 240);
                            const imageData = canvas.toDataURL('image/jpeg');
                            selfiePreview.src = imageData;
                            selfiePreview.style.display = 'block';
                            video.classList.add('d-none');
                            
                            // Enable clock in button
                            clockInBtn.disabled = false;
                            
                            // Stop video stream
                            stream.getTracks().forEach(track => track.stop());
                        })
                        .catch(err => {
                            console.error("Camera error:", err);
                            alert("Could not access camera. Clock in without photo?");
                            clockInBtn.disabled = false;
                        });
                }
            });
        }

        // Clock In Handler
        if (clockInBtn) {
            clockInBtn.addEventListener('click', async () => {
                try {
                    const response = await fetch('../../api/attendance/clock-in.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': '<?php echo $_SESSION['csrf_token']; ?>'
                        },
                        body: JSON.stringify({
                            latitude: currentLocation?.lat,
                            longitude: currentLocation?.lng,
                            imageData: selfiePreview.src || null
                        })
                    });
                    
                    const result = await response.json();
                    if (result.success) {
                        alert("Clock in recorded successfully!");
                        window.location.reload();
                    } else {
                        alert("Error: " + result.error);
                    }
                } catch (error) {
                    console.error("Error:", error);
                    alert("Failed to record clock in");
                }
            });
        }

        // Clock Out Handler
        const clockOutBtn = document.getElementById('clockOutBtn');
        if (clockOutBtn) {
            clockOutBtn.addEventListener('click', async () => {
                if (confirm("Are you sure you want to clock out?")) {
                    try {
                        const response = await fetch('../../api/attendance/clock-out.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': '<?php echo $_SESSION['csrf_token']; ?>'
                            },
                            body: JSON.stringify({
                                latitude: currentLocation?.lat,
                                longitude: currentLocation?.lng
                            })
                        });
                        
                        const result = await response.json();
                        if (result.success) {
                            alert("Clock out recorded successfully!");
                            window.location.reload();
                        } else {
                            alert("Error: " + result.error);
                        }
                    } catch (error) {
                        console.error("Error:", error);
                        alert("Failed to record clock out");
                    }
                }
            });
        }
    </script>
</body>
</html>