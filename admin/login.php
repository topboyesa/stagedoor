<?php
session_start();
require __DIR__ . '/../config/admin.php';
require __DIR__ . '/../includes/functions.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if (hash_equals(ADMIN_PASSWORD, $password)) {
        $_SESSION['is_admin'] = true;
        header('Location: dashboard.php');
        exit;
    } else {
        $errors[] = 'Incorrect password.';
    }
}

$page_title = 'Admin login';
$base = '../';
require __DIR__ . '/../includes/header.php';
?>

<div class="page">
    <div class="section" style="margin-top:24px;">
        <h2>Admin login</h2>
    </div>

    <?php foreach ($errors as $err): ?>
        <p class="error"><?= e($err) ?></p>
    <?php endforeach; ?>

    <form method="post">
        <div class="field-group">
            <label>Password</label>
            <input type="password" name="password">
        </div>
        <button type="submit" class="btn-primary">Log in</button>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
