<?php

namespace App\Extensions\Chatbot\System\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LanguageMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $language = $request->get('language') ?: app()->getLocale();

        app()->setLocale($language);

        return $next($request);
    }
}
