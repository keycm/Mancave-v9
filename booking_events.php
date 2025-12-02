<?php
include 'config.php';

$sql = "SELECT id, service, preferred_date, full_name FROM bookings WHERE status='approved'";
$result = mysqli_query($conn, $sql);

$events = [];
while ($row = mysqli_fetch_assoc($result)) {
    $events[] = [
        'id' => $row['id'],
        'title' => $row['service'] . " - " . $row['full_name'],
        'start' => $row['preferred_date']
    ];
}

echo json_encode($events);
?>
