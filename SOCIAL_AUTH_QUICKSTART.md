# 🔐 Social Authentication - Quick Reference & Examples

## 📋 Setup Checklist

- [ ] Install Laravel Socialite (✅ done)
- [ ] Create Google OAuth credentials
- [ ] Create Facebook OAuth credentials
- [ ] Update `.env` dengan credentials
- [ ] Run migration: `php artisan migrate`
- [ ] Test di Postman
- [ ] Frontend integration

---

## ⚡ Quick Setup (TL;DR)

### 1. Setup Google
```
1. Buka: https://console.cloud.google.com/
2. Create project
3. Enable Google+ API
4. Setup OAuth Consent Screen
5. Create OAuth 2.0 Credentials (Web application)
6. Add Redirect URI: http://localhost:8000/api/v1/auth/google/callback
7. Copy Client ID & Secret ke .env
```

### 2. Setup Facebook
```
1. Buka: https://developers.facebook.com/
2. Create app
3. Add Facebook Login product
4. Setup OAuth Redirect URIs
5. Copy App ID & Secret ke .env
```

### 3. Update `.env`
```env
GOOGLE_CLIENT_ID=your_id
GOOGLE_CLIENT_SECRET=your_secret
GOOGLE_REDIRECT_URI=http://localhost:8000/api/v1/auth/google/callback

FACEBOOK_APP_ID=your_id
FACEBOOK_APP_SECRET=your_secret
FACEBOOK_REDIRECT_URI=http://localhost:8000/api/v1/auth/facebook/callback
```

### 4. Run Migration
```bash
php artisan migrate
```

---

## 🔗 API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v1/auth/google` | GET | Redirect ke Google OAuth |
| `/api/v1/auth/google/callback` | GET | Google OAuth callback |
| `/api/v1/auth/facebook` | GET | Redirect ke Facebook OAuth |
| `/api/v1/auth/facebook/callback` | GET | Facebook OAuth callback |
| `/api/v1/auth/social/{provider}` | DELETE | Unlink social account (protected) |

---

## 🧪 Testing Flow

### Step 1: Test Google Redirect
```bash
curl "http://localhost:8000/api/v1/auth/google"
```

**Response:**
```json
{
  "success": true,
  "redirect_url": "https://accounts.google.com/o/oauth2/v2/auth?..."
}
```

### Step 2: Open redirect_url
- Copy URL dari response
- Paste ke browser
- Login dengan akun Google
- Authorize permission
- Browser akan redirect ke callback URL

### Step 3: Check Token
- Check app console log
- Should have token dari backend

---

## 💻 Frontend Implementation

### React Component

```jsx
import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';

export function SocialLoginPage() {
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

  const handleGoogleLogin = async () => {
    setLoading(true);
    try {
      const response = await fetch('http://localhost:8000/api/v1/auth/google');
      const data = await response.json();
      
      if (data.success) {
        window.location.href = data.redirect_url;
      }
    } catch (error) {
      console.error('Error:', error);
      setLoading(false);
    }
  };

  const handleFacebookLogin = async () => {
    setLoading(true);
    try {
      const response = await fetch('http://localhost:8000/api/v1/auth/facebook');
      const data = await response.json();
      
      if (data.success) {
        window.location.href = data.redirect_url;
      }
    } catch (error) {
      console.error('Error:', error);
      setLoading(false);
    }
  };

  return (
    <div className="social-login-container">
      <h2>Login dengan</h2>
      
      <button 
        onClick={handleGoogleLogin} 
        disabled={loading}
        className="btn-google"
      >
        🔵 Google
      </button>

      <button 
        onClick={handleFacebookLogin} 
        disabled={loading}
        className="btn-facebook"
      >
        🔵 Facebook
      </button>
    </div>
  );
}

// Callback component
export function OAuthCallbackPage() {
  useEffect(() => {
    // Backend akan redirect ke sini dengan token
    const params = new URLSearchParams(window.location.search);
    const token = params.get('token');
    
    if (token) {
      localStorage.setItem('auth_token', token);
      window.location.href = '/dashboard';
    }
  }, []);

  return <div>Loading...</div>;
}
```

### Vue Component

```vue
<template>
  <div class="social-login">
    <h2>Login dengan</h2>
    
    <button @click="loginGoogle" :disabled="loading">
      🔵 Google
    </button>

    <button @click="loginFacebook" :disabled="loading">
      🔵 Facebook
    </button>
  </div>
</template>

<script>
export default {
  data() {
    return {
      loading: false,
    };
  },
  methods: {
    async loginGoogle() {
      this.loading = true;
      try {
        const response = await fetch('http://localhost:8000/api/v1/auth/google');
        const data = await response.json();
        window.location.href = data.redirect_url;
      } catch (error) {
        console.error('Error:', error);
        this.loading = false;
      }
    },
    async loginFacebook() {
      this.loading = true;
      try {
        const response = await fetch('http://localhost:8000/api/v1/auth/facebook');
        const data = await response.json();
        window.location.href = data.redirect_url;
      } catch (error) {
        console.error('Error:', error);
        this.loading = false;
      }
    },
  },
};
</script>
```

---

## 🧪 Postman Testing Collection

Save ini sebagai collection JSON:

```json
{
  "info": {
    "name": "Social Auth Testing",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "item": [
    {
      "name": "Google - Redirect",
      "request": {
        "method": "GET",
        "url": "{{base_url}}/api/v1/auth/google"
      }
    },
    {
      "name": "Facebook - Redirect",
      "request": {
        "method": "GET",
        "url": "{{base_url}}/api/v1/auth/facebook"
      }
    },
    {
      "name": "Unlink Google (Protected)",
      "request": {
        "method": "DELETE",
        "url": "{{base_url}}/api/v1/auth/social/google",
        "header": [
          {
            "key": "Authorization",
            "value": "Bearer {{token}}"
          }
        ]
      }
    }
  ]
}
```

---

## 📊 Database Schema

Migration menambahkan kolom-kolom ini ke tabel `users`:

```sql
ALTER TABLE users ADD COLUMN provider VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN provider_id VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN provider_token LONGTEXT NULL;
ALTER TABLE users ADD COLUMN provider_refresh_token LONGTEXT NULL;

CREATE INDEX users_provider_provider_id_index 
ON users(provider, provider_id);
```

---

## 🔄 OAuth Flow Diagram

```
Frontend                Backend                 OAuth Provider
   │                      │                           │
   ├─ Click "Google" ───→ │                           │
   │                      │                           │
   │                      ├─ Generate auth URL ──────→│
   │                      │                           │
   │  ← Redirect URL ─────┤                           │
   │                      │                           │
   ├─ Redirect to URL ────────────────────────────────→│
   │                      │                           │
   │                      │  ← auth code ─────────────┤
   │   (user authorizes)  │                           │
   │                      ├─ Exchange code for token ─→│
   │                      │                           │
   │                      │← user data + token ───────┤
   │                      │                           │
   │                      ├─ Create/Update user       │
   │                      ├─ Generate Sanctum token   │
   │                      │                           │
   │← Callback w/ token ──┤                           │
   │                      │                           │
   ├─ Save token locally  │                           │
   │                      │                           │
   └─ Redirect to app ────→                           │
```

---

## ✅ Response Examples

### Successful Login
```json
{
  "success": true,
  "message": "Login with google successful",
  "user": {
    "id": 148,
    "name": "Budi Santoso",
    "email": "budi@example.com",
    "avatar": "https://lh3.googleusercontent.com/...",
    "is_seller": false,
    "is_buyer": true
  },
  "token": "1|VZKjUgv9XZ...",
  "token_type": "Bearer"
}
```

### Error Response
```json
{
  "success": false,
  "message": "Google authentication failed: Invalid authorization code"
}
```

---

## 🔒 Production Checklist

- [ ] Update redirect URIs untuk production domain
- [ ] Use HTTPS everywhere
- [ ] Add CSRF protection
- [ ] Enable rate limiting
- [ ] Monitor OAuth errors
- [ ] Backup OAuth credentials secara aman
- [ ] Setup email notifications untuk new logins
- [ ] Test account linking edge cases

---

## 📞 Troubleshooting

### Token work di Postman tapi tidak di frontend?
- Check CORS settings
- Add header: `'Content-Type': 'application/json'`
- Pastikan token disimpan dengan benar

### Redirect URI tidak match?
- Copy-paste exact URL dari error message
- Hapuskan trailing slashes
- Match protocol (http vs https)

### User created tapi data kosong?
- Check scope permissions di OAuth Provider
- Verify user profile completion flow

---

Selamat! Social auth sudah siap! 🎉
