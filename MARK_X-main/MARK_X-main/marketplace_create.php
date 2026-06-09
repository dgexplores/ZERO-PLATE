<?php
require_once __DIR__ . '/config.php';
ensureSessionStarted();
include 'connection.php';
require_once __DIR__ . '/includes/org_flow.php';
mark16_ensure_org_tables($connection);

if (!isset($_SESSION['email']) || $_SESSION['email'] === '') {
    header('Location: signin.php');
    exit();
}
$msg = '';
if (isset($_POST['create']) && verifyCsrfToken($_POST['csrf'] ?? '')) {
    $title = trim((string)($_POST['title'] ?? ''));
    $cat = trim((string)($_POST['category'] ?? 'packed-food'));
    $qty = trim((string)($_POST['quantity'] ?? ''));
    $city = trim((string)($_POST['city'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));
    $price = (float)($_POST['listed_price'] ?? 0);
    $expires = trim((string)($_POST['expires_at'] ?? ''));
    if ($title !== '' && $qty !== '' && $city !== '' && $address !== '' && $price > 0) {
        $st = mysqli_prepare($connection, 'INSERT INTO marketplace_listings (seller_email,title,category,quantity,city,address,listed_price,expires_at) VALUES (?,?,?,?,?,?,?,?)');
        $seller = $_SESSION['email'];
        $exp = $expires !== '' ? str_replace('T', ' ', $expires) . ':00' : null;
        mysqli_stmt_bind_param($st, 'ssssssds', $seller, $title, $cat, $qty, $city, $address, $price, $exp);
        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
        $msg = 'Listing created.';
    } else {
        $msg = 'Fill all required fields.';
    }
}
$csrf = getCsrfToken();
?>
<!DOCTYPE html><html><head><meta charset="utf-8"><title>Create Surplus Listing</title><link rel="stylesheet" href="loginstyle.css"></head><body style="padding:20px;">
<h2>Restaurant / Donor Surplus Listing</h2>
<p><a href="profile.php">Back to profile</a></p>
<?php if ($msg): ?><p><?php echo htmlspecialchars($msg); ?></p><?php endif; ?>
<form method="post" style="max-width:520px;display:grid;gap:10px;">
<input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
<label>Title <input name="title" required></label>
<label>Category <select name="category"><option value="packed-food">Packed Food</option><option value="cooked-food">Cooked Food</option><option value="raw-food">Raw Food</option></select></label>
<label>Quantity <input name="quantity" required></label>
<label>City <input name="city" required></label>
<label>Address <textarea name="address" required></textarea></label>
<label>Discounted Price (INR) <input type="number" step="0.01" min="1" name="listed_price" required></label>
<label>Listing Expiry <input type="datetime-local" name="expires_at"></label>
<button type="submit" name="create" value="1">Create Listing</button>
</form>
</body></html>
