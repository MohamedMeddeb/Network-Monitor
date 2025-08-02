<?php
$devices = json_decode(file_get_contents('devices.json'), true);
$statusCache = json_decode(file_get_contents('status_cache.json'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];

    if ($action === 'add') {
        $ip = trim($_POST['ip']);
        $type = $_POST['type'];
        
        // Normalize the IP/hostname for comparison (case insensitive, remove whitespace)
        $normalizedIp = strtolower(trim($ip));
        
        // Check if IP already exists (case insensitive comparison)
        $ipExists = false;
        foreach ($devices as $device) {
            if (strtolower(trim($device['ip'])) === $normalizedIp) {
                $ipExists = true;
                break;
            }
        }
        
        if ($ipExists) {
            header('Location: config.php?error=IP+address+already+exists');
            exit;
        }
        
        // Validate IP/hostname format
        $isValidIp = filter_var($ip, FILTER_VALIDATE_IP);
        $isValidHostname = preg_match('/^([a-zA-Z0-9\-]+\.)+[a-zA-Z]{2,}$/', $ip);
        
        if ($isValidIp || $isValidHostname) {
            // Add device with default Offline status
            $devices[] = [
                'ip' => $ip,
                'type' => $type,
                'status' => 'Offline',
                'last_online' => null
            ];
            $statusCache[$ip] = 'Offline';
        } else {
            header('Location: config.php?error=Invalid+IP+or+hostname');
            exit;
        }
    } elseif ($action === 'remove') {
        $index = intval($_POST['index']);
        if (isset($devices[$index])) {
            $ipToRemove = $devices[$index]['ip'];
            array_splice($devices, $index, 1);
            
            // Remove from status_cache if it exists
            if (array_key_exists($ipToRemove, $statusCache)) {
                unset($statusCache[$ipToRemove]);
            }
            
            // Save changes with error handling
            if (file_put_contents('devices.json', json_encode($devices, JSON_PRETTY_PRINT))) {
                if (file_put_contents('status_cache.json', json_encode($statusCache, JSON_PRETTY_PRINT))) {
                    header('Location: config.php?success=Device+removed+successfully');
                } else {
                    header('Location: config.php?error=Failed+to+update+status+cache');
                }
            } else {
                header('Location: config.php?error=Failed+to+update+devices');
            }
            exit;
        }
    } elseif ($action === 'edit') {
        $index = intval($_POST['index']);
        if (isset($devices[$index])) {
            $oldIp = $devices[$index]['ip'];
            $ip = trim($_POST['ip']);
            $type = $_POST['type'];
            
            // Normalize the IP/hostname for comparison
            $normalizedIp = strtolower(trim($ip));
            $normalizedOldIp = strtolower(trim($oldIp));
            
            // Check if new IP already exists (and it's not the same as the old IP)
            if ($normalizedIp !== $normalizedOldIp) {
                $ipExists = false;
                foreach ($devices as $i => $device) {
                    if ($i !== $index && strtolower(trim($device['ip'])) === $normalizedIp) {
                        $ipExists = true;
                        break;
                    }
                }
                
                if ($ipExists) {
                    header('Location: config.php?error=IP+address+already+exists');
                    exit;
                }
            }
            
            // Validate IP/hostname format
            $isValidIp = filter_var($ip, FILTER_VALIDATE_IP);
            $isValidHostname = preg_match('/^([a-zA-Z0-9\-]+\.)+[a-zA-Z]{2,}$/', $ip);
            
            if ($isValidIp || $isValidHostname) {
                if ($ip !== $oldIp) {
                    $statusCache[$ip] = $statusCache[$oldIp] ?? 'Offline';
                    unset($statusCache[$oldIp]);
                }
                
                $devices[$index]['ip'] = $ip;
                $devices[$index]['type'] = $type;
            } else {
                header('Location: config.php?error=Invalid+IP+or+hostname');
                exit;
            }
        }
    }

    // Save changes for add/edit actions
    if (file_put_contents('devices.json', json_encode($devices, JSON_PRETTY_PRINT))) {
        if (file_put_contents('status_cache.json', json_encode($statusCache, JSON_PRETTY_PRINT))) {
            header('Location: config.php?success=Changes+saved+successfully');
        } else {
            header('Location: config.php?error=Failed+to+update+status+cache');
        }
    } else {
        header('Location: config.php?error=Failed+to+update+devices');
    }
    exit;
}
?>