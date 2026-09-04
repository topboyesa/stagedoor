<?php
session_start();
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/functions.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'creator'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['creator_id'] = $user['id'];
        $_SESSION['creator_name'] = $user['name'];
        header('Location: dashboard.php');
        exit;
    } else {
        $errors[] = 'Incorrect email or password.';
    }
}

$page_title = 'Creator log in';
$base = '../';
require __DIR__ . '/../includes/header.php';
?>

<div class="page">
    <div class="section" style="margin-top:24px;">
        <h2>Creator log in</h2>
    </div>

    <?php foreach ($errors as $err): ?>
        <p class="error"><?= e($err) ?></p>
    <?php endforeach; ?>

    <form method="post">
        <div class="field-group">
            <label>Email</label>
            <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>">
        </div>
        <div class="field-group">
            <label>Password</label>
            <input type="password" name="password">
        </div>
        <button type="submit" class="btn-primary">Log in</button>
    </form>

    <p style="color:var(--muted);margin-top:20px;">
        No account yet? <a href="register.php" style="color:var(--amber);">Sign up</a>
    </p>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
