# 🔧 Location API - Integration Examples

## Frontend React Component

```jsx
import React, { useState, useEffect, useRef } from "react";

export function LocationAutocomplete() {
  const [query, setQuery] = useState("");
  const [suggestions, setSuggestions] = useState([]);
  const [selectedLocation, setSelectedLocation] = useState(null);
  const [loading, setLoading] = useState(false);
  const timeoutRef = useRef(null);

  // Debounced search
  const handleSearch = (value) => {
    setQuery(value);

    if (timeoutRef.current) clearTimeout(timeoutRef.current);

    if (value.length < 2) {
      setSuggestions([]);
      return;
    }

    setLoading(true);
    timeoutRef.current = setTimeout(async () => {
      try {
        const response = await fetch(
          `/api/v1/locations/suggestions?q=${encodeURIComponent(value)}`,
        );
        const data = await response.json();

        if (data.success) {
          setSuggestions(data.suggestions);
        } else {
          setSuggestions([]);
        }
      } catch (error) {
        console.error("Error fetching suggestions:", error);
        setSuggestions([]);
      } finally {
        setLoading(false);
      }
    }, 300); // Debounce 300ms
  };

  const handleSelect = (location) => {
    setSelectedLocation(location);
    setQuery(location.label);
    setSuggestions([]);
  };

  return (
    <div className="location-autocomplete">
      <input
        type="text"
        value={query}
        onChange={(e) => handleSearch(e.target.value)}
        placeholder="Cari lokasi..."
        className="location-input"
      />

      {loading && <div className="loading">Mencari...</div>}

      {suggestions.length > 0 && (
        <div className="suggestions-dropdown">
          {suggestions.map((suggestion) => (
            <div
              key={suggestion.id}
              className="suggestion-item"
              onClick={() => handleSelect(suggestion)}
            >
              <div className="suggestion-label">{suggestion.label}</div>
              <div className="suggestion-desc">{suggestion.description}</div>
              <div className="suggestion-coords">
                {suggestion.latitude.toFixed(4)},{" "}
                {suggestion.longitude.toFixed(4)}
              </div>
            </div>
          ))}
        </div>
      )}

      {selectedLocation && (
        <div className="selected-location">
          <p>
            <strong>Lokasi:</strong> {selectedLocation.label}
          </p>
          <p>
            <strong>Alamat:</strong> {selectedLocation.description}
          </p>
          <p>
            <strong>Koordinat:</strong> {selectedLocation.latitude},{" "}
            {selectedLocation.longitude}
          </p>
        </div>
      )}
    </div>
  );
}

export default LocationAutocomplete;
```

---

## Frontend Vue Component

```vue
<template>
  <div class="location-autocomplete">
    <!-- Input Field -->
    <div class="input-group">
      <input
        v-model="query"
        @input="debounceSearch"
        @focus="showSuggestions = true"
        @blur="setTimeout(() => (showSuggestions = false), 200)"
        type="text"
        placeholder="Cari lokasi..."
        class="form-control"
      />
      <span v-if="loading" class="spinner"></span>
    </div>

    <!-- Suggestions Dropdown -->
    <div v-if="showSuggestions && suggestions.length > 0" class="dropdown-menu">
      <div
        v-for="suggestion in suggestions"
        :key="suggestion.id"
        class="dropdown-item"
        @click="selectLocation(suggestion)"
      >
        <div class="suggestion-title">{{ suggestion.label }}</div>
        <div class="suggestion-desc">{{ suggestion.description }}</div>
      </div>
    </div>

    <!-- Selected Location Display -->
    <div v-if="selectedLocation" class="alert alert-info mt-2">
      <p class="mb-1">
        <strong>Lokasi Terpilih:</strong> {{ selectedLocation.label }}
      </p>
      <p class="mb-0 small text-muted">
        📍 {{ selectedLocation.latitude }}, {{ selectedLocation.longitude }}
      </p>
    </div>

    <!-- Hidden inputs for form submission -->
    <input
      type="hidden"
      v-model="selectedLocation.label"
      name="location_city"
    />
    <input type="hidden" v-model="selectedLocation.latitude" name="latitude" />
    <input
      type="hidden"
      v-model="selectedLocation.longitude"
      name="longitude"
    />
    <input
      type="hidden"
      v-model="selectedLocation.id"
      name="location_place_id"
    />
  </div>
</template>

<script>
export default {
  name: "LocationAutocomplete",
  data() {
    return {
      query: "",
      suggestions: [],
      selectedLocation: null,
      loading: false,
      showSuggestions: false,
      searchTimeout: null,
    };
  },
  methods: {
    debounceSearch() {
      clearTimeout(this.searchTimeout);

      if (this.query.length < 2) {
        this.suggestions = [];
        this.showSuggestions = false;
        return;
      }

      this.loading = true;
      this.showSuggestions = true;

      this.searchTimeout = setTimeout(async () => {
        try {
          const response = await fetch(
            `/api/v1/locations/suggestions?q=${encodeURIComponent(this.query)}`,
          );
          const data = await response.json();

          if (data.success) {
            this.suggestions = data.suggestions;
          } else {
            this.suggestions = [];
          }
        } catch (error) {
          console.error("Search error:", error);
          this.suggestions = [];
        } finally {
          this.loading = false;
        }
      }, 300);
    },

    selectLocation(location) {
      this.selectedLocation = {
        id: location.id,
        label: location.label,
        description: location.description,
        latitude: location.latitude,
        longitude: location.longitude,
      };

      this.query = location.label;
      this.suggestions = [];
      this.showSuggestions = false;

      // Emit event untuk parent component
      this.$emit("location-selected", this.selectedLocation);
    },
  },
};
</script>

<style scoped>
.location-autocomplete {
  position: relative;
  margin-bottom: 1rem;
}

.input-group {
  position: relative;
  display: flex;
  align-items: center;
}

.form-control {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid #ced4da;
  border-radius: 0.25rem;
}

.spinner {
  position: absolute;
  right: 10px;
  width: 20px;
  height: 20px;
  border: 2px solid #f3f3f3;
  border-top: 2px solid #3498db;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}

.dropdown-menu {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: white;
  border: 1px solid #ced4da;
  border-top: none;
  max-height: 250px;
  overflow-y: auto;
  z-index: 10;
}

.dropdown-item {
  padding: 0.75rem 0.5rem;
  cursor: pointer;
  border-bottom: 1px solid #f0f0f0;
}

.dropdown-item:hover {
  background-color: #f8f9fa;
}

.suggestion-title {
  font-weight: 600;
  color: #333;
}

.suggestion-desc {
  font-size: 0.875rem;
  color: #666;
  margin-top: 0.25rem;
}

.alert-info {
  background-color: #d1ecf1;
  color: #0c5460;
  padding: 0.75rem;
  border-radius: 0.25rem;
}
</style>
```

---

## Vanilla JavaScript Implementation

```html
<!DOCTYPE html>
<html>
  <head>
    <title>Location Autocomplete</title>
    <style>
      .location-container {
        max-width: 500px;
        margin: 20px auto;
      }

      .location-input {
        width: 100%;
        padding: 10px;
        font-size: 14px;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-sizing: border-box;
      }

      .suggestions {
        position: absolute;
        width: 100%;
        max-width: 500px;
        background: white;
        border: 1px solid #ddd;
        border-top: none;
        max-height: 300px;
        overflow-y: auto;
        z-index: 10;
        display: none;
      }

      .suggestions.active {
        display: block;
      }

      .suggestion-item {
        padding: 10px;
        cursor: pointer;
        border-bottom: 1px solid #eee;
      }

      .suggestion-item:hover {
        background-color: #f5f5f5;
      }

      .suggestion-label {
        font-weight: bold;
        color: #333;
      }

      .suggestion-desc {
        font-size: 12px;
        color: #666;
        margin-top: 5px;
      }

      .selected-info {
        margin-top: 15px;
        padding: 10px;
        background-color: #e8f5e9;
        border-radius: 4px;
        display: none;
      }

      .selected-info.active {
        display: block;
      }
    </style>
  </head>
  <body>
    <div class="location-container">
      <input
        id="locationInput"
        class="location-input"
        type="text"
        placeholder="Cari lokasi..."
      />
      <div id="suggestionsContainer" class="suggestions"></div>
      <div id="selectedInfo" class="selected-info"></div>
    </div>

    <!-- Hidden form inputs -->
    <input type="hidden" id="locationCity" name="location_city" />
    <input type="hidden" id="latitude" name="latitude" />
    <input type="hidden" id="longitude" name="longitude" />
    <input type="hidden" id="placeId" name="location_place_id" />

    <script>
      const inputEl = document.getElementById("locationInput");
      const suggestionsEl = document.getElementById("suggestionsContainer");
      const selectedInfoEl = document.getElementById("selectedInfo");
      let debounceTimer;

      inputEl.addEventListener("input", (e) => {
        const query = e.target.value;

        clearTimeout(debounceTimer);

        if (query.length < 2) {
          suggestionsEl.classList.remove("active");
          return;
        }

        debounceTimer = setTimeout(() => {
          fetchSuggestions(query);
        }, 300);
      });

      async function fetchSuggestions(query) {
        try {
          const response = await fetch(
            `/api/v1/locations/suggestions?q=${encodeURIComponent(query)}`,
          );
          const data = await response.json();

          if (data.success && data.suggestions.length > 0) {
            displaySuggestions(data.suggestions);
            suggestionsEl.classList.add("active");
          } else {
            suggestionsEl.classList.remove("active");
            suggestionsEl.innerHTML =
              '<div style="padding:10px; color:#999;">Lokasi tidak ditemukan</div>';
          }
        } catch (error) {
          console.error("Error:", error);
          suggestionsEl.classList.remove("active");
        }
      }

      function displaySuggestions(suggestions) {
        suggestionsEl.innerHTML = suggestions
          .map(
            (s) => `
          <div class="suggestion-item" onclick="selectLocation(${JSON.stringify(s).replace(/"/g, "&quot;")})">
            <div class="suggestion-label">${s.label}</div>
            <div class="suggestion-desc">${s.description}</div>
          </div>
        `,
          )
          .join("");
      }

      function selectLocation(location) {
        inputEl.value = location.label;
        document.getElementById("locationCity").value = location.label;
        document.getElementById("latitude").value = location.latitude;
        document.getElementById("longitude").value = location.longitude;
        document.getElementById("placeId").value = location.id;

        suggestionsEl.classList.remove("active");

        // Show selected info
        selectedInfoEl.innerHTML = `
        <strong>Lokasi Terpilih:</strong> ${location.label}<br>
        <small>${location.description}</small><br>
        <small>📍 ${location.latitude}, ${location.longitude}</small>
      `;
        selectedInfoEl.classList.add("active");
      }

      // Hide suggestions when clicking outside
      document.addEventListener("click", (e) => {
        if (e.target !== inputEl) {
          suggestionsEl.classList.remove("active");
        }
      });
    </script>
  </body>
</html>
```

---

## Backend Integration (Laravel Controller)

```php
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\LocationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    protected LocationService $locationService;

    public function __construct(LocationService $locationService)
    {
        $this->locationService = $locationService;
    }

    /**
     * Store a newly created product
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
            'stock' => 'required|integer|min:1',
            'condition' => 'required|in:new,used',
            'transaction_type' => 'required|in:cod,rekber,both',

            // Location fields
            'location_city' => 'required|string|max:100',
            'location_province' => 'required|string|max:100',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'location_place_id' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $product = Product::create([
                'user_id' => $request->user()->id,
                'title' => $request->title,
                'slug' => Str::slug($request->title) . '-' . time(),
                'description' => $request->description,
                'price' => $request->price,
                'category_id' => $request->category_id,
                'stock' => $request->stock,
                'condition' => $request->condition,
                'transaction_type' => $request->transaction_type,
                'location_city' => $request->location_city,
                'location_province' => $request->location_province,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'location_place_id' => $request->location_place_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Product created successfully',
                'product' => $product
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating product: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update product location
     */
    public function updateLocation(Request $request, Product $product): JsonResponse
    {
        // Check ownership
        if ($product->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'location_city' => 'nullable|string|max:100',
            'location_province' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'location_place_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $product->update($request->only([
            'location_city',
            'location_province',
            'latitude',
            'longitude',
            'location_place_id'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Location updated',
            'product' => $product
        ]);
    }
}
```

---

## CSS Styling (Tailwind)

```html
<!-- Location Input Component -->
<div class="relative w-full">
  <!-- Input -->
  <input
    id="location-search"
    type="text"
    placeholder="Cari lokasi..."
    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
  />

  <!-- Loading Spinner -->
  <div id="loading" class="absolute right-3 top-3 hidden">
    <svg
      class="animate-spin h-5 w-5 text-blue-500"
      xmlns="http://www.w3.org/2000/svg"
      fill="none"
      viewBox="0 0 24 24"
    >
      <circle
        class="opacity-25"
        cx="12"
        cy="12"
        r="10"
        stroke="currentColor"
        stroke-width="4"
      ></circle>
      <path
        class="opacity-75"
        fill="currentColor"
        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
      ></path>
    </svg>
  </div>

  <!-- Suggestions Dropdown -->
  <div
    id="suggestions"
    class="absolute top-full left-0 right-0 mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto hidden z-10"
  >
    <!-- Populated by JavaScript -->
  </div>

  <!-- Selected Location Info -->
  <div
    id="selected-info"
    class="mt-3 p-3 bg-green-50 border border-green-200 rounded-lg hidden"
  >
    <p class="text-sm font-semibold text-green-800" id="selected-label"></p>
    <p class="text-xs text-gray-600 mt-1" id="selected-desc"></p>
    <p class="text-xs text-gray-500 mt-1" id="selected-coords"></p>
  </div>
</div>
```

---

## Error Handling

```javascript
async function handleLocationError(error) {
  console.error("Location API Error:", error);

  const errorMessages = {
    ZERO_RESULTS: "Lokasi tidak ditemukan. Coba query yang berbeda.",
    INVALID_REQUEST: "Request tidak valid.",
    OVER_QUERY_LIMIT: "Quota API sudah habis. Silahkan coba lagi nanti.",
    REQUEST_DENIED: "Request ditolak. Periksa API key.",
    UNKNOWN_ERROR: "Terjadi kesalahan. Silahkan coba lagi.",
  };

  const statusCode = error.status || "UNKNOWN_ERROR";
  const message = errorMessages[statusCode] || error.message;

  showErrorNotification(message);
}
```

---

## Testing

```bash
# Test dengan cURL
curl "http://localhost:8000/api/v1/locations/suggestions?q=Jakarta" \
  -H "Accept: application/json"

# Test dengan query yang berbeda
curl "http://localhost:8000/api/v1/locations/suggestions?q=Ban"
curl "http://localhost:8000/api/v1/locations/suggestions?q=Surabaya"

# Test reverse geocoding
curl "http://localhost:8000/api/v1/locations/reverse?latitude=-6.2088&longitude=106.8456"
```
