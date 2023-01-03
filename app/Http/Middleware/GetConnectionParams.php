<?php

namespace App\Http\Middleware;

use Session;
use Closure;

class GetConnectionParams
{
    public function handle($request, Closure $next)
    {
        $params = [
            'DOMAIN' => null,
            'member_id' => null,
            'AUTH_ID' => null,
            'REFRESH_ID' => null
        ];

        foreach ($params as $key => $value) {
            if($request->has($key)) {
                $params[$key] = $request->input($key);
                $request->session()->put($key, $params[$key]);
            } else {
                $params[$key] =  $request->session()->get($key);
            }          
        }

        $request->attributes->add(['params' => $params]);

        return $next($request);
    }
}
