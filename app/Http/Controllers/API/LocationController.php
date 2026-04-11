<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\LocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LocationController extends Controller
{
    protected LocationService $locationService;

    public function __construct(LocationService $locationService)
    {
        $this->locationService = $locationService;
    }

    /**
     * Search locations (autocomplete)
     * GET /api/v1/locations/search?q=query
     */
    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:2|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
                'locations' => []
            ], 422);
        }

        $query = $request->input('q');
        
        $options = [
            'language' => $request->input('language', 'id'),
            'country' => $request->input('country', 'ID'),
        ];

        $result = $this->locationService->searchLocations($query, $options);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Get location details by coordinates (Reverse Geocoding)
     * GET /api/v1/locations/reverse?latitude=X&longitude=Y
     */
    public function reverse(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
                'location' => null
            ], 422);
        }

        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');

        $options = [
            'language' => $request->input('language', 'id'),
        ];

        $result = $this->locationService->reverseGeocode($latitude, $longitude, $options);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Get location suggestions for product
     * GET /api/v1/locations/suggestions?q=query
     * 
     * Mirip dengan search tapi dengan format yang cocok untuk product location
     */
    public function suggestions(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:1|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
                'suggestions' => []
            ], 422);
        }

        $query = $request->input('q');

        $result = $this->locationService->searchLocations($query);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'suggestions' => collect($result['locations'] ?? [])
                ->map(function ($location) {
                    return [
                        'id' => $location['place_id'],
                        'label' => $location['display_name'],
                        'description' => $location['name'],
                        'latitude' => $location['latitude'],
                        'longitude' => $location['longitude'],
                    ];
                })
                ->toArray(),
            'count' => $result['count'] ?? 0
        ], $result['success'] ? 200 : 400);
    }
}
