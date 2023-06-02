<?php

// OpenAI APIの設定
$apiKey = '*************'; // OpenAI APIキーに置き換えてください

// OpenAI APIへのリクエストを送信する関数
function callOpenAI($endpoint, $data) {
    global $apiKey;

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ];

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

// OpenAI APIに対してChatGPTに関するリクエストを送信する関数
function chatGPT($messages) {
    // セッションをクリアするための処理
    if ($messages[count($messages) - 1]['content'] === 'clear-session') {
        session_destroy(); // セッションを破棄
        return 'セッションがクリアされました。新しい会話を開始してください。';
    }

    $endpoint = 'https://api.openai.com/v1/chat/completions';
    $data = [
        'model' => 'gpt-3.5-turbo', // 使用するモデルを指定
        'messages' => $messages,
        'max_tokens' => 500, // 応答の最大トークン数（適宜変更可能）
        'temperature' => 0.7, // 応答の多様性（0.0から1.0の間で設定）
        'n' => 1, // 応答の生成数（適宜変更可能）
    ];

    $response = callOpenAI($endpoint, $data);

    return $response['choices'][0]['message']['content'];
}

// ユーザーからの入力を受け取り、ChatGPTに応答を生成する処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST['message'];

    // 会話履歴をセッションから取得
    session_start();
    $messages = isset($_SESSION['messages']) ? $_SESSION['messages'] : [];

    // ユーザーの入力を会話履歴に追加
    $messages[] = ['role' => 'user', 'content' => $input];

    // ChatGPTに応答を生成
    $response = chatGPT($messages);

    // ChatGPTの応答を会話履歴に追加
    $messages[] = ['role' => 'assistant', 'content' => $response];

    // 会話履歴をセッションに保存
    $_SESSION['messages'] = $messages;

    echo $response;
    exit(); // 応答を送信したらプログラムを終了する
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>ChatGPT Clone</title>
    <script src="https://cdn.jsdelivr.net/npm/marked@3.0.7/marked.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.3/css/bulma.min.css">
    <style>
        .message-container {
            display: flex;
            justify-content: flex-start;
        }

        .message-container.user {
            justify-content: flex-end;
        }

        .message-bubble {
            max-width: 70%;
            margin: 0.5rem;
            padding: 1rem;
            border-radius: 0.5rem;
        }

        .message-bubble.user {
            background-color: lightblue;
            align-self: flex-end;
        }

        #loader {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 9999;
            text-align: center;
        }

        #loader .progress {
            width: 200px;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <section class="section">
        <div class="container">
            <h1 class="title">ChatGPT Clone</h1>
            <div id="chat-container" class="box"></div>
            <div class="field is-grouped">
                <p class="control is-expanded">
                    <input id="user-input" class="input" type="text" placeholder="メッセージを入力してください">
                </p>
                <p class="control">
                    <button class="button is-primary" onclick="sendMessage()">送信</button>
                </p>
                <p class="control">
                    <button class="button is-danger" onclick="clearSession()">クリア</button>
                </p>
            </div>
            <div id="loader">
                <progress class="progress is-small is-primary" max="100"></progress>
            </div>
        </div>
    </section>

    <script>
        const chatContainer = document.getElementById('chat-container');
        const userInput = document.getElementById('user-input');
        const loader = document.getElementById('loader');
        const progressBar = document.querySelector('#loader .progress');

        function displayMessage(text, sender) {
            const messageContainer = document.createElement('div');
            messageContainer.classList.add('message-container', sender);
            const messageBubble = document.createElement('div');
            messageBubble.classList.add('message-bubble', sender);

            // マークダウンをHTMLに変換して表示
            const html = marked(text);
            messageBubble.innerHTML = html;

            messageContainer.appendChild(messageBubble);
            chatContainer.appendChild(messageContainer);
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }

        function showLoader() {
            loader.style.display = 'block';
        }

        function hideLoader() {
            loader.style.display = 'none';
        }

        function startLoader() {
            progressBar.classList.add('is-indeterminate');
        }

        function stopLoader() {
            progressBar.classList.remove('is-indeterminate');
        }

        function sendMessage() {
            const message = userInput.value;
            displayMessage(message, 'user');

            showLoader();
            startLoader();

            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'message=' + encodeURIComponent(message),
            })
                .then(response => response.text())
                .then(text => {
                    displayMessage(text, 'assistant');
                    hideLoader();
                    stopLoader();
                })
                .catch(error => {
                    console.error('An error occurred:', error);
                    hideLoader();
                    stopLoader();
                });

            userInput.value = '';
        }

        function clearSession() {
            showLoader();
            startLoader();

            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'message=clear-session',
            })
                .then(() => {
                    chatContainer.innerHTML = '';
                    hideLoader();
                    stopLoader();
                })
                .catch(error => {
                    console.error('An error occurred:', error);
                    hideLoader();
                    stopLoader();
                });
        }
    </script>
</body>
</html>

