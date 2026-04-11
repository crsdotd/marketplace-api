<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class LocationService
{
    protected string $nominatimUrl = 'https://nominatim.openstreetmap.org';
    protected string $userAgent;

    public function __construct()
    {
        // Nominatim requires User-Agent header
        $this->userAgent = 'MarketplaceAPI/1.0 (Location Search) - ' . config('app.url');
    }

    /**
     * Search locations using Nominatim API (OpenStreetMap)
     * FREE API - No API key required!
     * 
     * @param string $query Search query (e.g., "Jakarta", "Bandung", etc.)
     * @param array $options Optional parameters
     * @return array Array of location suggestions
     */
    public function searchLocations(string $query, array $options = []): array
    {
        try {
            // Limit search ke Indonesia
            $countryCode = $options['country'] ?? 'ID';
            
            $params = [
                'q'           => $query,
                'format'      => 'json',
                'language'    => $options['language'] ?? 'id',
                'limit'       => 10,
                'countrycodes'=> strtolower($countryCode), // Nominatim uses lowercase
                'addressdetails' => 1,
            ];

            $response = Http::withHeaders([
                'User-Agent' => $this->userAgent,
            ])->withoutVerifying()->get("{$this->nominatimUrl}/search", $params);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => 'Failed to fetch from Nominatim API',
                    'locations' => []
                ];
            }

            $results = $response->json();

            if (empty($results)) {
                return [
                    'success' => false,
                    'message' => 'No locations found',
                    'locations' => [],
                ];
            }

            $locations = $this->formatNominatimResults($results);

            return [
                'success' => true,
                'message' => 'Locations found',
                'locations' => $locations,
                'count' => count($locations)
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error searching locations: ' . $e->getMessage(),
                'locations' => []
            ];
        }
    }

    /**
     * Format Nominatim results into our standard format
     */
    protected function formatNominatimResults(array $results): array
    {
        return collect($results)
            ->map(function ($result) {
                return [
                    'name' => $result['display_name'] ?? '',
                    'display_name' => $this->extractMainLocationName($result),
                    'latitude' => (float)$result['lat'],
                    'longitude' => (float)$result['lon'],
                    'address_components' => $this->parseNominatimAddress($result['address'] ?? []),
                    'place_id' => $result['osm_id'] . '_' . $result['osm_type'] ?? null,
                    'types' => [$result['type'] ?? 'location'],
                    'osm_type' => $result['osm_type'] ?? '',
                    'osm_id' => $result['osm_id'] ?? null,
                ];
            })
            ->take(10)
            ->values()
            ->toArray();
    }

    /**
     * Extract main location name from Nominatim address
     */
    protected function extractMainLocationName(array $result): string
    {
        $address = $result['address'] ?? [];

        // Priority order untuk nama lokasi
        if (!empty($address['city'])) {
            return $address['city'];
        }
        if (!empty($address['town'])) {
            return $address['town'];
        }
        if (!empty($address['municipality'])) {
            return $address['municipality'];
        }
        if (!empty($address['county'])) {
            return $address['county'];
        }
        if (!empty($address['state'])) {
            return $address['state'];
        }

        // Fallback ke display name
        return $result['display_name'] ?? 'Unknown Location';
    }

    /**
     * Parse Nominatim address components into standard format
     */
    protected function parseNominatimAddress(array $address): array
    {
        $components = [];

        // Map Nominatim address keys to component types
        $mapping = [
            'city' => 'locality',
            'town' => 'locality',
            'municipality' => 'locality',
            'state' => 'administrative_area_level_1',
            'province' => 'administrative_area_level_1',
            'country' => 'country',
            'postcode' => 'postal_code',
            'district' => 'administrative_area_level_2',
            'county' => 'administrative_area_level_2',
        ];

        foreach ($mapping as $nominatimKey => $componentType) {
            if (!empty($address[$nominatimKey])) {
                $components[$componentType] = [
                    'long_name' => $address[$nominatimKey],
                    'short_name' => $address[$nominatimKey],
                ];
            }
        }

        return $components;
    }

    /**
     * Get location details by coordinates (Reverse Geocoding)
     * Using Nominatim API - FREE!
     */
    public function reverseGeocode(float $latitude, float $longitude, array $options = []): array
    {
        try {
            $params = [
                'lat'            => $latitude,
                'lon'            => $longitude,
                'format'         => 'json',
                'language'       => $options['language'] ?? 'id',
                'addressdetails' => 1,
            ];

            $response = Http::withHeaders([
                'User-Agent' => $this->userAgent,
            ])->withoutVerifying()->get("{$this->nominatimUrl}/reverse", $params);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => 'Failed to fetch from Nominatim API',
                    'location' => null
                ];
            }

            $result = $response->json();

            if (empty($result) || isset($result['error'])) {
                return [
                    'success' => false,
                    'message' => 'No location found for these coordinates',
                    'location' => null
                ];
            }

            return [
                'success' => true,
                'message' => 'Location found',
                'location' => [
                    'name' => $result['display_name'] ?? '',
                    'address_components' => $this->parseNominatimAddress($result['address'] ?? []),
                    'latitude' => (float)$result['lat'],
                    'longitude' => (float)$result['lon'],
                    'osm_id' => $result['osm_id'] ?? null,
                    'osm_type' => $result['osm_type'] ?? '',
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error fetching location: ' . $e->getMessage(),
                'location' => null
            ];
        }
    }
}
