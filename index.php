<?php
// Load user info to get refresh interval
$userInfo = json_decode(file_get_contents('user_info.json'), true);
$refreshInterval = $userInfo['refresh_interval'] ?? 15;

// Get current filter values from URL
$currentStatus = $_GET['status'] ?? '';
$currentIp = $_GET['ip'] ?? '';

// Handle AJAX data request
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $command = escapeshellcmd('python3 ping_monitor.py ' . time());
    $output = shell_exec($command);
    
    // Also prepare chart data for AJAX response
    $statusCache = json_decode(file_get_contents('status_cache.json'), true);
    $devicesData = json_decode(file_get_contents('devices.json'), true);
    
    // Calculate device type counts
    $deviceTypeCounts = [];
    foreach ($devicesData as $device) {
        $type = $device['type'] ?? 'Unknown';
        $ip = $device['ip'];
        $status = $statusCache[$ip] ?? 'Offline';
        
        if (!isset($deviceTypeCounts[$type])) {
            $deviceTypeCounts[$type] = ['online' => 0, 'total' => 0];
        }
        
        $deviceTypeCounts[$type]['total']++;
        if ($status === 'Online') {
            $deviceTypeCounts[$type]['online']++;
        }
    }
    
    $responseData = json_decode($output, true);
    $responseData['chartData'] = $deviceTypeCounts;
    
    echo json_encode($responseData);
    exit;
}

// Handle single IP ping request
if (isset($_GET['ping_ip'])) {
    $ip_raw = $_GET['ping_ip'];
    header('Content-Type: application/json');

    if (!empty($ip_raw)) {
        $ip = escapeshellarg($ip_raw);
        $singleCommand = "python3 ping_single.py $ip";
        $singleOutput = shell_exec($singleCommand);

        $decoded = json_decode($singleOutput, true);
        if ($decoded !== null) {
            echo json_encode($decoded);
        } else {
            echo json_encode(["error" => "Invalid response from ping script"]);
        }
    } else {
        echo json_encode(["error" => "Empty IP/host"]);
    }
    exit;
}

// Load cached data and device info for initial display
$statusCache = json_decode(file_get_contents('status_cache.json'), true);
$devicesData = json_decode(file_get_contents('devices.json'), true);

// Create a direct IP-to-device mapping for efficient lookup
$devicesMap = [];
foreach ($devicesData as $device) {
    $devicesMap[$device['ip']] = $device;
}

// Prepare display data - only include IPs that exist in devices.json
$displayData = [];
foreach ($statusCache as $ip => $status) {
    if (isset($devicesMap[$ip])) {
        $displayData[$ip] = [
            'status' => $status,
            'last_online' => $devicesMap[$ip]['last_online'] ?? null
        ];
    }
}

// Calculate initial chart data
$deviceTypeCounts = [];
foreach ($devicesData as $device) {
    $type = $device['type'] ?? 'Unknown';
    $ip = $device['ip'];
    $status = $statusCache[$ip] ?? 'Offline';
    
    if (!isset($deviceTypeCounts[$type])) {
        $deviceTypeCounts[$type] = ['online' => 0, 'total' => 0];
    }
    
    $deviceTypeCounts[$type]['total']++;
    if ($status === 'Online') {
        $deviceTypeCounts[$type]['online']++;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Network Status Monitor</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="index_style.css">
</head>
<body>
    <nav>
        <a href="index.php" class="active">Home</a>
        <a href="config.php">Configuration</a>
    </nav>

    <div class="main-container">
        <div class="status-container">
            <h1>Network Status Monitor</h1>
            <div class="timestamp">
                Last checked: Loading...
                <br>Auto-refreshes every <?php echo $refreshInterval; ?> seconds
            </div>

            <div class="loading" id="loadingIndicator">Checking status...</div>

            <div class="filters">
                <form id="filterForm" method="GET">
                    <div style="display: flex; gap: 15px; margin-bottom: 20px;">
                        <div>
                            <label for="statusFilter">Status:</label>
                            <select id="statusFilter" name="status" onchange="applyFilters()">
                                <option value="">All</option>
                                <option value="Online" <?= isset($_GET['status']) && $_GET['status'] === 'Online' ? 'selected' : '' ?>>Online</option>
                                <option value="Offline" <?= isset($_GET['status']) && $_GET['status'] === 'Offline' ? 'selected' : '' ?>>Offline</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="ipFilter">IP/Domain:</label>
                            <input type="text" id="ipFilter" name="ip" placeholder="Filter by IP/domain" 
                                   value="<?= htmlspecialchars($_GET['ip'] ?? '') ?>" oninput="applyFilters()">
                        </div>
                        
                        <button type="button" onclick="resetFilters()">Reset Filters</button>
                    </div>
                </form>
            </div>

            <div class="status-header">
                <span>IP Address</span>
                <span>Status</span>
            </div>

            <div id="statusItems">
                <?php foreach ($displayData as $ip => $data): ?>
                    <?php 
                    if (!empty($currentStatus) && $data['status'] !== $currentStatus) continue;
                    if (!empty($currentIp) && stripos($ip, $currentIp) === false) continue;
                    ?>
                    <div class="status-item <?php echo strtolower($data['status']); ?>" id="ip-<?php echo str_replace('.', '-', $ip); ?>">
                        <div>
                            <span class="ip-address"><?php echo htmlspecialchars($ip); ?></span>
                            <?php if ($data['status'] === 'Offline' && !empty($data['last_online'])): ?>
                                <div class="last-online">Last online: <?php echo htmlspecialchars($data['last_online']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span class="status-text"><?php echo $data['status'] === 'Online' ? '🟢 Online' : '🔴 Offline'; ?></span>
                            <button onclick="testSingleIP('<?php echo htmlspecialchars($ip); ?>')" class="test-btn">Test Now</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="controls">
                <button onclick="loadFreshData()">Refresh All</button>
                <button onclick="testAllIPs()">Test All IPs</button>
            </div>
        </div>

        <div class="chart-container">
            <div class="chart-title">Online Devices by Type</div>
            <div class="chart-wrapper">
                <canvas id="deviceTypesChart"></canvas>
            </div>
            <div class="chart-legend">
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #4CAF50;"></div>
                    <span>Online</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #f44336;"></div>
                    <span>Offline</span>
                </div>
            </div>
        </div>
    </div>

    <script>
    let deviceTypesChart = null;
    let currentChartData = {};

    // Initialize chart only once
    function initializeChart(chartData) {
        if (deviceTypesChart) {
            updateChartData(chartData);
            return;
        }

        const ctx = document.getElementById('deviceTypesChart').getContext('2d');
        currentChartData = chartData;
        
        // Prepare data for chart
        const labels = Object.keys(chartData);
        const onlineData = labels.map(type => chartData[type].online);
        const offlineData = labels.map(type => chartData[type].total - chartData[type].online);

        deviceTypesChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Online',
                        data: onlineData,
                        backgroundColor: '#4CAF50',
                        borderColor: '#45a049',
                        borderWidth: 1
                    },
                    {
                        label: 'Offline',
                        data: offlineData,
                        backgroundColor: '#f44336',
                        borderColor: '#da190b',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 300 // Shorter animation
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Device Types'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        stepSize: 1,
                        title: {
                            display: true,
                            text: 'Number of Devices'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false // We'll use custom legend
                    },
                    tooltip: {
                        callbacks: {
                            afterLabel: function(context) {
                                const type = context.label;
                                const total = currentChartData[type].total;
                                const online = currentChartData[type].online;
                                return `Total: ${total} | Online: ${online} | Offline: ${total - online}`;
                            }
                        }
                    }
                }
            }
        });
    }

    // Update chart data without recreating the chart
    function updateChartData(chartData) {
        if (!deviceTypesChart) return;
        
        currentChartData = chartData;
        const labels = Object.keys(chartData);
        const onlineData = labels.map(type => chartData[type].online);
        const offlineData = labels.map(type => chartData[type].total - chartData[type].online);

        // Check if we need to add/remove labels
        const currentLabels = deviceTypesChart.data.labels;
        const labelsChanged = JSON.stringify(currentLabels) !== JSON.stringify(labels);

        if (labelsChanged) {
            // Only recreate if labels changed (new device types added/removed)
            deviceTypesChart.destroy();
            deviceTypesChart = null;
            initializeChart(chartData);
        } else {
            // Just update the data
            deviceTypesChart.data.datasets[0].data = onlineData;
            deviceTypesChart.data.datasets[1].data = offlineData;
            deviceTypesChart.update('none'); // No animation for smoother updates
        }
    }

    // Initial load
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize chart with PHP data
        const initialChartData = <?php echo json_encode($deviceTypeCounts); ?>;
        initializeChart(initialChartData);
        loadFreshData();
    });

    // Filter functions
    function applyFilters() {
        const form = document.getElementById('filterForm');
        const params = new URLSearchParams(new FormData(form));
        window.location.search = params.toString();
    }

    function resetFilters() {
        window.location.search = '';
    }

    // Status update functions
    function updateUI(data) {
        document.querySelector('.timestamp').innerHTML = 
            `Last checked: ${new Date().toLocaleString()}<br>
             Auto-refreshes every <?php echo $refreshInterval; ?> seconds`;
        
        // Update existing items
        Object.entries(data.ips).forEach(([ip, status]) => {
            const element = document.getElementById(`ip-${ip.replace(/\./g, '-')}`);
            if (element) {
                element.className = `status-item ${status.toLowerCase()}`;
                element.querySelector('.status-text').textContent = 
                    status === 'Online' ? '🟢 Online' : '🔴 Offline';
            }
        });

        // Update chart if chart data is available
        if (data.chartData) {
            updateChartData(data.chartData);
        }
    }

    let refreshTimeout = null;

    function loadFreshData() {
        // Clear any pending refresh
        if (refreshTimeout) {
            clearTimeout(refreshTimeout);
        }

        document.getElementById('loadingIndicator').style.display = 'block';
        
        fetch(`?ajax=1&t=${Date.now()}`)
            .then(r => r.json())
            .then(data => {
                updateUI(data);
                document.getElementById('loadingIndicator').style.display = 'none';
                
                // Reset the refresh timer
                refreshTimeout = setTimeout(loadFreshData, <?php echo $refreshInterval * 1000; ?>);
            })
            .catch(e => {
                console.error('Refresh failed:', e);
                document.getElementById('loadingIndicator').style.display = 'none';
                refreshTimeout = setTimeout(loadFreshData, 5000);
            });
    }

    async function testAllIPs() {
        const testAllBtn = document.querySelector('.controls button:last-child');
        testAllBtn.disabled = true;
        const originalText = testAllBtn.textContent;
        testAllBtn.textContent = 'Testing...';

        const items = document.querySelectorAll('.status-item');
        for (const item of items) {
            if (item.style.display !== 'none') {
                const ip = item.querySelector('.ip-address').textContent;
                await testSingleIP(ip);
                await new Promise(resolve => setTimeout(resolve, 500));
            }
        }

        testAllBtn.disabled = false;
        testAllBtn.textContent = originalText;
        
        // Refresh chart data after testing all IPs
        loadFreshData();
    }

    // Single IP test function
    async function testSingleIP(ip) {
        const ipElement = document.getElementById(`ip-${ip.replace(/\./g, '-')}`);
        const statusElement = ipElement.querySelector('.status-text');
        const testBtn = ipElement.querySelector('.test-btn');
        
        // Find existing last online element or create container for it
        let lastOnlineElement = ipElement.querySelector('.last-online');
        
        statusElement.textContent = 'Testing...';
        testBtn.disabled = true;

        try {
            const response = await fetch(`?ping_ip=${encodeURIComponent(ip)}`);
            const data = await response.json();

            if (data.error) {
                alert(data.error);
                statusElement.textContent = 'Error';
            } else {
                statusElement.textContent = data.status === 'Online' ? '🟢 Online' : '🔴 Offline';
                ipElement.className = 'status-item ' + data.status.toLowerCase();
                
                // Handle last online display
                if (data.status === 'Offline' && data.last_online) {
                    if (!lastOnlineElement) {
                        // Create new element if it doesn't exist
                        lastOnlineElement = document.createElement('div');
                        lastOnlineElement.className = 'last-online';
                        // Insert after IP address
                        ipElement.querySelector('.ip-address').after(lastOnlineElement);
                    }
                    // Update text content
                    lastOnlineElement.textContent = `Last online: ${data.last_online}`;
                } else if (lastOnlineElement) {
                    // Remove if device is online
                    lastOnlineElement.remove();
                }
            }
        } catch (error) {
            console.error('Error:', error);
            statusElement.textContent = 'Error';
        } finally {
            testBtn.disabled = false;
        }
    }
    </script>
</body>
</html>