<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Weather MCP Console</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <main class="shell">
        <header class="hero">
            <div>
                <p class="eyebrow">Laravel 10 · Model Context Protocol</p>
                <h1>Weather MCP Console</h1>
                <p class="subtitle">Connect to the local Streamable HTTP server, discover its tools, and make a real MCP tool call.</p>
            </div>
            <div id="connection-status" class="status status--offline"><span></span>Disconnected</div>
        </header>

        <section class="workspace">
            <aside class="panel sidebar">
                <div class="panel-heading">
                    <div><span class="step">01</span><h2>Connection</h2></div>
                </div>
                <label for="endpoint">MCP endpoint</label>
                <input id="endpoint" value="{{ url('/mcp/weather') }}" spellcheck="false">
                <button id="connect" class="button button--secondary" type="button">Connect & discover</button>

                <div class="divider"></div>
                <div class="panel-heading compact">
                    <div><span class="step">02</span><h2>Discovered tools</h2></div>
                </div>
                <div id="tools" class="tool-list"><p class="muted">Connect to inspect the server.</p></div>
            </aside>

            <section class="panel runner">
                <div class="panel-heading">
                    <div><span class="step">03</span><h2>Call get-weather</h2></div>
                    <span class="method">tools/call</span>
                </div>

                <form id="weather-form">
                    <div class="form-grid">
                        <div class="field field--wide">
                            <label for="location">Location</label>
                            <input id="location" name="location" value="Bengaluru, India" maxlength="120" required>
                        </div>
                        <div class="field">
                            <label for="units">Units</label>
                            <select id="units" name="units">
                                <option value="metric">Metric · °C</option>
                                <option value="imperial">Imperial · °F</option>
                            </select>
                        </div>
                        <button id="run" class="button button--primary" type="submit" disabled>Run tool <span>↗</span></button>
                    </div>
                </form>

                <div id="weather-card" class="weather-card empty">
                    <div class="weather-icon">☼</div>
                    <div><p class="muted">The current conditions will appear here.</p></div>
                </div>

                <div class="response-heading">
                    <h3>Raw MCP response</h3>
                    <button id="copy" class="text-button" type="button">Copy JSON</button>
                </div>
                <pre id="response">Waiting for a tool call…</pre>
            </section>
        </section>

        <footer><span>Server: Laravel MCP</span><span>Client: @modelcontextprotocol/sdk</span></footer>
    </main>
</body>
</html>
