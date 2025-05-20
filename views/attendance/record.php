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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-light: #818cf8;
            --primary-dark: #4f46e5;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --light-color: #f8fafc;
            --dark-color: #1e293b;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --card-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --card-shadow-hover: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --transition-speed: 0.3s;
        }

        body {
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--gray-100) 100%);
            color: var(--gray-800);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
        }

        .main-content {
            padding: 2rem;
            transition: all var(--transition-speed) ease;
        }

        .attendance-card {
            background: white;
            border-radius: 1.5rem;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--gray-200);
            margin-bottom: 2rem;
            overflow: hidden;
            transition: all var(--transition-speed) ease;
        }

        .attendance-card:hover {
            box-shadow: var(--card-shadow-hover);
            border-color: var(--primary-light);
        }

        .attendance-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }

        .attendance-header::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 150px;
            height: 150px;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1));
            border-radius: 50%;
            transform: translate(30%, -30%);
        }

        .attendance-body {
            padding: 2rem;
        }

        .time-display {
            font-size: 3rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 1.5rem 0;
            text-align: center;
        }

        .action-button {
            padding: 1rem 2rem;
            border-radius: 1rem;
            font-weight: 600;
            transition: all var(--transition-speed) ease;
            width: 100%;
            max-width: 300px;
            margin: 1rem auto;
            position: relative;
            overflow: hidden;
        }

        .action-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, rgba(255,255,255,0.1), rgba(255,255,255,0));
            transform: translateX(-100%);
            transition: transform var(--transition-speed) ease;
        }

        .action-button:hover {
            transform: translateY(-3px);
            box-shadow: var(--card-shadow-hover);
        }

        .action-button:hover::before {
            transform: translateX(100%);
        }

        .camera-container {
            position: relative;
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
            border-radius: 1rem;
            overflow: hidden;
            background: var(--gray-900);
            box-shadow: var(--card-shadow);
        }

        #video, #canvas {
            width: 100%;
            height: auto;
            display: none;
        }

        #selfie-preview {
            width: 100%;
            height: auto;
            border-radius: 1rem;
            display: none;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-weight: 600;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-badge::before {
            content: '';
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }

        .recent-records {
            background: white;
            border-radius: 1.5rem;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--gray-200);
            transition: all var(--transition-speed) ease;
        }

        .recent-records:hover {
            box-shadow: var(--card-shadow-hover);
            border-color: var(--primary-light);
        }

        .recent-records .card-header {
            background: none;
            border-bottom: 1px solid var(--gray-200);
            padding: 1.5rem;
        }

        .recent-records .card-header h5 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--gray-800);
        }

        .recent-records .card-header h5::before {
            content: '';
            display: inline-block;
            width: 4px;
            height: 1.5rem;
            background: var(--primary-color);
            border-radius: 2px;
        }

        .recent-records .table {
            margin-bottom: 0;
        }

        .recent-records th {
            font-weight: 600;
            color: var(--gray-600);
            border-bottom: 2px solid var(--gray-200);
            background: var(--gray-50);
            padding: 1rem;
        }

        .recent-records td {
            padding: 1rem;
            vertical-align: middle;
            transition: all var(--transition-speed) ease;
        }

        .recent-records tr:hover td {
            background-color: var(--gray-50);
        }

        .loading-spinner {
            display: none;
            width: 1.5rem;
            height: 1.5rem;
            border: 3px solid var(--gray-200);
            border-top: 3px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .location-status {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--gray-600);
            font-size: 0.875rem;
            margin-top: 1.5rem;
            padding: 0.75rem;
            background: var(--gray-50);
            border-radius: 0.75rem;
            border: 1px solid var(--gray-200);
        }

        .location-status i {
            color: var(--primary-color);
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .attendance-card {
                border-radius: 1rem;
            }

            .attendance-header {
                padding: 1.25rem;
            }

            .attendance-body {
                padding: 1.5rem;
            }

            .time-display {
                font-size: 2.5rem;
            }

            .action-button {
                padding: 0.875rem 1.75rem;
            }

            .recent-records {
                border-radius: 1rem;
            }

            .recent-records .card-header {
                padding: 1.25rem;
            }

            .recent-records th,
            .recent-records td {
                padding: 0.875rem;
            }
        }

        @media (max-width: 576px) {
            .time-display {
                font-size: 2rem;
            }

            .action-button {
                padding: 0.75rem 1.5rem;
            }

            .location-status {
                font-size: 0.8rem;
                padding: 0.5rem;
            }
        }
    </style>
</head>
<body>
<?php include '../../includes/sidebar.php'; ?>
    
<div class="main-content">
    <div class="container">
        <div class="attendance-card">
            <div class="attendance-header">
                <h4 class="mb-0">Daily Time Record</h4>
            </div>
            <div class="attendance-body text-center">
                <h5 class="mb-4"><?= date('l, F j, Y') ?></h5>
                
                <?php if ($todayRecord && $todayRecord['time_in'] && !$todayRecord['time_out']): ?>
                <!-- Clock Out Section -->
                <div class="time-display">
                    <i class="fas fa-clock"></i> <?= date('h:i A', strtotime($todayRecord['time_in'])) ?>
                </div>
                <p class="text-muted mb-4">You clocked in at <?= date('h:i A', strtotime($todayRecord['time_in'])) ?></p>
                <button id="clockOutBtn" class="btn btn-danger action-button">
                    <i class="fas fa-sign-out-alt"></i> Clock Out
                </button>
                
                <?php elseif ($todayRecord && $todayRecord['time_out']): ?>
                <!-- Already Completed Section -->
                <div class="alert alert-success" role="alert">
                    <h5 class="alert-heading">Time Record Completed!</h5>
                    <div class="time-display">
                        <div>Clock In: <?= date('h:i A', strtotime($todayRecord['time_in'])) ?></div>
                        <div>Clock Out: <?= date('h:i A', strtotime($todayRecord['time_out'])) ?></div>
                    </div>
                </div>
                
                <?php else: ?>
                <!-- Clock In Section -->
                <div class="camera-container mb-4">
                    <video id="video" autoplay playsinline></video>
                    <canvas id="canvas"></canvas>
                    <img id="selfie-preview" alt="Selfie Preview">
                </div>
                <button id="captureBtn" class="btn btn-secondary action-button mb-3">
                    <i class="fas fa-camera"></i> Take Verification Photo
                </button>
                <button id="clockInBtn" class="btn btn-primary action-button" disabled>
                    <i class="fas fa-sign-in-alt"></i> Clock In
                </button>
                <?php endif; ?>
                
                <div class="location-status">
                    <i class="fas fa-map-marker-alt"></i>
                    <span id="locationText">Acquiring location...</span>
                </div>
            </div>
        </div>
        
        <!-- Recent Records -->
        <div class="recent-records">
            <div class="card-header">
                <h5 class="mb-0">Recent Attendance</h5>
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
                                    <span class="status-badge bg-<?= 
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
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
<script>
    // Initialize Pusher
    const pusher = new Pusher('10ac21fee8c24f99545b', {
        cluster: 'ap1'
    });

    // Subscribe to attendance channel
    const channel = pusher.subscribe('attendance-channel');

    // Listen for clock-in events
    channel.bind('clock-in-event', function(data) {
        if (Notification.permission === 'granted') {
            new Notification('Clock In', {
                body: `${data.employee_name} has clocked in at ${new Date(data.time_in).toLocaleTimeString()}`,
                icon: '/assets/img/logo.png'
            });
        }

        if (data.employee_id === '<?php echo $_SESSION['user_data']['employee_id']; ?>') {
            window.location.reload();
        }
    });

    // Listen for clock-out events
    channel.bind('clock-out-event', function(data) {
        if (Notification.permission === 'granted') {
            new Notification('Clock Out', {
                body: `${data.employee_name} has clocked out at ${new Date(data.time_out).toLocaleTimeString()}`,
                icon: '/assets/img/logo.png'
            });
        }

        if (data.employee_id === '<?php echo $_SESSION['user_data']['employee_id']; ?>') {
            window.location.reload();
        }
    });

    // Request notification permission
    if (Notification.permission !== 'granted') {
        Notification.requestPermission();
    }

    // Geolocation
    let currentLocation = null;
    
    function updateLocationStatus(status, error = null) {
        const locationText = document.getElementById('locationText');
        if (error) {
            locationText.innerHTML = `<span class="text-danger">${error}</span>`;
        } else {
            locationText.textContent = status;
        }
    }

    navigator.geolocation.getCurrentPosition(
        (position) => {
            currentLocation = {
                lat: position.coords.latitude,
                lng: position.coords.longitude
            };
            updateLocationStatus('Location captured');
        },
        (error) => {
            console.error("Geolocation error:", error);
            let errorMessage = 'Location access denied';
            if (error.code === error.TIMEOUT) {
                errorMessage = 'Location request timed out';
            } else if (error.code === error.POSITION_UNAVAILABLE) {
                errorMessage = 'Location information unavailable';
            }
            updateLocationStatus(null, errorMessage);
        },
        {
            enableHighAccuracy: true,
            timeout: 5000,
            maximumAge: 0
        }
    );

    // Camera setup for clock in
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const selfiePreview = document.getElementById('selfie-preview');
    const captureBtn = document.getElementById('captureBtn');
    const clockInBtn = document.getElementById('clockInBtn');
    
    if (captureBtn) {
        captureBtn.addEventListener('click', async () => {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ 
                    video: { 
                        facingMode: 'user',
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    } 
                });
                
                video.srcObject = stream;
                video.style.display = 'block';
                selfiePreview.style.display = 'none';
                
                // Enable clock in button
                clockInBtn.disabled = false;
                
                // Stop video stream after 30 seconds if no capture
                setTimeout(() => {
                    if (video.style.display === 'block') {
                        stream.getTracks().forEach(track => track.stop());
                        video.style.display = 'none';
                        Swal.fire({
                            title: 'Camera Timeout',
                            text: 'Camera access timed out. Please try again.',
                            icon: 'warning'
                        });
                    }
                }, 30000);
            } catch (err) {
                console.error("Camera error:", err);
                Swal.fire({
                    title: 'Camera Error',
                    text: 'Could not access camera. Please ensure camera permissions are granted.',
                    icon: 'error'
                });
            }
        });
    }

    // Clock In Handler
    if (clockInBtn) {
        clockInBtn.addEventListener('click', async () => {
            if (!currentLocation) {
                Swal.fire({
                    title: 'Location Required',
                    text: 'Please allow location access to clock in.',
                    icon: 'warning'
                });
                return;
            }

            try {
                // Capture photo
                canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                const imageData = canvas.toDataURL('image/jpeg');
                
                // Show loading state
                clockInBtn.disabled = true;
                clockInBtn.innerHTML = '<span class="loading-spinner"></span> Processing...';

                const response = await fetch('../../api/attendance/clock-in.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': '<?php echo $_SESSION['csrf_token']; ?>'
                    },
                    body: JSON.stringify({
                        latitude: currentLocation.lat,
                        longitude: currentLocation.lng,
                        imageData: imageData
                    })
                });
                
                const result = await response.json();
                if (result.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Clock in recorded successfully!',
                        icon: 'success'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    throw new Error(result.error || 'Failed to record clock in');
                }
            } catch (error) {
                console.error("Error:", error);
                Swal.fire({
                    title: 'Error',
                    text: error.message || 'Failed to record clock in',
                    icon: 'error'
                });
                clockInBtn.disabled = false;
                clockInBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Clock In';
            }
        });
    }

    // Clock Out Handler
    const clockOutBtn = document.getElementById('clockOutBtn');
    if (clockOutBtn) {
        clockOutBtn.addEventListener('click', async () => {
            if (!currentLocation) {
                Swal.fire({
                    title: 'Location Required',
                    text: 'Please allow location access to clock out.',
                    icon: 'warning'
                });
                return;
            }

            const result = await Swal.fire({
                title: 'Clock Out',
                text: 'Are you sure you want to clock out?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Clock Out',
                cancelButtonText: 'Cancel'
            });

            if (result.isConfirmed) {
                try {
                    // Show loading state
                    clockOutBtn.disabled = true;
                    clockOutBtn.innerHTML = '<span class="loading-spinner"></span> Processing...';

                    const response = await fetch('../../api/attendance/clock-out.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': '<?php echo $_SESSION['csrf_token']; ?>'
                        },
                        body: JSON.stringify({
                            latitude: currentLocation.lat,
                            longitude: currentLocation.lng
                        })
                    });
                    
                    const result = await response.json();
                    if (result.success) {
                        Swal.fire({
                            title: 'Success!',
                            text: 'Clock out recorded successfully!',
                            icon: 'success'
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        throw new Error(result.error || 'Failed to record clock out');
                    }
                } catch (error) {
                    console.error("Error:", error);
                    Swal.fire({
                        title: 'Error',
                        text: error.message || 'Failed to record clock out',
                        icon: 'error'
                    });
                    clockOutBtn.disabled = false;
                    clockOutBtn.innerHTML = '<i class="fas fa-sign-out-alt"></i> Clock Out';
                }
            }
        });
    }
</script>
</body>
</html>