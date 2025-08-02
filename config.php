<?php
$userInfo = json_decode(file_get_contents('user_info.json'), true);
$devices = json_decode(file_get_contents('devices.json'), true);


// Display success/error messages
$message = '';
if (isset($_GET['success'])) {
    $message = '<div class="alert success">' . htmlspecialchars(urldecode($_GET['success'])) . '</div>';
} elseif (isset($_GET['error'])) {
    $error = urldecode($_GET['error']);
    if ($error === 'IP address already exists') {
        $message = '<div class="alert error">The IP address/hostname already exists in the device list.</div>';
    } elseif ($error === 'Invalid IP or hostname') {
        $message = '<div class="alert error">Please enter a valid IP address or hostname.</div>';
    } else {
        $message = '<div class="alert error">' . htmlspecialchars($error) . '</div>';
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Configuration</title>
    <link rel="stylesheet" href="config_style.css">
</head>
<body>
    <nav>
        <a href="index.php">Home</a>
        <a href="config.php" class="active">Configuration</a>
    </nav>
    <div class="container">
        <h2>User Contact Settings</h2>
        <form method="POST" action="save_user.php">
            <label>Email:</label>
            <input type="email" name="email" value="<?= htmlspecialchars($userInfo['email']) ?>" required>
            
            <label>Phone (+216):</label>
            <input type="tel" name="phone" pattern="\+216\d{8}" value="<?= htmlspecialchars($userInfo['phone']) ?>" required>
            
            <label>Refresh Interval (seconds, 5-300):</label>
            <input type="number" name="interval" min="5" max="300" 
                value="<?= htmlspecialchars($userInfo['refresh_interval'] ?? 15) ?>" required>
            
            <button type="submit">Save Settings</button>
        </form>

        <h2>Add New Device</h2>
        <?php echo $message; ?>
        <form method="POST" action="manage_devices.php">
            <input type="hidden" name="action" value="add">
            
            <label>IP Address / Hostname:</label>
            <input type="text" name="ip" placeholder="e.g., 192.168.1.1 or google.com" required>
            
            <label>Device Type:</label>
            <select name="type" required>
                <option value="server">Server</option>
                <option value="pc">PC</option>
                <option value="phone">Phone</option>
                <option value="printer">Printer</option>
                <option value="router">Router</option>
            </select>
            
            <button type="submit">Add Device</button>
        </form>
        <table>
            <tr>
                <th>IP Address</th>
                <th>Type</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
            <?php foreach ($devices as $index => $device): ?>
                <tr>
                    <td><?= htmlspecialchars($device['ip']) ?></td>
                    <td><?= htmlspecialchars($device['type']) ?></td>
                    <td>
                        <?= htmlspecialchars($device['status']) ?>
                        <?php if ($device['status'] === 'Offline' && !empty($device['last_online'])): ?>
                            <br><small>Last online: <?= htmlspecialchars($device['last_online']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button onclick="showEditModal(<?= $index ?>, '<?= htmlspecialchars($device['ip']) ?>', '<?= htmlspecialchars($device['type']) ?>')"
                                style="background:#2196F3; margin-right:5px;">Edit</button>
                        
                        <form method="POST" action="manage_devices.php" style="display:inline;">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="index" value="<?= $index ?>">
                            <button type="submit" class="delete-btn">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>


    <div id="editModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
        <div style="background:white; width:400px; margin:100px auto; padding:20px; border-radius:5px;">
            <h2>Edit Device</h2>
            <form method="POST" action="manage_devices.php" id="editForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="index" id="editIndex">
                
                <label>IP Address:</label>
                <input type="text" name="ip" id="editIp" required>
                
                <label>Device Type:</label>
                <select name="type" id="editType" required>
                    <option value="pc">PC</option>
                    <option value="printer">Printer</option>
                    <option value="scanner">Scanner</option>
                    <option value="server">Server</option>
                    <option value="phone">Phone</option>
                </select>
                
                <div style="margin-top:20px;">
                    <button type="submit" style="background:#4CAF50;">Save Changes</button>
                    <button type="button" onclick="document.getElementById('editModal').style.display='none'" 
                            style="background:#f44336;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <script src="config_script.js"></script>
</body>
</html>
