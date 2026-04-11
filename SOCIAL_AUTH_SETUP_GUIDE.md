# 🔐 Social Authentication - Google & Facebook OAuth

Fitur login dengan akun Google dan Facebook sudah selesai! Berikut panduan lengkapnya.

---

## 🎯 Alur Kerja

```
User klik "Login dengan Google/Facebook"
       ↓
Redirect ke OAuth Provider
       ↓
User authorize permission
       ↓
Redirect kembali ke callback URL dengan code
       ↓
Backend verify code & get user data
       ↓
Create/Update user di database
       ↓
Generate Sanctum token
       ↓
Return token ke frontend
```

---

## 🔧 Step 1: Setup Google OAuth Credentials

### 1.1 Buka Google Cloud Console

- Kunjungi: https://console.cloud.google.com/

### 1.2 Buat Project

- Klik **"Select a Project"** (atas)
- Klik **"NEW PROJECT"**
- Nama: `Marketplace API` (atau nama lain)
- Klik **"CREATE"**

### 1.3 Aktifkan Google+ API

- Cari **"Google+ API"** atau **"Google Identity"** di Search
- Klik **"ENABLE"**

### 1.4 Setup OAuth Consent Screen

- Di sidebar, klik **"OAuth consent screen"**
- Pilih **"External"**
- Klik **"CREATE"**
- Isi form:
  - **App name:** `Marketplace API`
  - **User support email:** your-email@gmail.com
  - Klik **"SAVE AND CONTINUE"**
- Di **"Scopes"**, tambahkan:
  - `userinfo.email`
  - `userinfo.profile`
  - Klik **"SAVE AND CONTINUE"**
- Di **"Test users"**, tambahkan email Anda sendiri (untuk testing)
- Klik **"SAVE AND CONTINUE"**

### 1.5 Buat OAuth 2.0 Credentials

- Di sidebar, klik **"Credentials"**
- Klik **"+ CREATE CREDENTIALS"** → **"OAuth client ID"**
- Pilih **"Web application"**
- **Name:** `Marketplace Web`
- Di **"Authorized redirect URIs"**, tambahkan:
  ```
  http://localhost:8000/api/v1/auth/google/callback
  http://yourdomain.com/api/v1/auth/google/callback
  ```
- Klik **"CREATE"**
- Copy **Client ID** dan **Client Secret**

### Simpan di `.env`:

```env
GOOGLE_CLIENT_ID=your_client_id_here
GOOGLE_CLIENT_SECRET=your_client_secret_here
GOOGLE_REDIRECT_URI=http://localhost:8000/api/v1/auth/google/callback
```

---

## 🔧 Step 2: Setup Facebook OAuth Credentials

### 2.1 Buka Facebook Developer

- Kunjungi: https://developers.facebook.com/

### 2.2 Buat App

- Klik **"My Apps"** → **"Create App"**
- **App Name:** `Marketplace API`
- **App Purpose:** Pilih yang paling sesuai
- Klik **"Create App"**

### 2.3 Setup Facebook Login Product

- Di dashboard app, klik **"+ Add Product"**
- Cari **"Facebook Login"** → Klik **"Set Up"**
- Pilih **"Web"**

### 2.4 Konfigurasi Valid OAuth Redirect URIs

- Di sidebar, buka **Settings** → **Basic**
- Copy **App ID** dan **App Secret**
- Buka **Settings** → **Facebook Login**
- Di **"Valid OAuth Redirect URIs"**, tambahkan:
  ```
  http://localhost:8000/api/v1/auth/facebook/callback
  http://yourdomain.com/api/v1/auth/facebook/callback
  ```
- Klik **"Save Changes"**

### Simpan di `.env`:

```env
FACEBOOK_APP_ID=your_app_id_here
FACEBOOK_APP_SECRET=your_app_secret_here
FACEBOOK_REDIRECT_URI=http://localhost:8000/api/v1/auth/facebook/callback
```

---

## 📋 Run Migration

Setelah setup, jalankan migration:

```bash
php artisan migrate
```

Ini akan menambahkan kolom ke tabel `users`:

- `provider` (google/facebook)
- `provider_id`
- `provider_token`
- `provider_refresh_token`

---

## 🔗 API Endpoints

### **1️⃣ Redirect ke Google**

```
GET /api/v1/auth/google
```

**Response:**

```json
{
  "success": true,
  "message": "Redirect to Google",
  "redirect_url": "https://accounts.google.com/o/oauth2/v2/auth?..."
}
```

**Frontend:** Redirect user ke `redirect_url`

---

### **2️⃣ Google OAuth Callback**

```
GET /api/v1/auth/google/callback?code=...&state=...
```

**Automatic:** Dihandle oleh backend  
**Response:** Redirect ke frontend dengan format:

```
http://frontend.com/oauth-callback?token=sanctum_token&user_id=123
```

---

### **3️⃣ Redirect ke Facebook**

```
GET /api/v1/auth/facebook
```

**Response:**

```json
{
  "success": true,
  "message": "Redirect to Facebook",
  "redirect_url": "https://www.facebook.com/v18.0/dialog/oauth?..."
}
```

---

### **4️⃣ Facebook OAuth Callback**

```
GET /api/v1/auth/facebook/callback?code=...&state=...
```

**Response:** Sama seperti Google callback

---

### **5️⃣ Unlink Social Account** (Protected)

```
DELETE /api/v1/auth/social/{provider}
Authorization: Bearer {token}
```

**Parameters:**

- `provider`: `google` atau `facebook`

**Response:**

```json
{
  "success": true,
  "message": "Successfully unlinked google account"
}
```

---

## 📱 Frontend Integration

### React Example

```jsx
import { useEffect } from "react";

export function SocialLogin() {
  const handleLoginGoogle = async () => {
    try {
      // Get redirect URL dari backend
      const res = await fetch("http://localhost:8000/api/v1/auth/google");
      const data = await res.json();

      // Redirect ke Google
      window.location.href = data.redirect_url;
    } catch (error) {
      console.error("Error:", error);
    }
  };

  const handleLoginFacebook = async () => {
    try {
      const res = await fetch("http://localhost:8000/api/v1/auth/facebook");
      const data = await res.json();
      window.location.href = data.redirect_url;
    } catch (error) {
      console.error("Error:", error);
    }
  };

  return (
    <div>
      <button onClick={handleLoginGoogle}>Login dengan Google</button>
      <button onClick={handleLoginFacebook}>Login dengan Facebook</button>
    </div>
  );
}
```

### Handle Callback

```jsx
import { useEffect } from "react";
import { useNavigate } from "react-router-dom";

export function OAuthCallback() {
  const navigate = useNavigate();

  useEffect(() => {
    // Backend akan redirect ke sini dengan token
    const params = new URLSearchParams(window.location.search);
    const token = params.get("token");

    if (token) {
      // Save token to localStorage
      localStorage.setItem("auth_token", token);

      // Redirect ke dashboard
      navigate("/dashboard");
    }
  }, [navigate]);

  return <div>Loading...</div>;
}
```

---

## ⚙️ Update `.env`

```env
# Google OAuth
GOOGLE_CLIENT_ID=123456789-abcdef...apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-1234567890abcdef
GOOGLE_REDIRECT_URI=http://localhost:8000/api/v1/auth/google/callback

# Facebook OAuth
FACEBOOK_APP_ID=1234567890
FACEBOOK_APP_SECRET=1234567890abcdef
FACEBOOK_REDIRECT_URI=http://localhost:8000/api/v1/auth/facebook/callback
```

---

## 🧪 Testing dengan Postman

### 1. Test Redirect Google

```
GET http://localhost:8000/api/v1/auth/google
```

Akan return `redirect_url`. Copy dan buka di browser.

### 2. Test Redirect Facebook

```
GET http://localhost:8000/api/v1/auth/facebook
```

Sama seperti Google.

---

## 🔒 Security Notes

### ✅ Best Practices:

1. **Jangan commit credentials ke GitHub**
   - Selalu gunakan `.env` file
   - Add `.env` ke `.gitignore`

2. **Use HTTPS di production**
   - OAuth Provider tidak terima HTTP untuk production
   - Self-signed certificates work untuk local testing

3. **Validate Redirect URIs**
   - Daftar semua redirect URI di OAuth Provider
   - Pastikan URL cocok persis (termasuk protokol dan port)

4. **Rate Limiting**
   - Implement rate limit untuk OAuth endpoints (optional)
   - Protect dari brute force attacks

5. **Token Storage**
   - Store `provider_token` secara aman
   - Jangan expose di client-side

---

## 🐛 Common Issues & Solutions

### ❌ Error: "Redirect URI mismatch"

**Solusi:**

- Pastikan redirect URI di OAuth Provider settings sama persis dengan `.env`
- Termasuk protocol (http/https), domain, port, dan path
- Contoh: `http://localhost:8000/api/v1/auth/google/callback`

### ❌ Error: "Invalid client_id"

**Solusi:**

- Copy lagi client_id dari OAuth Provider
- Pastikan tidak ada spasi di awal/akhir
- Verifikasi `.env` file

### ❌ Error: "Authorization code expired"

**Solusi:**

- Kode berlaku hanya 10 menit
- Jangan delay terlalu lama di process
- Test lagi dengan callback URL baru

### ❌ Error: "Scope tidak valid"

**Solusi:**

- Check scope yang direquest valid di OAuth Provider
- Pastikan sudah add scope di OAuth consent screen (untuk Google)

---

## 📊 User Data yang Disimpan

Ketika user login dengan social account:

```json
{
  "id": 123,
  "name": "John Doe",
  "email": "john@example.com",
  "avatar": "https://...",
  "provider": "google",
  "provider_id": "google_user_id_123",
  "provider_token": "access_token_...",
  "provider_refresh_token": null,
  "is_verified": true,
  "is_active": true
}
```

---

## 🔄 Token Generation

Setiap successful OAuth login akan generate **Sanctum token**:

```json
{
  "success": true,
  "message": "Login with google successful",
  "user": {...},
  "token": "1|abcdef...",
  "token_type": "Bearer"
}
```

**Penggunaan token di client:**

```javascript
fetch("/api/v1/me", {
  headers: {
    Authorization: "Bearer 1|abcdef...",
  },
});
```

---

## 📞 Support

Jika ada masalah:

1. Check error message di browser console
2. Lihat server logs: `php artisan serve`
3. Verify credentials di `.env` file
4. Test dengan Postman untuk debug

Sukses! 🎉
