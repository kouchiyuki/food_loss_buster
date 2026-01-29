<?php
session_start();
require_once 'db_config.php';

// OpenAI APIキー（.envや環境変数から安全に取得）
$OPENAI_API_KEY = getenv('OPENAI_API_KEY');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['selected_foods'])) {
    header('Location: look_inside_refrigerato.php');
    exit;
}

$selected_foods = array_filter($_POST['selected_foods'], fn($v) => !empty(trim($v)));
if (empty($selected_foods)) {
    $_SESSION['message'] = '食材を1つ以上入力してください。';
    header('Location: look_inside_refrigerato.php');
    exit;
}

$food_list = implode('、', $selected_foods);

// AIへのプロンプト
$prompt = "私は家庭の料理アシスタントです。次の食材を使って簡単に作れるレシピを3つ提案してください。食材はこれです: {$food_list}。分量や作り方も簡単に書いてください。";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.openai.com/v1/chat/completions");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer {$OPENAI_API_KEY}"
]);

$data = [
    "model" => "gpt-4.1-mini",
    "messages" => [
        ["role" => "system", "content" => "あなたは親切で家庭向けの料理アシスタントです。"],
        ["role" => "user", "content" => $prompt]
    ],
    "max_tokens" => 700,
    "temperature" => 0.7
];

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error_msg = curl_error($ch);
curl_close($ch);

// レシピ取得結果の判定
if ($error_msg) {
    $ai_result = "AIに接続できませんでした: {$error_msg}";
} elseif ($http_code == 429) {
    $ai_result = "現在、APIの利用制限中です。しばらく待ってからもう一度試してください。";
} elseif ($http_code >= 400) {
    $ai_result = "AIに接続できませんでした。HTTPコード: {$http_code}";
} else {
    $res_json = json_decode($response, true);
    if (isset($res_json['choices'][0]['message']['content'])) {
        $ai_result = $res_json['choices'][0]['message']['content'];
    } else {
        $ai_result = "レシピを取得できませんでした。";
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AIレシピ提案 - Food Loss Buster</title>
<link href="https://fonts.googleapis.com/css2?family=Kiwi+Maru:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { font-family: 'Kiwi Maru', serif; background-color: #fff9e6; padding: 20px; }
.main-board { max-width: 700px; margin: 0 auto; background: white; border-radius: 30px; padding: 20px; border: 6px solid #a0c4ff; }
.header-banner { background-color: #a0c4ff; color: white; padding: 20px; text-align: center; font-size: 1.5rem; font-weight: bold; text-shadow: 1px 1px 0 rgba(0,0,0,0.1); border-radius: 20px; margin-bottom: 20px; }
pre { background-color: #f0f7ff; padding: 15px; border-radius: 10px; white-space: pre-wrap; word-wrap: break-word; }
.btn-back { margin-top: 20px; display: inline-block; }
.alert { margin-top: 15px; }
</style>
</head>
<body>
<div class="main-board">
    <div class="header-banner">🤖 AIレシピ提案</div>

    <p>選んだ食材: <strong><?= htmlspecialchars(implode('、', $selected_foods)) ?></strong></p>

    <?php if ($http_code == 429 || $http_code >= 400 || $error_msg): ?>
        <div class="alert alert-warning"><?= htmlspecialchars($ai_result) ?></div>
    <?php else: ?>
        <pre><?= htmlspecialchars($ai_result) ?></pre>
    <?php endif; ?>

    <a href="top_refrigerator.php" class="btn btn-primary btn-back">トップにもどる</a>
</div>
</body>
</html>
