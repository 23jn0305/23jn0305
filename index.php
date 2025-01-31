<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>レシートOCR</title>
</head>
<body>
    <h1>レシートアップロードフォーム</h1>
    <form action="process.php" method="post" enctype="multipart/form-data">
        <label for="receipt">レシート画像をアップロード:</label>
        <input type="file" name="receipts[]" id="receipt" multiple accept="image/*">
        <button type="submit">アップロードして解析</button>
    </form>
</body>
</html>
