<?php
set_time_limit(0);
ignore_user_abort(true);

$promptDir = __DIR__ . "/prompts";

/* ---------------- PROMPT LOADER ---------------- */
if (isset($_GET["load_prompt"])) {

    header("Content-Type: text/plain; charset=utf-8");

    $file = basename($_GET["load_prompt"]);
    $path = realpath($promptDir . "/" . $file);
    $base = realpath($promptDir);

    if (!$path || strpos($path, $base) !== 0 || !file_exists($path)) {
        http_response_code(404);
        echo "Prompt not found";
        exit;
    }

    echo file_get_contents($path);
    exit;
}

/* ---------------- CHAT API ---------------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    header("Content-Type: application/json; charset=utf-8");

    $input = json_decode(file_get_contents("php://input"), true);

    $system = $input["system"] ?? "";
    $user   = $input["user"] ?? "";

    $payload = [
        "messages" => [
            ["role" => "system", "content" => $system],
            ["role" => "user", "content" => $user]
        ],
        "temperature" => 0.7,
        "stream" => false
    ];

    $ch = curl_init("http://localhost:1234/v1/chat/completions");

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 0
    ]);

    $result = curl_exec($ch);

    if ($result === false) {
        echo json_encode(["error" => curl_error($ch)]);
        exit;
    }

    curl_close($ch);

    echo $result;
    exit;
}

/* ---------------- LOAD PROMPTS ---------------- */
$promptFiles = [];
if (is_dir($promptDir)) {
    foreach (scandir($promptDir) as $file) {
        if ($file !== "." && $file !== "..") {
            $promptFiles[pathinfo($file, PATHINFO_FILENAME)] = $file;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Local LLaMA Chat</title>

<link id="favicon" rel="icon" href="images/green.png">

<style>
body {
    margin:0;
    font-family: Arial;
    background:#0f172a;
    color:#e2e8f0;
}

.container {
    display:flex;
    height:100vh;
}

/* LEFT */
.left {
    width:40%;
    background:#111827;
    padding:20px;
    border-right:1px solid #1f2937;
}

textarea, select {
    width:100%;
    padding:10px;
    margin-bottom:10px;
    border-radius:8px;
    background:#1f2937;
    color:white;
    border:none;
}

textarea { height:120px; }

button {
    width:100%;
    padding:12px;
    background:#3b82f6;
    border:none;
    color:white;
    font-weight:bold;
    border-radius:8px;
    cursor:pointer;
}

/* RIGHT */
.right {
    width:60%;
    padding:20px;
    position:relative;
}

.response {
    background:#1e293b;
    padding:20px;
    border-radius:10px;
    min-height:200px;
    white-space:normal;
}

/* LOADER */
.loader {
    display:none;
    position:absolute;
    top:0;left:0;
    width:100%;
    height:100%;
    background:rgba(0,0,0,0.6);
    justify-content:center;
    align-items:center;
    flex-direction:column;
}

.spinner {
    width:50px;
    height:50px;
    border:5px solid #334155;
    border-top:5px solid #3b82f6;
    border-radius:50%;
    animation:spin 1s linear infinite;
}

@keyframes spin {
    100% { transform:rotate(360deg); }
}
</style>

<!-- LOCAL JS -->
<script src="js/marked.min.js"></script>
<script src="js/highlight.min.js"></script>
<script src="js/purify.min.js"></script>

</head>

<body>

<div class="container">

<!-- LEFT -->
<div class="left">

<form id="chatForm">

<label>System Prompt</label>

<select id="promptSelect">
    <option value="">-- select prompt --</option>
    <?php foreach ($promptFiles as $name => $file): ?>
        <option value="<?= htmlspecialchars($file) ?>">
            <?= htmlspecialchars($name) ?>
        </option>
    <?php endforeach; ?>
</select>

<textarea id="systemBox"></textarea>

<label>User Prompt</label>
<textarea id="userBox"></textarea>

<button type="submit">Send</button>

</form>

</div>

<!-- RIGHT -->
<div class="right">

<h2>Response</h2>

<div class="response" id="responseBox">No response yet...</div>

<div class="loader" id="loader">
    <div class="spinner"></div>
    <div style="margin-top:10px;">Thinking...</div>
</div>

</div>

</div>

<script>
const favicon = document.getElementById("favicon");
const BASE = window.location.pathname;

/* ---------------- PROMPT LOAD ---------------- */
document.getElementById("promptSelect").addEventListener("change", async (e) => {

    const file = e.target.value;
    if (!file) return;

    const url = BASE + "?load_prompt=" + encodeURIComponent(file);

    try {
        const res = await fetch(url);
        const text = await res.text();
        document.getElementById("systemBox").value = text;
    } catch (err) {
        console.error(err);
        alert("Failed to load prompt");
    }
});

/* ---------------- CHAT ---------------- */
document.getElementById("chatForm").addEventListener("submit", async (e) => {
    e.preventDefault();

    setState(true);

    const payload = {
        system: document.getElementById("systemBox").value,
        user: document.getElementById("userBox").value
    };

    try {
        const res = await fetch(BASE, {
            method: "POST",
            headers: {"Content-Type":"application/json"},
            body: JSON.stringify(payload)
        });

        const text = await res.text();
        const data = JSON.parse(text);

        const output =
            data?.choices?.[0]?.message?.content ||
            data?.error ||
            "No response";

        render(output);

    } catch (err) {
        console.error(err);
        alert("Request failed");
    }

    setState(false);
});

/* ---------------- RENDER ---------------- */
function render(text) {
    let html = marked.parse(text);
    html = DOMPurify.sanitize(html);

    document.getElementById("responseBox").innerHTML = html;

    document.querySelectorAll("pre code").forEach(el => {
        hljs.highlightElement(el);
    });
}

/* ---------------- UI STATE ---------------- */
function setState(loading) {
    document.getElementById("loader").style.display = loading ? "flex" : "none";
    favicon.href = loading ? "images/wait.png" : "images/green.png";
}
</script>

</body>
</html>