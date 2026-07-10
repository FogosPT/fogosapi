<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\LiveActivityToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LiveActivityController extends Controller
{
    private const VALID_ENVS = [
        LiveActivityToken::ENV_SANDBOX,
        LiveActivityToken::ENV_PRODUCTION,
    ];

    public function register(Request $request, string $id): JsonResponse
    {
        Incident::whereFireId($id)->firstOrFail();

        $pushToken = (string) $request->post('pushToken', '');
        $env       = (string) $request->post('env', '');

        if (!$this->isValidPushToken($pushToken)) {
            return $this->error('invalid_push_token');
        }
        if (!in_array($env, self::VALID_ENVS, true)) {
            return $this->error('invalid_env');
        }

        $token = LiveActivityToken::updateOrCreate(
            ['fire_id' => $id, 'push_token' => $pushToken],
            ['env' => $env]
        );
        $token->touch();

        return new JsonResponse(['success' => true]);
    }

    public function unregister(Request $request, string $id): JsonResponse
    {
        $pushToken = (string) $request->post('pushToken', '');

        if (!$this->isValidPushToken($pushToken)) {
            return $this->error('invalid_push_token');
        }

        LiveActivityToken::where('fire_id', $id)
            ->where('push_token', $pushToken)
            ->delete();

        return new JsonResponse(['success' => true]);
    }

    private function isValidPushToken(string $token): bool
    {
        return strlen($token) >= 64 && ctype_xdigit($token);
    }

    private function error(string $code): JsonResponse
    {
        return new JsonResponse(['success' => false, 'error' => $code], 422);
    }
}
