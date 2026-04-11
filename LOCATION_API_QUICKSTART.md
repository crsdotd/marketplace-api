# 🚀 Quick Start Guide - Location Autocomplete API

## Setup dalam 5 langkah

### 1️⃣ Dapatkan Google Maps API Key

- Buka https://console.cloud.google.com/
- Aktifkan **Geocoding API**
- Generate API key di **Credentials**

### 2️⃣ Tambahkan ke `.env`

```bash
GOOGLE_MAPS_API_KEY=your_api_key_here
```

### 3️⃣ Jalankan Migration

```bash
php artisan migrate
```

### 4️⃣ Test API dengan Postman/cURL

```bash
# Test search lokasi
curl "http://localhost:8000/api/v1/locations/search?q=Jakarta"

# Test suggestions (format lebih simple)
curl "http://localhost:8000/api/v1/locations/suggestions?q=Ban"

# Test reverse geocoding
curl "http://localhost:8000/api/v1/locations/reverse?latitude=-6.2088&longitude=106.8456"
```

### 5️⃣ Integrate ke Frontend (JavaScript)

```javascript
// Input lokasi di HTML
<input id="location" placeholder="Cari lokasi..." />
<div id="suggestions"></div>

// JavaScript
document.getElementById('location').addEventListener('input', async (e) => {
  const q = e.target.value;
  if (q.length < 2) return;

  const res = await fetch(`/api/v1/locations/suggestions?q=${q}`);
  const data = await res.json();

  // Tampilkan suggestions
  document.getElementById('suggestions').innerHTML = data.suggestions
    .map(s => `<div onclick="select('${s.label}', ${s.latitude}, ${s.longitude})">${s.label}</div>`)
    .join('');
});

function select(label, lat, lng) {
  document.getElementById('location').value = label;
  document.getElementById('latitude').value = lat;
  document.getElementById('longitude').value = lng;
}
```

---

## 📋 Available Endpoints

| Endpoint                        | Method | Deskripsi                    |
| ------------------------------- | ------ | ---------------------------- |
| `/api/v1/locations/search`      | GET    | Cari lokasi (detail lengkap) |
| `/api/v1/locations/suggestions` | GET    | Cari lokasi (format simple)  |
| `/api/v1/locations/reverse`     | GET    | Dapatkan nama dari koordinat |

---

## 💡 Gunakan saat membuat produk

```bash
POST /api/v1/products
Authorization: Bearer {token}
Content-Type: application/json

{
  "title": "Sepeda Gunung",
  "description": "Sepeda bekas dalam kondisi baik",
  "price": 500000,
  "category_id": 1,
  "stock": 1,
  "condition": "used",
  "transaction_type": "cod",
  "location_city": "Jakarta",
  "location_province": "DKI Jakarta",
  "latitude": -6.2088,
  "longitude": 106.8456,
  "location_place_id": "ChIJ-bvJl..."
}
```

---

## ✅ Features

✨ Autocomplete lokasi seperti Google Maps  
🌍 Dukungan bahasa Indonesia  
📍 Latitude/Longitude otomatis  
🔄 Reverse geocoding  
⚡ Response cepat dan akurat

---

## 📚 Dokumentasi Lengkap

Lihat **LOCATION_API_DOCS.md** untuk dokumentasi lengkap dengan contoh code Vue.js dan JavaScript vanilla.
