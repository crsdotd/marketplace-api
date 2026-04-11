# 📍 Dokumentasi API Autocomplete Lokasi

Fitur autocomplete lokasi menggunakan **Google Maps Geocoding API** untuk memberikan saran lokasi secara real-time saat menambahkan atau mengedit produk.

## 🔧 Setup & Konfigurasi

### 1. Dapatkan Google Maps API Key

1. Buka [Google Cloud Console](https://console.cloud.google.com/)
2. Buat project baru atau gunakan yang sudah ada
3. Aktifkan **Google Maps Geocoding API** dan **Google Maps JavaScript API**
4. Buat API key di **Credentials** → **Create Credentials** → **API Key**
5. Restrict key ke **Geocoding API** saja untuk keamanan

### 2. Konfigurasi Environment Variable

Tambahkan API key ke file `.env`:

```env
GOOGLE_MAPS_API_KEY=your_actual_api_key_here
```

### 3. Jalankan Migration

```bash
php artisan migrate
```

Ini akan menambah 2 kolom ke tabel `products`:

- `location_place_id` - Google Maps Place ID
- `location_address` - Alamat lengkap

---

## 🔌 Endpoints API

### 1. **GET** `/api/v1/locations/search`

Cari lokasi dengan query autocomplete

**Query Parameters:**
| Parameter | Type | Required | Deskripsi |
|-----------|------|----------|-----------|
| `q` | string | ✅ | Query pencarian lokasi (min. 2 karakter) |
| `language` | string | ❌ | Bahasa (default: `id`) |
| `country` | string | ❌ | Kode negara ISO-3166-1 (default: `ID`) |

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Locations found",
  "locations": [
    {
      "name": "Jakarta, Daerah Khusus Ibukota Jakarta, Indonesia",
      "display_name": "Jakarta",
      "latitude": -6.2088,
      "longitude": 106.8456,
      "address_components": {
        "locality": {"long_name": "Jakarta", "short_name": "Jakarta"},
        "administrative_area_level_1": {...},
        "country": {...}
      },
      "place_id": "ChIJ-bvJl...",
      "types": ["locality", "political"],
      "viewport": {
        "northeast": {"lat": -6.0726..., "lng": 107.0218...},
        "southwest": {"lat": -6.3844..., "lng": 106.6969...}
      }
    },
    {...}
  ],
  "count": 1
}
```

**Response (Error 400):**

```json
{
  "success": false,
  "message": "No locations found",
  "locations": [],
  "status": "ZERO_RESULTS"
}
```

**Contoh Request:**

```bash
curl "http://localhost:8000/api/v1/locations/search?q=Jakarta"
```

---

### 2. **GET** `/api/v1/locations/suggestions`

Dapatkan saran lokasi dengan format yang lebih simpel

**Query Parameters:**
| Parameter | Type | Required | Deskripsi |
|-----------|------|----------|-----------|
| `q` | string | ✅ | Query pencarian (min. 1 karakter) |

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Locations found",
  "suggestions": [
    {
      "id": "ChIJ-bvJl...",
      "label": "Jakarta",
      "description": "Jakarta, Daerah Khusus Ibukota Jakarta, Indonesia",
      "latitude": -6.2088,
      "longitude": 106.8456
    },
    {...}
  ],
  "count": 1
}
```

**Contoh Request:**

```bash
curl "http://localhost:8000/api/v1/locations/suggestions?q=Ban"
```

---

### 3. **GET** `/api/v1/locations/reverse`

Dapatkan nama lokasi dari koordinat (Reverse Geocoding)

**Query Parameters:**
| Parameter | Type | Required | Deskripsi |
|-----------|------|----------|-----------|
| `latitude` | float | ✅ | Latitude (-90 hingga 90) |
| `longitude` | float | ✅ | Longitude (-180 hingga 180) |
| `language` | string | ❌ | Bahasa (default: `id`) |

**Response (200 OK):**

```json
{
  "success": true,
  "message": "Location found",
  "location": {
    "name": "Jakarta, Daerah Khusus Ibukota Jakarta, Indonesia",
    "address_components": {
      "locality": {"long_name": "Jakarta", "short_name": "Jakarta"},
      "administrative_area_level_1": {...},
      "country": {...}
    }
  }
}
```

**Contoh Request:**

```bash
curl "http://localhost:8000/api/v1/locations/reverse?latitude=-6.2088&longitude=106.8456"
```

---

## 📝 Contoh Implementasi di Frontend

### JavaScript/Fetch API

```javascript
// 1. Autocomplete lokasi saat user mengetik
const locationInput = document.getElementById("location-input");
const suggestionsList = document.getElementById("suggestions-list");

locationInput.addEventListener("input", async (e) => {
  const query = e.target.value;

  if (query.length < 2) {
    suggestionsList.innerHTML = "";
    return;
  }

  try {
    const response = await fetch(
      `/api/v1/locations/suggestions?q=${encodeURIComponent(query)}`,
    );
    const data = await response.json();

    if (data.success) {
      suggestionsList.innerHTML = data.suggestions
        .map(
          (suggestion) => `
          <div class="suggestion-item" onclick="selectLocation(${JSON.stringify(suggestion).replace(/"/g, "&quot;")})">
            <div class="suggestion-label">${suggestion.label}</div>
            <div class="suggestion-desc">${suggestion.description}</div>
          </div>
        `,
        )
        .join("");
    }
  } catch (error) {
    console.error("Error fetching locations:", error);
  }
});

// 2. Ketika user memilih saran lokasi
function selectLocation(location) {
  // Set nilai ke form
  document.getElementById("location-input").value = location.label;
  document.getElementById("latitude").value = location.latitude;
  document.getElementById("longitude").value = location.longitude;
  document.getElementById("place-id").value = location.id;

  suggestionsList.innerHTML = ""; // Clear suggestions
}

// 3. Submit form dengan data lokasi
document
  .getElementById("product-form")
  .addEventListener("submit", async (e) => {
    e.preventDefault();

    const formData = new FormData(e.target);

    try {
      const response = await fetch("/api/v1/products", {
        method: "POST",
        headers: {
          Authorization: `Bearer ${accessToken}`,
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          title: formData.get("title"),
          description: formData.get("description"),
          price: formData.get("price"),
          category_id: formData.get("category_id"),
          location_city: formData.get("location-label"),
          location_place_id: formData.get("place-id"),
          latitude: formData.get("latitude"),
          longitude: formData.get("longitude"),
          // ... field lainnya
        }),
      });

      const result = await response.json();
      if (result.success) {
        alert("Produk berhasil ditambahkan!");
      }
    } catch (error) {
      console.error("Error creating product:", error);
    }
  });
```

### Vue.js Example

```vue
<template>
  <div class="location-search">
    <input
      v-model="searchQuery"
      @input="searchLocations"
      placeholder="Cari lokasi..."
      type="text"
    />

    <div v-if="suggestions.length" class="suggestions">
      <div
        v-for="suggestion in suggestions"
        :key="suggestion.id"
        class="suggestion-item"
        @click="selectLocation(suggestion)"
      >
        <div class="label">{{ suggestion.label }}</div>
        <div class="desc">{{ suggestion.description }}</div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      searchQuery: "",
      suggestions: [],
    };
  },
  methods: {
    async searchLocations() {
      if (this.searchQuery.length < 2) {
        this.suggestions = [];
        return;
      }

      try {
        const response = await fetch(
          `/api/v1/locations/suggestions?q=${encodeURIComponent(this.searchQuery)}`,
        );
        const data = await response.json();

        if (data.success) {
          this.suggestions = data.suggestions;
        }
      } catch (error) {
        console.error("Error:", error);
      }
    },
    selectLocation(location) {
      this.$emit("location-selected", {
        city: location.label,
        address: location.description,
        latitude: location.latitude,
        longitude: location.longitude,
        placeId: location.id,
      });
      this.suggestions = [];
    },
  },
};
</script>
```

---

## 🔐 Security Considerations

### 1. API Key Protection

```env
# Jangan commit API key ke repository!
GOOGLE_MAPS_API_KEY=your_api_key_here
```

### 2. Rate Limiting (Optional - gunakan Throttle Middleware)

```php
// di routes/api.php
Route::middleware('throttle:100,1')->get('/locations/search', [...]);
```

### 3. Input Validation

- Query minimal 2 karakter untuk `/search`
- Latitude/Longitude di validasi range yang benar

---

## 🐛 Troubleshooting

### API Key tidak valid

```
"error_message": "The provided API key is invalid"
```

**Solusi:** Pastikan API key sudah diaktifkan di Google Cloud Console

### ZERO_RESULTS

```
"status": "ZERO_RESULTS"
```

**Solusi:** Query tidak ditemukan. Coba query yang lebih spesifik atau gunakan nama kota yang berbeda

### Quota Exceeded

```
"status": "OVER_QUERY_LIMIT"
```

**Solusi:** Google Maps API quota sudah habis. Upgrade paket atau tunggu hingga bulan berikutnya

---

## 📊 Database Schema

### Products Table Additions

```sql
ALTER TABLE products ADD COLUMN location_place_id VARCHAR(255) NULL;
ALTER TABLE products ADD COLUMN location_address VARCHAR(500) NULL;

-- Existing columns yang sudah ada:
-- location_city VARCHAR(100)
-- location_province VARCHAR(100)
-- latitude DECIMAL(10,8)
-- longitude DECIMAL(11,8)
```

---

## 🚀 Performance Tips

1. **Cache Results**

   ```php
   $result = Cache::remember("location:{$query}", 3600, function () use ($service, $query) {
       return $service->searchLocations($query);
   });
   ```

2. **Rate Limiting**
   - Free tier Google Maps: 25,000 requests/hari gratis
   - Pertimbangkan caching untuk query yang sering

3. **Frontend Optimization**
   - Debounce input untuk mengurangi API calls
   - Minimal 2-3 karakter sebelum search

---

## 📞 Support

Jika ada masalah atau pertanyaan, silahkan hubungi tim development.
