<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token === null || $token === '') {
            return $this->unauthenticated();
        }

        $user = User::query()
            ->with(['role', 'employee'])
            ->where('remember_token', hash('sha256', $token))
            ->first();

        if ($user === null || $user->status !== 'active') {
            return $this->unauthenticated();
        }

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }

    private function unauthenticated(): JsonResponse
    {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }
}
