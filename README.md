<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

# Weather MCP Server (Laravel)

A small learning/testing project for exploring the [Model Context Protocol](https://modelcontextprotocol.io) (MCP) using the [`laravel/mcp`](https://github.com/laravel/mcp) package. It exposes a **Weather MCP server** with a single `get-weather` tool, plus a browser-based console for connecting to the server and calling the tool by hand.

This repo exists purely to learn how Laravel MCP servers, tools, and the Streamable HTTP transport work — it is not a production service.

## What's in here

- **`WeatherServer`** ([app/Mcp/Servers/WeatherServer.php](app/Mcp/Servers/WeatherServer.php)) — an MCP server registered at `/mcp/weather` that exposes the `get-weather` tool and instructs the LLM on when to use it.
- **`GetWeather`** ([app/Mcp/Tools/GetWeather.php](app/Mcp/Tools/GetWeather.php)) — a read-only, open-world MCP tool that:
  - Accepts a `location` (required) and `units` (`metric` or `imperial`, defaults to metric).
  - Geocodes the location via the [Open-Meteo Geocoding API](https://open-meteo.com/).
  - Fetches live current conditions from the [Open-Meteo Forecast API](https://open-meteo.com/).
  - Returns structured content (temperature, apparent temperature, humidity, wind, condition, timezone, etc.) or a friendly error if the location can't be found or the provider is unreachable.
- **`CrmServer`** ([app/Mcp/Servers/CrmServer.php](app/Mcp/Servers/CrmServer.php)) — an empty scaffold server (registered at `/mcp/crm`) kept around for experimenting with a second server/tool set.
- **Weather MCP Console** ([resources/views/mcp-tester.blade.php](resources/views/mcp-tester.blade.php)) — a plain HTML/JS page served at `/` and `/mcp-tester` for connecting to the local Streamable HTTP MCP endpoint, discovering its tools, and calling `get-weather` interactively.
- **MCP routes** ([routes/ai.php](routes/ai.php)) — registers the two servers with `Mcp::web(...)`.

## Requirements

- PHP ^8.1
- Composer
- Node.js + npm (for the console's Vite assets)

## Setup

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

npm run dev      # or: npm run build
php artisan serve
```

No database is required to use the weather tool itself, but the default Laravel `.env` still points at one if you want to run migrations.

## Trying it out

1. Start the app with `php artisan serve` and run `npm run dev` (or build assets) so the console's JS/CSS are available.
2. Open `http://127.0.0.1:8000/` (or `/mcp-tester`) in a browser.
3. Point **MCP endpoint** at `http://127.0.0.1:8000/mcp/weather` and click **Connect & discover** to list the server's tools.
4. Call `get-weather` with a `location` (e.g. `Bengaluru`) and an optional `units` value to see a live structured response.

You can also talk to the server directly as an MCP client (e.g. Claude Desktop, Claude Code, or another MCP-compatible client) by pointing it at the `/mcp/weather` Streamable HTTP endpoint.

## Tests

```bash
php artisan test
```

[tests/Feature/GetWeatherToolTest.php](tests/Feature/GetWeatherToolTest.php) fakes the Open-Meteo HTTP calls and asserts the tool returns correctly structured weather data.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
