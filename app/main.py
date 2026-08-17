from contextlib import asynccontextmanager

from fastapi import FastAPI
from fastapi.responses import HTMLResponse
from pydantic import BaseModel

from app.chatcontroller import ChatController

controller = ChatController()


@asynccontextmanager
async def lifespan(app: FastAPI):
    await controller.setup()  # connects to the Laravel MCP weather server, compiles the graph
    yield


app = FastAPI(lifespan=lifespan)


class ChatRequest(BaseModel):
    session_id: str
    message: str


class ChatResponse(BaseModel):
    reply: str


@app.post("/chat", response_model=ChatResponse)
async def chat_with_ai(request: ChatRequest) -> ChatResponse:
    reply = await controller.chat(request.session_id, request.message)
    return ChatResponse(reply=reply)


@app.get("/", response_class=HTMLResponse)
def chat_ui() -> str:
    return CHAT_UI_HTML


CHAT_UI_HTML = """\
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Weather Chat</title>
<style>
  body { font-family: system-ui, sans-serif; max-width: 640px; margin: 40px auto; background: #f5f5f7; }
  #log { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 16px; height: 60vh; overflow-y: auto; }
  .msg { margin: 8px 0; padding: 8px 12px; border-radius: 8px; max-width: 80%; white-space: pre-wrap; }
  .user { background: #0b84ff; color: #fff; margin-left: auto; }
  .bot { background: #e5e5ea; color: #000; }
  .row { display: flex; }
  form { display: flex; gap: 8px; margin-top: 12px; }
  input { flex: 1; padding: 10px; border-radius: 8px; border: 1px solid #ccc; }
  button { padding: 10px 16px; border-radius: 8px; border: none; background: #0b84ff; color: #fff; cursor: pointer; }
  button:disabled { opacity: 0.5; cursor: default; }
</style>
</head>
<body>
  <h2>Weather Chat</h2>
  <div id="log"></div>
  <form id="form">
    <input id="input" type="text" placeholder="Say hi, or ask about the weather..." autocomplete="off" autofocus />
    <button type="submit">Send</button>
  </form>

<script>
  const sessionId = localStorage.getItem("session_id") || crypto.randomUUID();
  localStorage.setItem("session_id", sessionId);

  const log = document.getElementById("log");
  const form = document.getElementById("form");
  const input = document.getElementById("input");

  function addMessage(text, who) {
    const row = document.createElement("div");
    row.className = "row";
    const bubble = document.createElement("div");
    bubble.className = "msg " + who;
    bubble.textContent = text;
    row.style.justifyContent = who === "user" ? "flex-end" : "flex-start";
    row.appendChild(bubble);
    log.appendChild(row);
    log.scrollTop = log.scrollHeight;
  }

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const text = input.value.trim();
    if (!text) return;
    addMessage(text, "user");
    input.value = "";
    input.disabled = true;

    try {
      const res = await fetch("/chat", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ session_id: sessionId, message: text }),
      });
      const data = await res.json();
      addMessage(res.ok ? data.reply : "Error: " + JSON.stringify(data), "bot");
    } catch (err) {
      addMessage("Error: " + err, "bot");
    } finally {
      input.disabled = false;
      input.focus();
    }
  });
</script>
</body>
</html>
"""
