<?php
require_once '../../auth/check_login.php';
require_once(__DIR__ . '/../../config/dbcon.php');
require_once(__DIR__ . '/../../config/maps.php');

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
            --primary: #4e73df;
            --primary-light: #7e9eff;
            --primary-dark: #2c56c9;
            --success: #1cc88a;
            --danger: #e74a3b;
            --warning: #f6c23e;
            --info: #36b9cc;
            --light: #f8f9fc;
            --dark: #5a5c69;
        }
        
        body {
            background-color: #f8f9fc;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        
        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            background: white;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 2rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid #e3e6f0;
            padding: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            border-radius: 1rem 1rem 0 0 !important;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .card-header i {
            font-size: 1.25rem;
            color: var(--primary);
        }
        
        .time-card {
            border-left: 0.5rem solid var(--primary);
        }
        
        .time-display {
            font-size: 3.5rem;
            font-weight: 800;
            color: var(--dark);
            margin: 2rem 0;
            line-height: 1.2;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }
        
        .btn-clock {
            padding: 1rem 2rem;
            font-weight: 700;
            border-radius: 0.75rem;
            min-width: 220px;
            transition: all 0.3s;
            margin: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
            box-shadow: 0 4px 6px rgba(50, 50, 93, 0.11), 0 1px 3px rgba(0, 0, 0, 0.08);
        }
        
        .btn-clock:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 14px rgba(50, 50, 93, 0.1), 0 3px 6px rgba(0, 0, 0, 0.08);
        }
        
        .btn-clock i {
            font-size: 1.1rem;
        }
        
        .camera-container {
            border-radius: 1rem;
            overflow: hidden;
            background: #000;
            margin: 2rem 0;
            position: relative;
            aspect-ratio: 4/3;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
            display: none;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        #video, #canvas {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: none;
        }
        
        #selfie-preview {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 1rem;
            display: none;
        }
        
        .status-badge {
            padding: 0.6rem 1rem;
            border-radius: 0.75rem;
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .attendance-summary {
            border-left: 0.5rem solid var(--success);
        }
        
        .location-preview {
            height: 200px;
            border-radius: 1rem;
            margin-top: 1rem;
            overflow: hidden;
            display: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .location-preview.active {
            display: block;
        }

        #locationMap {
            width: 100%;
            height: 100%;
            border-radius: 1rem;
        }

        /* Leaflet map custom styles */
        .leaflet-container {
            border-radius: 1rem;
        }

        .leaflet-control-zoom {
            border: none !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
        }

        .leaflet-control-zoom a {
            background-color: white !important;
            color: #333 !important;
            border: none !important;
        }

        .leaflet-control-zoom a:hover {
            background-color: #f8f9fc !important;
        }

        .location-status {
            background-color: #f8f9fc;
            border-radius: 1rem;
            padding: 1.25rem;
            margin-top: 2rem;
            border: 1px solid #e3e6f0;
            font-size: 0.95rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .location-status i {
            font-size: 1.25rem;
            color: var(--primary);
        }

        .location-details {
            margin-top: 0.5rem;
            font-size: 0.9rem;
            color: #666;
        }
        
        .spinner-border {
            width: 1.2rem;
            height: 1.2rem;
            border-width: 0.2em;
        }

        .table {
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0 0.5rem;
        }

        .table th {
            font-weight: 700;
            color: var(--dark);
            border-top: none;
            padding: 1.25rem 1rem;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .table td {
            padding: 1.25rem 1rem;
            vertical-align: middle;
            background: white;
        }

        .table tr {
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border-radius: 0.75rem;
            transition: all 0.3s ease;
        }

        .table tr:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .table tr td:first-child {
            border-radius: 0.75rem 0 0 0.75rem;
        }

        .table tr td:last-child {
            border-radius: 0 0.75rem 0.75rem 0;
        }

        .main-content {
            padding: 2.5rem;
        }

        .page-header {
            margin-bottom: 2.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #e3e6f0;
        }

        .page-header h1 {
            font-weight: 800;
            color: var(--dark);
            font-size: 2rem;
            margin: 0;
        }

        .date-badge {
            background: var(--primary);
            color: white;
            padding: 0.75rem 1.25rem;
            border-radius: 0.75rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 4px 6px rgba(50, 50, 93, 0.11);
        }

        .date-badge i {
            font-size: 1.1rem;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1.5rem;
            }

            .time-display {
                font-size: 2.5rem;
                margin: 1.5rem 0;
            }
            
            .btn-clock {
                min-width: 180px;
                padding: 0.85rem 1.5rem;
                margin: 0.5rem;
            }

            .card-header {
                padding: 1.25rem;
            }

            .table th, .table td {
                padding: 1rem 0.75rem;
            }

            .page-header {
                margin-bottom: 2rem;
                padding-bottom: 1rem;
            }

            .page-header h1 {
                font-size: 1.75rem;
            }
        }
    </style>
    <!-- Add Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script>
        // Initialize Google Maps API
        function initMap() {
            if (typeof google === 'undefined') {
                console.error('Google Maps API failed to load');
                return;
            }

            const mapElement = document.getElementById('locationMap');
            if (!mapElement) return;

            const map = new google.maps.Map(mapElement, {
                center: { lat: 0, lng: 0 },
                zoom: 15,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: false
            });

            const marker = new google.maps.marker.AdvancedMarkerElement({
                map: map,
                position: { lat: 0, lng: 0 },
                title: 'Your Location'
            });

            window.attendanceMap = { map, marker };
        }
    </script>
    <script async defer
        src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_MAPS_API_KEY; ?>&callback=initMap&libraries=marker">
    </script>
</head>
<body>
<?php include '../../includes/sidebar.php'; ?>
    
<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="page-header d-sm-flex align-items-center justify-content-between">
            <h1>Time Tracking</h1>
            <div class="d-none d-sm-inline-block">
                <span class="date-badge">
                    <i class="fas fa-calendar-alt"></i> <?= date('l, F j, Y') ?>
                </span>
            </div>
        </div>
        
        <!-- Content Row -->
        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="card time-card h-100">
                    <div class="card-header d-flex align-items-center">
                        <i class="fas fa-clock me-2"></i>
                        <h6 class="m-0 font-weight-bold">Daily Time Record</h6>
                    </div>
                    <div class="card-body text-center py-4">
                        <?php if ($todayRecord && $todayRecord['time_in'] && !$todayRecord['time_out']): ?>
                        <!-- Clock Out Section -->
                        <div class="time-display mb-3">
                            <i class="fas fa-sign-in-alt text-primary"></i> <?= date('h:i A', strtotime($todayRecord['time_in'])) ?>
                        </div>
                        <p class="text-muted mb-4">You clocked in at <?= date('h:i A', strtotime($todayRecord['time_in'])) ?></p>
                        <button id="clockOutBtn" class="btn btn-danger btn-clock">
                            <i class="fas fa-sign-out-alt me-2"></i> Clock Out
                        </button>
                        
                        <?php elseif ($todayRecord && $todayRecord['time_out']): ?>
                        <!-- Already Completed Section -->
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <h4 class="alert-heading"><i class="fas fa-check-circle"></i> Time Record Completed!</h4>
                            <hr>
                            <div class="time-display">
                                <div class="mb-2">
                                    <i class="fas fa-sign-in-alt text-primary me-2"></i>
                                    <?= date('h:i A', strtotime($todayRecord['time_in'])) ?>
                                </div>
                                <div>
                                    <i class="fas fa-sign-out-alt text-danger me-2"></i>
                                    <?= date('h:i A', strtotime($todayRecord['time_out'])) ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php else: ?>
                        <!-- Clock In Section -->
                        <div class="camera-container mb-4">
                            <video id="video" autoplay playsinline></video>
                            <canvas id="canvas"></canvas>
                            <img id="selfie-preview" alt="Selfie Preview">
                        </div>
                        <button id="captureBtn" class="btn btn-secondary btn-clock mb-3">
                            <i class="fas fa-camera me-2"></i> Take Photo
                        </button>
                        <button id="clockInBtn" class="btn btn-primary btn-clock" disabled>
                            <i class="fas fa-sign-in-alt me-2"></i> Clock In
                        </button>
                        <?php endif; ?>
                        
                        <div class="location-status text-start">
                            <i class="fas fa-map-marker-alt text-primary me-2"></i>
                            <span id="locationText">Acquiring location...</span>
                            <div class="location-details" id="locationDetails"></div>
                            <div class="location-preview" id="locationPreview">
                                <div id="locationMap"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center">
                        <i class="fas fa-history me-2"></i>
                        <h6 class="m-0 font-weight-bold">Recent Attendance</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Clock In</th>
                                        <th>Clock Out</th>
                                        <th>Status</th>
                                        <th>Location</th>
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
                                            <?php 
                                            $badgeClass = '';
                                            if ($record['status'] === 'Present') $badgeClass = 'bg-success';
                                            elseif ($record['status'] === 'Late') $badgeClass = 'bg-warning text-dark';
                                            elseif ($record['status'] === 'On Leave') $badgeClass = 'bg-info';
                                            else $badgeClass = 'bg-secondary';
                                            ?>
                                            <span class="badge <?= $badgeClass ?> status-badge">
                                                <?= $record['status'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($record['clock_in_latitude'] && $record['clock_in_longitude']): ?>
                                                <div class="mb-1">
                                                    <a href="https://www.google.com/maps?q=<?= $record['clock_in_latitude'] ?>,<?= $record['clock_in_longitude'] ?>" 
                                                       target="_blank" 
                                                       class="text-primary text-decoration-none">
                                                        <i class="fas fa-sign-in-alt"></i> Clock-in Location
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($record['clock_out_latitude'] && $record['clock_out_longitude']): ?>
                                                <div>
                                                    <a href="https://www.google.com/maps?q=<?= $record['clock_out_latitude'] ?>,<?= $record['clock_out_longitude'] ?>" 
                                                       target="_blank" 
                                                       class="text-primary text-decoration-none">
                                                        <i class="fas fa-sign-out-alt"></i> Clock-out Location
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!$record['clock_in_latitude'] && !$record['clock_out_latitude']): ?>
                                                <span class="text-muted">No location data</span>
                                            <?php endif; ?>
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
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
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

    // Geolocation with map preview
    let currentLocation = null;
    let map = null;
    let marker = null;
    
    function initMap() {
        if (map) return; // Map already initialized

        map = L.map('locationMap').setView([0, 0], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);
    }

    function updateLocationStatus(status, error = null) {
        const locationText = document.getElementById('locationText');
        const locationDetails = document.getElementById('locationDetails');
        const locationPreview = document.getElementById('locationPreview');
        
        if (error) {
            locationText.innerHTML = `<span class="text-danger">${error}</span>`;
            locationPreview.classList.remove('active');
        } else {
            locationText.textContent = status;
            if (currentLocation) {
                locationDetails.textContent = `Latitude: ${currentLocation.lat.toFixed(6)}, Longitude: ${currentLocation.lng.toFixed(6)}`;
                locationPreview.classList.add('active');
                
                // Initialize map if not already done
                if (!map) {
                    initMap();
                }

                // Update map view and marker
                map.setView([currentLocation.lat, currentLocation.lng], 15);
                if (marker) {
                    marker.setLatLng([currentLocation.lat, currentLocation.lng]);
                } else {
                    marker = L.marker([currentLocation.lat, currentLocation.lng]).addTo(map);
                }

                // FIX: Invalidate map size after showing
                setTimeout(() => {
                    map.invalidateSize();
                }, 200);
            }
        }
    }

    // Watch for location changes
    if ("geolocation" in navigator) {
        const watchId = navigator.geolocation.watchPosition(
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

        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            navigator.geolocation.clearWatch(watchId);
            if (map) {
                map.remove();
            }
        });
    } else {
        updateLocationStatus(null, 'Geolocation is not supported by your browser');
    }

    // Camera setup for clock in
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const selfiePreview = document.getElementById('selfie-preview');
    const captureBtn = document.getElementById('captureBtn');
    const clockInBtn = document.getElementById('clockInBtn');
    const cameraContainer = document.querySelector('.camera-container');
    let mediaStream = null;
    
    if (captureBtn) {
        captureBtn.addEventListener('click', async () => {
            try {
                // Show camera container
                cameraContainer.style.display = 'block';
                
                // Stop any existing stream
                if (mediaStream) {
                    mediaStream.getTracks().forEach(track => track.stop());
                }

                const stream = await navigator.mediaDevices.getUserMedia({ 
                    video: { 
                        facingMode: 'user',
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    } 
                });
                
                mediaStream = stream;
                video.srcObject = stream;
                video.style.display = 'block';
                selfiePreview.style.display = 'none';
                
                // Enable clock in button
                clockInBtn.disabled = false;
                captureBtn.innerHTML = '<i class="fas fa-sync-alt me-2"></i> Retake Photo';
                
                // Stop video stream after 30 seconds if no capture
                setTimeout(() => {
                    if (video.style.display === 'block') {
                        stream.getTracks().forEach(track => track.stop());
                        video.style.display = 'none';
                        cameraContainer.style.display = 'none';
                        Swal.fire({
                            title: 'Camera Timeout',
                            text: 'Camera access timed out. Please try again.',
                            icon: 'warning'
                        });
                    }
                }, 30000);
            } catch (err) {
                console.error("Camera error:", err);
                cameraContainer.style.display = 'none';
                Swal.fire({
                    title: 'Camera Error',
                    text: 'Could not access camera. Please ensure camera permissions are granted.',
                    icon: 'error'
                });
            }
        });

        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            if (mediaStream) {
                mediaStream.getTracks().forEach(track => track.stop());
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

            if (!video.style.display === 'block') {
                Swal.fire({
                    title: 'Photo Required',
                    text: 'Please take a photo before clocking in.',
                    icon: 'warning'
                });
                return;
            }

            try {
                // Capture photo
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                const imageData = canvas.toDataURL('image/jpeg', 0.8);
                
                // Show preview
                selfiePreview.src = imageData;
                selfiePreview.style.display = 'block';
                video.style.display = 'none';
                
                // Stop video stream
                if (mediaStream) {
                    mediaStream.getTracks().forEach(track => track.stop());
                }
                
                // Show loading state
                clockInBtn.disabled = true;
                clockInBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Processing...';

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
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
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
                clockInBtn.innerHTML = '<i class="fas fa-sign-in-alt me-2"></i> Clock In';
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
                title: 'Confirm Clock Out',
                text: 'Are you sure you want to clock out?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Clock Out',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#e74a3b'
            });

            if (result.isConfirmed) {
                try {
                    // Show loading state
                    clockOutBtn.disabled = true;
                    clockOutBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Processing...';

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
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
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
                    clockOutBtn.innerHTML = '<i class="fas fa-sign-out-alt me-2"></i> Clock Out';
                }
            }
        });
    }
</script>
</body>
</html>