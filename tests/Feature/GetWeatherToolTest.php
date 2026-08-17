<?php

namespace Tests\Feature;

use App\Mcp\Tools\GetWeather;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;
use Tests\TestCase;

class GetWeatherToolTest extends TestCase
{
    public function test_it_returns_structured_current_weather(): void
    {
        Http::fake([
            'geocoding-api.open-meteo.com/*' => Http::response([
                'results' => [[
                    'name' => 'Bengaluru',
                    'admin1' => 'Karnataka',
                    'country' => 'India',
                    'latitude' => 12.97,
                    'longitude' => 77.59,
                ]],
            ]),
            'api.open-meteo.com/*' => Http::response([
                'timezone' => 'Asia/Kolkata',
                'current_units' => ['temperature_2m' => '°C', 'wind_speed_10m' => 'km/h'],
                'current' => [
                    'time' => '2026-08-17T12:00',
                    'temperature_2m' => 25.4,
                    'relative_humidity_2m' => 71,
                    'apparent_temperature' => 27.1,
                    'weather_code' => 2,
                    'wind_speed_10m' => 13.2,
                ],
            ]),
        ]);

        $result = app(GetWeather::class)->handle(new Request([
            'location' => 'Bengaluru',
            'units' => 'metric',
        ]));

        $this->assertSame('Bengaluru, Karnataka, India', $result->getStructuredContent()['location']);
        $this->assertSame('Partly cloudy', $result->getStructuredContent()['condition']);
        $this->assertSame(25.4, $result->getStructuredContent()['temperature']);
    }
}
