<?php

namespace App\Http\Controllers;

use App\Rules\EndpointPushPermitido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'endpoint' => ['required', 'string', 'max:500', new EndpointPushPermitido],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'contentEncoding' => ['nullable', 'string', Rule::in(['aesgcm', 'aes128gcm'])],
        ]);

        Auth::user()->updatePushSubscription(
            endpoint: $datos['endpoint'],
            key: $datos['keys']['p256dh'],
            token: $datos['keys']['auth'],
            contentEncoding: $datos['contentEncoding'] ?? null,
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
        ]);

        Auth::user()->deletePushSubscription($datos['endpoint']);

        return response()->json(['ok' => true]);
    }
}
