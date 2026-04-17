<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use App\Models\UserProfile;    
use App\Models\SellerProfile;  
use Illuminate\Support\Facades\DB;
class SocialAuthController extends Controller
{
    /**
     * Redirect user to Google for authentication
     * GET /api/v1/login/google?state=...&redirect_uri=...
     */
    public function redirectToGoogle(Request $request): JsonResponse
    {
        try {
            $frontendState = $request->query('state');
            
            if (!$frontendState) {
                return response()->json([
                    'success' => false,
                    'message' => 'State parameter is required',
                ], 400);
            }
            
            // Generate backend state and nonce for OAuth security
            $backendState = Str::random(40);
            $nonce = Str::random(40);
            
            // Map frontend state to backend state for validation later
            Cache::put("oauth_state_google_map_{$backendState}", [
                'frontend_state' => $frontendState,
                'nonce' => $nonce,
            ], now()->addMinutes(10));

            // Build Google OAuth URL manually
            $params = [
                'client_id' => config('services.google.client_id'),
                'redirect_uri' => config('services.google.redirect'),
                'response_type' => 'code',
                'scope' => 'openid profile email',
                'state' => $backendState,
                'nonce' => $nonce,
                'access_type' => 'offline',
            ];

            $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);

            return response()->json([
                'success' => true,
                'message' => 'Redirect to Google',
                'redirect_url' => $url,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to redirect to Google: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Handle Google OAuth callback
     * GET /api/v1/login/google/callback?code=...&state=...
     */
    public function handleGoogleCallback(Request $request): RedirectResponse|JsonResponse
    {
        try {
            $code = $request->query('code');
            $backendState = $request->query('state');
            
            if (!$code || !$backendState) {
                return $this->redirectToFrontendCallback([
                    'success' => 'false',
                    'message' => 'Missing authorization code or state',
                ]);
            }
            
            // Verify backend state and get frontend state + nonce
            $stateData = Cache::get("oauth_state_google_map_{$backendState}");
            if (!$stateData) {
                return $this->redirectToFrontendCallback([
                    'success' => 'false',
                    'message' => 'Invalid OAuth state',
                ]);
            }
            
            $frontendState = $stateData['frontend_state'];
            Cache::forget("oauth_state_google_map_{$backendState}");

            // Exchange authorization code for access token
            $response = Http::post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => config('services.google.redirect'),
                'grant_type' => 'authorization_code',
                'code' => $code,
            ]);

            if ($response->failed()) {
                return $this->redirectToFrontendCallback([
                    'success' => 'false',
                    'message' => 'Failed to exchange authorization code',
                ]);
            }

            $tokenData = $response->json();
            $accessToken = $tokenData['access_token'];

            // Get user info from Google using access token
            $userResponse = Http::withToken($accessToken)->get('https://www.googleapis.com/oauth2/v2/userinfo');
            
            if ($userResponse->failed()) {
                return $this->redirectToFrontendCallback([
                    'success' => 'false',
                    'message' => 'Failed to retrieve user information',
                ]);
            }

            $googleUser = $userResponse->json();
            
            // Create a mock socialite user object
            $socialUser = (object) [
                'id' => $googleUser['id'],
                'name' => $googleUser['name'] ?? null,
                'email' => $googleUser['email'],
                'avatar' => $googleUser['picture'] ?? null,
                'token' => $accessToken,
                'refreshToken' => $tokenData['refresh_token'] ?? null,
            ];

            $result = $this->handleSocialCallback($socialUser, 'google');
            
            // Redirect to frontend callback with state
            return $this->redirectToFrontendCallback([
                'success' => 'true',
                'state' => $frontendState,
                'token' => $result['token'],
                'token_type' => $result['token_type'],
                'user' => json_encode($result['user']),
            ]);
        } catch (Exception $e) {
            return $this->redirectToFrontendCallback([
                'success' => 'false',
                'message' => 'Google authentication failed: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Redirect user to Facebook for authentication
     * GET /api/v1/login/facebook?state=...&redirect_uri=...
     */
    public function redirectToFacebook(Request $request): JsonResponse
    {
        try {
            $frontendState = $request->query('state');
            
            if (!$frontendState) {
                return response()->json([
                    'success' => false,
                    'message' => 'State parameter is required',
                ], 400);
            }
            
            // Generate backend state for OAuth security
            $backendState = Str::random(40);
            
            // Map frontend state to backend state
            Cache::put("oauth_state_facebook_map_{$backendState}", [
                'frontend_state' => $frontendState,
            ], now()->addMinutes(10));

            // Build Facebook OAuth URL manually
            $params = [
                'client_id' => config('services.facebook.client_id'),
                'redirect_uri' => config('services.facebook.redirect'),
                'response_type' => 'code',
                'scope' => 'public_profile,email',
                'state' => $backendState,
            ];

            $url = 'https://www.facebook.com/v12.0/dialog/oauth?' . http_build_query($params);

            return response()->json([
                'success' => true,
                'message' => 'Redirect to Facebook',
                'redirect_url' => $url,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to redirect to Facebook: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Handle Facebook OAuth callback
     * GET /api/v1/login/facebook/callback?code=...&state=...
     */
    public function handleFacebookCallback(Request $request): RedirectResponse|JsonResponse
    {
        try {
            $code = $request->query('code');
            $backendState = $request->query('state');
            
            if (!$code || !$backendState) {
                return $this->redirectToFrontendCallback([
                    'success' => 'false',
                    'message' => 'Missing authorization code or state',
                ]);
            }
            
            // Verify backend state and get frontend state
            $stateData = Cache::get("oauth_state_facebook_map_{$backendState}");
            if (!$stateData) {
                return $this->redirectToFrontendCallback([
                    'success' => 'false',
                    'message' => 'Invalid OAuth state',
                ]);
            }
            
            $frontendState = $stateData['frontend_state'];
            Cache::forget("oauth_state_facebook_map_{$backendState}");

            // Exchange authorization code for access token
            $response = Http::get('https://graph.facebook.com/v12.0/oauth/access_token', [
                'client_id' => config('services.facebook.client_id'),
                'client_secret' => config('services.facebook.client_secret'),
                'redirect_uri' => config('services.facebook.redirect'),
                'code' => $code,
            ]);

            if ($response->failed()) {
                return $this->redirectToFrontendCallback([
                    'success' => 'false',
                    'message' => 'Failed to exchange authorization code',
                ]);
            }

            $tokenData = $response->json();
            $accessToken = $tokenData['access_token'];

            // Get user info from Facebook using access token
            $userResponse = Http::get('https://graph.facebook.com/me', [
                'fields' => 'id,name,email,picture',
                'access_token' => $accessToken,
            ]);
            
            if ($userResponse->failed()) {
                return $this->redirectToFrontendCallback([
                    'success' => 'false',
                    'message' => 'Failed to retrieve user information',
                ]);
            }

            $facebookUser = $userResponse->json();
            
            // Create a mock socialite user object
            $socialUser = (object) [
                'id' => $facebookUser['id'],
                'name' => $facebookUser['name'] ?? null,
                'email' => $facebookUser['email'] ?? null,
                'avatar' => $facebookUser['picture']['data']['url'] ?? null,
                'token' => $accessToken,
                'refreshToken' => null,
            ];

            $result = $this->handleSocialCallback($socialUser, 'facebook');
            
            // Redirect to frontend callback with state
            return $this->redirectToFrontendCallback([
                'success' => 'true',
                'state' => $frontendState,
                'token' => $result['token'],
                'token_type' => $result['token_type'],
                'user' => json_encode($result['user']),
            ]);
        } catch (Exception $e) {
            return $this->redirectToFrontendCallback([
                'success' => 'false',
                'message' => 'Facebook authentication failed: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle social authentication callback (Google/Facebook)
     * 
     * 1. Check if user exists by provider_id
     * 2. If exists, update token and login
     * 3. If not exists, create new user and login
     * 
     * @throws Exception
     */
    protected function handleSocialCallback($socialUser, string $provider): array
    {
        try {
            // Find user by provider_id
            $user = User::where('provider', $provider)
                ->where('provider_id', $socialUser->id)
                ->first();

            // If user doesn't exist, create new user
            if (!$user) {
                // Check if email already exists
                $existingUser = User::where('email', $socialUser->email)->first();

                if ($existingUser) {
                    // Link social account to existing user
                    $user = $existingUser;
                } else {
                    // Create new user
                    $user = User::create([
                        'name' => $socialUser->name,
                        'email' => $socialUser->email,
                        'phone' => null,
                        'provider' => $provider,
                        'provider_id' => $socialUser->id,
                        'avatar' => $socialUser->avatar,
                        'password' => bcrypt(Str::random(16)), // Random password
                        'is_buyer' => true,
                        'is_seller' => true,
                        'is_verified' => true, // Auto-verified dengan social login
                        'is_active' => true,
                    ]);
                }
            }

            // Update provider tokens
            $user->update([
                'provider' => $provider,
                'provider_id' => $socialUser->id,
                'provider_token' => $socialUser->token,
                'provider_refresh_token' => $socialUser->refreshToken ?? null,
            ]);

            // Generate Sanctum token
            $token = $user->createToken('marketplace-token')->plainTextToken;

            return [
                'success' => true,
                'message' => "Login with {$provider} successful",
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'is_seller' => $user->is_seller,
                    'is_buyer' => $user->is_buyer,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ];

        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Helper method to redirect to frontend callback page with OAuth data
     */
    protected function redirectToFrontendCallback(array $params): RedirectResponse
    {
        $frontendUrl = config('services.frontend.url');
        $callbackPath = config('services.frontend.callback_path');
        $redirectUrl = $frontendUrl . $callbackPath;
        
        return redirect($redirectUrl . '?' . http_build_query($params));
    }

    /**
     * Unlink social account from user
     * DELETE /api/v1/auth/social/{provider}
     */
    public function unlinkSocialAccount(Request $request, string $provider): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user->provider !== $provider) {
                return response()->json([
                    'success' => false,
                    'message' => "User is not linked with {$provider}",
                ], 400);
            }

            // Check if user has password (untuk login dengan password)
            if (empty($user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot unlink social account without setting password first',
                ], 400);
            }

            // Unlink social account
            $user->update([
                'provider' => null,
                'provider_id' => null,
                'provider_token' => null,
                'provider_refresh_token' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Successfully unlinked {$provider} account",
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error unlinking social account: ' . $e->getMessage(),
            ], 500);
        }
    }
}
