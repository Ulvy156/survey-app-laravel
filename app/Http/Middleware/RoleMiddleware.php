<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  array<int, string>  $roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED, 'Unauthenticated.');
        }

        $allowedRoles = $this->normalizeRoles($roles);

        if ($allowedRoles !== [] && ! $this->userHasAllowedRole($user->role, $allowedRoles)) {
            abort(Response::HTTP_FORBIDDEN, 'You do not have permission to access this resource.');
        }

        return $next($request);
    }

    /**
     * Normalize and filter the incoming role constraints.
     *
     * @param  array<int, string>  $roles
     * @return array<int, string>
     */
    protected function normalizeRoles(array $roles): array
    {
        return array_values(array_filter(array_map(
            static fn (string $role): ?string => UserRole::tryFrom($role)?->value,
            $roles
        )));
    }

    /**
     * Determine if the authenticated user has one of the allowed roles.
     *
     * @param  mixed  $userRole
     * @param  array<int, string>  $allowedRoles
     */
    protected function userHasAllowedRole(mixed $userRole, array $allowedRoles): bool
    {
        $roleValue = $userRole instanceof UserRole ? $userRole->value : (string) $userRole;

        return in_array($roleValue, $allowedRoles, true);
    }
}
