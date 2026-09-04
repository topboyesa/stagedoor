<?php
session_start();
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/functions.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '') $errors[] = 'Enter your name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with that email already exists.';
        }
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'creator')");
        $stmt->execute([$name, $email, $hash]);

        $_SESSION['creator_id'] = $pdo->lastInsertId();
        $_SESSION['creator_name'] = $name;
        header('Location: dashboard.php');
        exit;
    }
}

$page_title = 'Creator sign up';
$base = '../';
require __DIR__ . '/../includes/header.php';
?>

<div class="page">
    <div class="section" style="margin-top:24px;">
        <h2>Create your creator account</h2>
    </div>

    <?php foreach ($errors as $err): ?>
        <p class="error"><?= e($err) ?></p>
    <?php endforeach; ?>

    <form method="post">
        <div class="field-group">
            <label>Full name</label>
            <input type="text" name="name" value="<?= e($_POST['name'] ?? '') ?>">
        </div>
        <div class="field-group">
            <label>Email</label>
            <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>">
        </div>
        <div class="field-group">
            <label>Password</label>
            <input type="password" name="password">
        </div>
        <button type="submit" class="btn-primary">Sign up</button>
    </form>

    <p style="color:var(--muted);margin-top:20px;">
        Already have an account? <a href="login.php" style="color:var(--amber);">Log in</a>
    </p>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
