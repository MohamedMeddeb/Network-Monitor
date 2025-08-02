<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $phone = preg_match('/^\+216\d{8}$/', $_POST['phone']) ? $_POST['phone'] : null;
    $interval = filter_var($_POST['interval'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 5, 'max_range' => 300]
    ]) ? $_POST['interval'] : 15;

    if ($email && $phone) {
        $data = [
            'email' => $email,
            'phone' => $phone,
            'refresh_interval' => $interval
        ];
        file_put_contents('user_info.json', json_encode($data, JSON_PRETTY_PRINT));
        header('Location: config.php');
        exit;
    } else {
        echo "Invalid input.";
    }
}
?>
