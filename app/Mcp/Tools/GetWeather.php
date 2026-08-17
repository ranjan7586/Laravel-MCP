<?php

namespace App\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\ResponseFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[IsReadOnly]
#[IsOpenWorld]
class GetWeather extends Tool
{
    protected string $description = 'Get the live current weather for a city or place name.';

    public function handle(Request $request): ResponseFactory|Response
    {
        $input = $request->validate([
            'location' => ['required', 'string', 'max:120'],
            'units' => ['sometimes', 'string', 'in:metric,imperial'],
        ]);

        $units = $input['units'] ?? 'metric';

        try {
            $placeResponse = Http::acceptJson()->timeout(8)->get(
                'https://geocoding-api.open-meteo.com/v1/search',
                [
                    'name' => $input['location'],
                    'count' => 1,
                    'language' => 'en',
                    'format' => 'json',
                ],
            );

            if ($placeResponse->failed()) {
                return Response::error('The weather provider could not find that location right now.');
            }

            $place = $placeResponse->json('results.0');

            if (! is_array($place)) {
                return Response::error("No location was found for '{$input['location']}'. Try including a state or country.");
            }

            $weatherResponse = Http::acceptJson()->timeout(8)->get(
                'https://api.open-meteo.com/v1/forecast',
                [
                    'latitude' => $place['latitude'],
                    'longitude' => $place['longitude'],
                    'current' => 'temperature_2m,relative_humidity_2m,apparent_temperature,weather_code,wind_speed_10m',
                    'temperature_unit' => $units === 'imperial' ? 'fahrenheit' : 'celsius',
                    'wind_speed_unit' => $units === 'imperial' ? 'mph' : 'kmh',
                    'timezone' => 'auto',
                ],
            );

            if ($weatherResponse->failed() || ! is_array($weatherResponse->json('current'))) {
                return Response::error('The current weather is temporarily unavailable.');
            }
        } catch (ConnectionException) {
            return Response::error('Could not connect to the weather provider. Please try again.');
        }

        $current = $weatherResponse->json('current');
        $country = $place['country'] ?? null;
        $admin = $place['admin1'] ?? null;
        $displayName = implode(', ', array_filter([$place['name'], $admin, $country]));

        return Response::structured([
            'location' => $displayName,
            'temperature' => (float) $current['temperature_2m'],
            'temperature_unit' => (string) $weatherResponse->json('current_units.temperature_2m'),
            'apparent_temperature' => (float) $current['apparent_temperature'],
            'humidity_percent' => (int) $current['relative_humidity_2m'],
            'condition' => $this->condition((int) $current['weather_code']),
            'wind_speed' => (float) $current['wind_speed_10m'],
            'wind_speed_unit' => (string) $weatherResponse->json('current_units.wind_speed_10m'),
            'observed_at' => (string) $current['time'],
            'timezone' => (string) $weatherResponse->json('timezone'),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'location' => $schema->string()
                ->description('City or place name, optionally including state and country.')
                ->required(),
            'units' => $schema->string()
                ->enum(['metric', 'imperial'])
                ->description('Measurement system. Defaults to metric.'),
        ];
    }

    private function condition(int $code): string
    {
        return match (true) {
            $code === 0 => 'Clear sky',
            in_array($code, [1, 2, 3], true) => 'Partly cloudy',
            in_array($code, [45, 48], true) => 'Foggy',
            in_array($code, [51, 53, 55, 56, 57], true) => 'Drizzle',
            in_array($code, [61, 63, 65, 66, 67, 80, 81, 82], true) => 'Rain',
            in_array($code, [71, 73, 75, 77, 85, 86], true) => 'Snow',
            in_array($code, [95, 96, 99], true) => 'Thunderstorm',
            default => 'Unknown',
        };
    }
}
