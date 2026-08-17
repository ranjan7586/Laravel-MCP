import './bootstrap';
import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { StreamableHTTPClientTransport } from '@modelcontextprotocol/sdk/client/streamableHttp.js';

const elements = {
    connect: document.querySelector('#connect'),
    endpoint: document.querySelector('#endpoint'),
    form: document.querySelector('#weather-form'),
    location: document.querySelector('#location'),
    units: document.querySelector('#units'),
    run: document.querySelector('#run'),
    status: document.querySelector('#connection-status'),
    tools: document.querySelector('#tools'),
    card: document.querySelector('#weather-card'),
    response: document.querySelector('#response'),
    copy: document.querySelector('#copy'),
};

let client;

function setStatus(state, text) {
    elements.status.className = `status status--${state}`;
    elements.status.innerHTML = `<span></span>${text}`;
}

function errorMessage(error) {
    return error instanceof Error ? error.message : String(error);
}

async function connect() {
    elements.connect.disabled = true;
    elements.run.disabled = true;
    setStatus('pending', 'Connecting…');

    try {
        if (client) await client.close();
        client = new Client({ name: 'laravel-weather-tester', version: '1.0.0' });
        const transport = new StreamableHTTPClientTransport(new URL(elements.endpoint.value));
        await client.connect(transport);

        const result = await client.listTools();
        elements.tools.innerHTML = result.tools.map((tool) => `
            <article class="tool-item">
                <code>${tool.name}</code>
                <p>${tool.description ?? 'No description'}</p>
            </article>
        `).join('') || '<p class="muted">No tools were advertised.</p>';

        setStatus('online', 'Connected');
        elements.run.disabled = !result.tools.some((tool) => tool.name === 'get-weather');
    } catch (error) {
        setStatus('offline', 'Connection failed');
        elements.tools.innerHTML = `<p class="error">${errorMessage(error)}</p>`;
    } finally {
        elements.connect.disabled = false;
    }
}

function renderWeather(data) {
    elements.card.classList.remove('empty');
    elements.card.innerHTML = `
        <div class="weather-icon">${data.condition.includes('Rain') ? '☂' : data.condition.includes('Snow') ? '❄' : '☼'}</div>
        <div class="weather-main">
            <p>${data.location}</p>
            <strong>${data.temperature}<small>${data.temperature_unit}</small></strong>
            <span>${data.condition} · Feels like ${data.apparent_temperature}${data.temperature_unit}</span>
        </div>
        <dl>
            <div><dt>Humidity</dt><dd>${data.humidity_percent}%</dd></div>
            <div><dt>Wind</dt><dd>${data.wind_speed} ${data.wind_speed_unit}</dd></div>
            <div><dt>Observed</dt><dd>${data.observed_at.replace('T', ' ')}</dd></div>
        </dl>
    `;
}

elements.connect?.addEventListener('click', connect);

elements.form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!client) return;

    elements.run.disabled = true;
    elements.run.textContent = 'Running…';

    try {
        const result = await client.callTool({
            name: 'get-weather',
            arguments: { location: elements.location.value, units: elements.units.value },
        });
        elements.response.textContent = JSON.stringify(result, null, 2);

        if (result.isError) {
            elements.card.className = 'weather-card empty error';
            elements.card.innerHTML = `<div class="weather-icon">!</div><p>${result.content?.[0]?.text ?? 'Tool call failed.'}</p>`;
        } else if (result.structuredContent) {
            renderWeather(result.structuredContent);
        }
    } catch (error) {
        elements.response.textContent = JSON.stringify({ error: errorMessage(error) }, null, 2);
    } finally {
        elements.run.disabled = false;
        elements.run.innerHTML = 'Run tool <span>↗</span>';
    }
});

elements.copy?.addEventListener('click', async () => {
    await navigator.clipboard.writeText(elements.response.textContent);
    elements.copy.textContent = 'Copied';
    setTimeout(() => { elements.copy.textContent = 'Copy JSON'; }, 1200);
});

connect();
