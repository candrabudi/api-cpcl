<?php

namespace App\Http\Middleware;

class CorsMiddleware
{
    public function handle($request, \Closure $next)
    {
        if ($request->getMethod() === 'OPTIONS') {
            return response()->json([], 204)->withHeaders($this->headers());
        }

        $response = $next($request);

        foreach ($this->headers() as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }

    protected function headers()
    {
        return [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Origin, Content-Type, Accept, Authorization, X-Requested-With',
        ];
    }
}
