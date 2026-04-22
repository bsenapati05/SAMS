<?php
include 'db_connection.php'; // Use your actual connection file

$sql = "SELECT id, recipient_email, recipient_name, status, created_at FROM mail_queue ORDER BY created_at DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Mail Delivery Status</title>
    <style>
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        .status-pending { color: orange; font-weight: bold; }
        .status-sent { color: green; font-weight: bold; }
        .status-failed { color: red; font-weight: bold; }
    </style>
</head>
<body>

<h2>Email Delivery Status Monitoring</h2>

<table>
    <tr>
        <th>ID</th>
        <th>Recipient Name</th>
        <th>Email Address</th>
        <th>Status</th>
        <th>Queue Date</th>
    </tr>
    <?php while($row = $result->fetch_assoc()): ?>
    <tr>
        <td><?php echo $row['id']; ?></td>
        <td><?php echo $row['recipient_name']; ?></td>
        <td><?php echo $row['recipient_email']; ?></td>
        <td>
            <span class="status-<?php echo strtolower($row['status']); ?>">
                <?php echo ucfirst($row['status']); ?>
            </span>
        </td>
        <td><?php echo $row['created_at']; ?></td>
    </tr>
    <?php endwhile; ?>
</table>

</body>
</html>