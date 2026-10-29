<?php
// 1. DATABASE CONNECTION
$servername = "172.16.50.33";
$username   = "web";
$password   = "web";
$dbname     = "web";

$conn = new mysqli(
    $servername,
    $username,
    $password,
    $dbname
);

if ($conn->connect_error) {
    http_response_code(500);
    exit("Database connection failed.");
}

$conn->set_charset("utf8mb4");

// 2. CREATE CHAT TABLE
$table_sql = "CREATE TABLE IF NOT EXISTS chat_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender VARCHAR(30) NOT NULL,
    message TEXT NOT NULL,
    reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (!$conn->query($table_sql)) {
    http_response_code(500);
    exit("Could not create chat table.");
}

// 3. SEND MESSAGE THROUGH AJAX
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    header("Content-Type: application/json");

    $sender = trim($_POST["sender"] ?? "");
    $message = trim($_POST["message"] ?? "");

    if ($sender === "" || $message === "") {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Please enter your name and message."
        ]);
        exit;
    }

    if (
        strlen($sender) > 120 ||
        strlen($message) > 8000
    ) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Your input is too long."
        ]);
        exit;
    }

    $sql = "INSERT INTO chat_messages (sender, message)
            VALUES (?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $sender, $message);

    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Message could not be saved."
        ]);
    }

    $stmt->close();
    $conn->close();
    exit;
}

// 4. FETCH MESSAGES THROUGH AJAX
if (isset($_GET["fetch"])) {
    header("Content-Type: application/json; charset=UTF-8");

    $sql = "SELECT sender, message, reg_date
            FROM chat_messages
            ORDER BY id DESC
            LIMIT 200";

    $result = $conn->query($sql);
    $messages = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
    }

    // Display messages from oldest to newest
    echo json_encode(
        array_reverse($messages),
        JSON_UNESCAPED_UNICODE
    );

    $conn->close();
    exit;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Simple Chat Application</title>

    <style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

:root {
    --page-bg: #a7a1a4;
    --panel-bg: #111113;
    --chat-bg: #171719;
    --incoming-bg: #29292e;
    --text: #f5f5f7;
    --muted: #a1a1aa;
    --purple: #8b3dff;
    --pink: #d946ef;
    --border: #34343a;
}

body {
    min-height: 100vh;
    padding: 24px;
    display: flex;
    justify-content: center;
    align-items: center;
    font-family: "Segoe UI", Arial, sans-serif;
    color: var(--text);

    /* Grey background outside the chat */
    background: var(--page-bg);
}

/* Main chat rectangle */
.container {
    width: 100%;
    max-width: 520px;
    padding: 0;
    overflow: hidden;
    background: var(--panel-bg);
    border: 1px solid #3a3a40;
    border-radius: 20px;
    box-shadow: 0 20px 55px rgba(0, 0, 0, 0.28);
}

/* Chat heading */
h2 {
    padding: 24px 16px 7px;
    text-align: center;
    color: #ffffff;
    font-size: 25px;
    font-weight: 700;
}

.subtitle {
    padding: 0 12px 20px;
    text-align: center;
    color: var(--muted);
    font-size: 13px;
}

/* Name field */
#sender {
    display: block;
    width: calc(100% - 32px);
    margin: 0 16px 15px;
}

input {
    min-width: 0;
    padding: 13px 14px;
    color: #ffffff;
    background: #202024;
    border: 1px solid #3a3a42;
    border-radius: 11px;
    outline: none;
    font-family: inherit;
    font-size: 14px;
    transition: border-color 0.2s, box-shadow 0.2s;
}

input::placeholder {
    color: #92929d;
}

input:focus {
    border-color: #a855f7;
    box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.15);
}

/* Chat messages area */
#chatbox {
    height: 370px;
    overflow-y: auto;
    padding: 17px 14px;
    margin: 0 12px 16px;
    background: var(--chat-bg);
    border: 1px solid #242428;
    border-radius: 14px;
    scroll-behavior: smooth;
}

#chatbox::-webkit-scrollbar {
    width: 5px;
}

#chatbox::-webkit-scrollbar-track {
    background: transparent;
}

#chatbox::-webkit-scrollbar-thumb {
    background: #55515b;
    border-radius: 10px;
}

/* Incoming message bubble */
.chat-message {
    width: fit-content;
    max-width: 85%;
    margin: 0 0 14px;
    padding: 10px 13px;
    background: var(--incoming-bg);
    border: 1px solid #37373e;
    border-radius: 5px 14px 14px 14px;
    overflow-wrap: anywhere;
    animation: bubbleIn 0.2s ease;
}

.chat-message strong {
    display: block;
    margin-bottom: 5px;
    color: #d8b4fe;
    font-size: 12px;
    font-weight: 700;
}

.chat-message p {
    margin: 0 0 7px;
    color: #f4f4f5;
    font-size: 14px;
    line-height: 1.55;
    white-space: pre-wrap;
}

.chat-message small {
    display: block;
    color: #a1a1aa;
    font-size: 10px;
    text-align: right;
}

/* Outgoing message bubble */
.chat-message.own-message {
    margin-left: auto;
    background: linear-gradient(135deg, #8b3dff, #d946ef);
    border: none;
    border-radius: 14px 5px 14px 14px;
    box-shadow: 0 4px 14px rgba(139, 61, 255, 0.14);
}

.chat-message.own-message strong {
    color: #ffffff;
}

.chat-message.own-message p {
    color: #ffffff;
}

.chat-message.own-message small {
    color: #f3d8ff;
}

/* Message input row */
.message-row {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 0 12px;
}

.message-row input {
    flex: 1;
    width: 0;
}

button {
    flex-shrink: 0;
    padding: 13px 19px;
    color: #ffffff;
    background: linear-gradient(135deg, #8b3dff, #c026d3);
    border: none;
    border-radius: 11px;
    font-family: inherit;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: transform 0.2s, filter 0.2s;
}

button:hover {
    filter: brightness(1.15);
    transform: translateY(-1px);
}

button:active {
    transform: scale(0.97);
}

button:disabled {
    opacity: 0.55;
    cursor: not-allowed;
    transform: none;
}

/* Connection status */
#status {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: 20px;
    padding: 14px;
    color: #a1a1aa;
    background: #151517;
    border-top: 1px solid #29292e;
    font-size: 12px;
}

#status::before {
    content: "";
    width: 7px;
    height: 7px;
    flex-shrink: 0;
    background: #4ade80;
    border-radius: 50%;
    box-shadow: 0 0 8px rgba(74, 222, 128, 0.4);
}

@keyframes bubbleIn {
    from {
        opacity: 0;
        transform: translateY(4px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Mobile responsive design */
@media (max-width: 480px) {
    body {
        padding: 10px;
    }

    .container {
        max-width: 100%;
        border-radius: 17px;
    }

    h2 {
        font-size: 23px;
        padding-top: 20px;
    }

    #chatbox {
        height: 48vh;
        min-height: 260px;
        max-height: 430px;
        margin-left: 9px;
        margin-right: 9px;
        padding: 12px;
    }

    #sender {
        width: calc(100% - 20px);
        margin-left: 10px;
        margin-right: 10px;
    }

    .message-row {
        gap: 7px;
        padding: 0 9px;
    }

    .message-row input {
        padding: 12px 10px;
    }

    .message-row button {
        padding: 12px 14px;
    }

    .chat-message {
        max-width: 92%;
    }
}

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation: none !important;
        transition: none !important;
        scroll-behavior: auto !important;
    }
}

    </style>
</head>

<body>

<div class="container">

    <h2>💬 Simple Chat</h2>
    <p class="subtitle">Chat with your friends</p>

    <input
        type="text"
        id="sender"
        placeholder="Enter your name"
        maxlength="30"
        autocomplete="nickname"
        required
    >

    <div id="chatbox">Loading messages...</div>

    <form id="chat-form" class="message-row">
        <input
            type="text"
            id="message"
            placeholder="Type a message..."
            maxlength="2000"
            autocomplete="off"
            required
        >

        <button type="submit" id="send-button">
            Send
        </button>
    </form>

    <div id="status">Connecting to chat...</div>

</div>

<script>
    const chatbox = document.getElementById("chatbox");
    const sender = document.getElementById("sender");
    const message = document.getElementById("message");
    const form = document.getElementById("chat-form");
    const statusText = document.getElementById("status");
    const sendButton = document.getElementById("send-button");

    // Load messages from the database
    async function loadMessages() {
        try {
            const response = await fetch("chat.php?fetch=1", {
                cache: "no-store"
            });

            if (!response.ok) {
                throw new Error("Could not load messages");
            }

            const data = await response.json();

            // Remember whether the user is at the bottom
            const atBottom =
                chatbox.scrollHeight -
                chatbox.scrollTop -
                chatbox.clientHeight < 50;

            // Create messages without inserting raw HTML
            const fragment = document.createDocumentFragment();

            data.forEach(function (chat) {
                const item = document.createElement("div");
                item.className =
                    "chat-message" +
                    (
                        sender.value.trim() !== "" &&
                        chat.sender === sender.value.trim()
                            ? " own-message"
                            : ""
                    );

                const name = document.createElement("strong");
                name.textContent = chat.sender;

                const text = document.createElement("p");
                text.textContent = chat.message;

                const time = document.createElement("small");
                time.textContent = chat.reg_date;

                item.append(name, text, time);
                fragment.appendChild(item);
            });

            chatbox.replaceChildren(fragment);

            if (atBottom) {
                chatbox.scrollTop = chatbox.scrollHeight;
            }

            statusText.textContent = "Connected to chat";

        } catch (error) {
            statusText.textContent =
                "Unable to connect. Checking again...";
        }
    }

    // Send a message without reloading the page
    form.addEventListener("submit", async function (event) {
        event.preventDefault();

        const name = sender.value.trim();
        const text = message.value.trim();

        if (!name || !text) {
            alert("Please enter your name and message.");
            return;
        }

        sendButton.disabled = true;

        try {
            const response = await fetch("chat.php", {
                method: "POST",
                headers: {
                    "Content-Type":
                        "application/x-www-form-urlencoded"
                },
                body: new URLSearchParams({
                    sender: name,
                    message: text
                })
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(
                    result.error || "Message could not be sent."
                );
            }

            message.value = "";
            await loadMessages();
            message.focus();

        } catch (error) {
            alert(error.message);
        } finally {
            sendButton.disabled = false;
        }
    });

    // Load messages when the page opens
    loadMessages();

    // Check for new messages every 2 seconds
    setInterval(loadMessages, 2000);
</script>

</body>
</html>
