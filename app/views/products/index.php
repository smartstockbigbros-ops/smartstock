<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>My App</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>
    <div class="container">
        <h1>📦 Product List</h1>

        <div class="toolbar">
            <a href="<?= site_url('products/create') ?>" class="btn-add">➕ Add Product</a>
            <a href="<?= site_url('logout') ?>" class="btn-logout">🚪 Logout</a>
        </div>

        <table>
            <tr>
                <th>Name</th>
                <th>Price</th>
                <th>Qty</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($products as $p): ?>
            <tr>
                <td><?= html_escape($p['product_name']) ?></td>
                <td><?= number_format($p['price'], 2) ?></td>
                <td><?= html_escape($p['quantity']) ?></td>
                <td>
                    <a href="<?= site_url('products/edit/' . $p['id']) ?>" class="btn-edit">✏️ Edit</a>
                    <a href="<?= site_url('products/delete/' . $p['id']) ?>" class="btn-delete" onclick="return confirm('Delete this product?')">🗑️ Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</body>
</html>