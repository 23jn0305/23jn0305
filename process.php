<?php
require 'vendor/autoload.php'; // ComposerでAzure SDKを読み込む
use GuzzleHttp\Client;

// Azure SQL接続情報
$serverName = "23jn0305.database.windows.net";
$databaseName = "AIdb";
$username = "AIdb";
$password = "Pa$$word1234";

// Azure AI Vision APIの設定
function ocrImage($filePath) {
    $endpoint = "https://myvisionresourceai.cognitiveservices.azure.com/.api.cognitive.microsoft.com/vision/v3.2/ocr";
    $subscriptionKey = "E3s6dPAVLLjCMTgp7taniZYo7ndDFEjUhUDO2MinKs48PTVVco1rJQQJ99BAACi0881XJ3w3AAAFACOG4GD8";

    $client = new Client();
    $response = $client->post($endpoint, [
        'headers' => [
            'Ocp-Apim-Subscription-Key' => $subscriptionKey,
            'Content-Type' => 'application/octet-stream',
        ],
        'body' => file_get_contents($filePath)
    ]);

    return json_decode($response->getBody(), true);
}

// OCR結果からデータを抽出
function parseReceiptData($ocrResult) {
    $lines = $ocrResult['regions'][0]['lines'];
    $items = [];
    $totalPrice = 0;

    foreach ($lines as $line) {
        $text = implode('', array_column($line['words'], 'text'));

        // 商品名と値段の抽出
        if (preg_match('/(.+?)¥([0-9]+)/u', $text, $matches)) {
            $items[] = ['name' => $matches[1], 'price' => (int)$matches[2]];
        }

        // 合計金額の抽出
        if (preg_match('/合計.+?¥([0-9]+)/u', $text, $matches)) {
            $totalPrice = (int)$matches[1];
        }
    }

    return [$items, $totalPrice];
}

// データベースに接続
function connectToDatabase($serverName, $databaseName, $username, $password) {
    try {
        $conn = new PDO("sqlsrv:server=$serverName;Database=$databaseName", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch (PDOException $e) {
        die("データベース接続に失敗しました: " . $e->getMessage());
    }
}

// データをデータベースに保存
function saveToDatabase($conn, $items, $totalPrice) {
    foreach ($items as $item) {
        $stmt = $conn->prepare("INSERT INTO ReceiptData (item_name, price, total_price) VALUES (:item_name, :price, :total_price)");
        $stmt->bindParam(':item_name', $item['name']);
        $stmt->bindParam(':price', $item['price']);
        $stmt->bindParam(':total_price', $totalPrice);
        $stmt->execute();
    }
}

// ファイルアップロード処理
$uploadedFiles = $_FILES['receipts'];
$resultData = [];
$conn = connectToDatabase($serverName, $databaseName, $username, $password);

foreach ($uploadedFiles['tmp_name'] as $index => $tmpName) {
    if ($uploadedFiles['error'][$index] === UPLOAD_ERR_OK) {
        $ocrResult = ocrImage($tmpName);

        // OCR結果をログファイルに保存
        file_put_contents('ocr.log', print_r($ocrResult, true), FILE_APPEND);

        [$items, $totalPrice] = parseReceiptData($ocrResult);

        // データベースに保存
        saveToDatabase($conn, $items, $totalPrice);

        $resultData[] = ['items' => $items, 'totalPrice' => $totalPrice];
    }
}

// CSV生成
$csvFile = 'output.csv';
$fp = fopen($csvFile, 'w');
fputcsv($fp, ['商品名', '価格', '合計金額']);

foreach ($resultData as $data) {
    foreach ($data['items'] as $item) {
        fputcsv($fp, [$item['name'], $item['price'], $data['totalPrice']]);
    }
}
fclose($fp);

echo "<h1>OCR解析結果</h1>";
foreach ($resultData as $data) {
    echo "<ul>";
    foreach ($data['items'] as $item) {
        echo "<li>{$item['name']} - ¥{$item['price']}</li>";
    }
    echo "<li>合計: ¥{$data['totalPrice']}</li>";
    echo "</ul>";
}
echo "<a href='$csvFile'>CSVダウンロード</a>";
?>
