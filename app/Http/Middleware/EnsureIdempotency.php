<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\IdempotencyKey;
use Exception;

class EnsureIdempotency
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->input('idempotency_key') ?? $request->header('Idempotency-Key');
        if (!$key) {
            return response()->json(['message' => 'Idempotency key required'], 422);
        }

        $record = IdempotencyKey::firstOrCreate(['key' => $key]);
        // if response exists, return it.
        if (!empty($record->response)) {
            return response()->json($record->response, 200);
        }

        // attach the record to the request
        $request->attributes->set('idempotency_record', $record);

        $response = $next($request);

        // store response body and request hash
        try {
            $payload = null;
            if ($response instanceof Response || method_exists($response, 'getContent')) {
                $content = $response->getContent();
                $decoded = json_decode($content, true);
                $payload = $decoded ?? ['raw' => $content];
            }
            $record->update([
                'request_hash' => sha1(json_encode($request->all())),
                'response' => $payload,
            ]);
        } catch (Exception $error) {
            logger('Failed storing idempotency response: ' . $error->getMessage());
        }

        return $response;
    }
}
