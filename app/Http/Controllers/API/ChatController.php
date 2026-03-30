<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ChatContact;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    /**
     * POST /api/v1/chat/whatsapp
     * Generate link WhatsApp langsung ke seller/buyer
     */
    public function whatsappLink(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'message'    => 'sometimes|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $product = Product::with('seller')->findOrFail($request->product_id);
        $seller  = $product->seller;

        if (!$seller->wa_number) {
            return response()->json([
                'success' => false,
                'message' => 'Penjual belum mengatur nomor WhatsApp.',
            ], 422);
        }

        // Format nomor WA internasional
        $waNumber = preg_replace('/[^0-9]/', '', $seller->wa_number);
        $waNumber = preg_replace('/^0/', '62', $waNumber);

        // Pesan otomatis jika tidak ada
        $message = $request->message ?? sprintf(
            "Halo Kak, saya tertarik dengan produk *%s* yang dijual seharga Rp %s. Apakah masih tersedia?",
            $product->title,
            number_format($product->price, 0, ',', '.')
        );

        $encodedMessage = urlencode($message);
        $waLink = "https://wa.me/{$waNumber}?text={$encodedMessage}";

        // Log percakapan
        ChatContact::create([
            'from_user_id'    => $request->user()->id,
            'to_user_id'      => $seller->id,
            'product_id'      => $product->id,
            'wa_number'       => $waNumber,
            'initial_message' => $message,
        ]);

        return response()->json([
            'success'   => true,
            'data'      => [
                'wa_link'   => $waLink,
                'wa_number' => $waNumber,
                'message'   => $message,
                'seller'    => [
                    'id'   => $seller->id,
                    'name' => $seller->name,
                ],
            ],
        ]);
    }
}
