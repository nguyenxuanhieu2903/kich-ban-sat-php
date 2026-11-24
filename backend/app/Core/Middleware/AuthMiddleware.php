<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Features\Identity\Repositories\UserRepository;
use App\Infrastructure\Database\DatabaseConnection;

class AuthMiddleware implements MiddlewareInterface
{
    /** @var string[] */
    protected array $allowedRoles;
    protected UserRepository $userRepository;

    /**
     * @param string[] $allowedRoles
     */
    public function __construct(array $allowedRoles = [], ?UserRepository $userRepository = null)
    {
        $this->allowedRoles = $allowedRoles;
        $this->userRepository = $userRepository ?? new UserRepository(DatabaseConnection::getInstance());
    }

    /**
     * Authenticate request and check role permissions.
     * Returns the user array on success, or a Response (401/403) on failure.
     *
     * @param string[] $allowedRoles
     * @return array<string, mixed>|Response
     */
    public static function authenticate(Request $request, array $allowedRoles = []): array|Response
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            $preSession = $_SESSION ?? [];
            @session_start();
            if (!empty($preSession) && empty($_SESSION)) {
                $_SESSION = $preSession;
            }
        }

        $userId = null;
        if (!empty($_SESSION['user_id'])) {
            $userId = (int) $_SESSION['user_id'];
        }

        if ($userId === null || $userId <= 0) {
            return Response::fail(
                'Unauthorized - Please log in to continue',
                'UNAUTHORIZED',
                401
            );
        }

        $userRepo = new UserRepository(DatabaseConnection::getInstance());
        $user = $userRepo->findById($userId);

        if ($user === null) {
            return Response::fail(
                'User not found or account deactivated',
                'UNAUTHORIZED',
                401
            );
        }

        if (($user['status'] ?? 'ACTIVE') !== 'ACTIVE') {
            return Response::fail(
                'Account is suspended or inactive',
                'FORBIDDEN',
                403
            );
        }

        // RBAC check
        if (!empty($allowedRoles)) {
            $userRoleCode = strtoupper((string) ($user['role_code'] ?? 'PLAYER'));
            $allowedUpper = array_map('strtoupper', $allowedRoles);

            if (!in_array($userRoleCode, $allowedUpper, true)) {
                return Response::fail(
                    'Forbidden - You do not have permission to access this resource',
                    'FORBIDDEN',
                    403
                );
            }
        }

        return $user;
    }

    public function handle(Request $request, callable $next): Response
    {
        $result = self::authenticate($request, $this->allowedRoles);
        if ($result instanceof Response) {
            return $result;
        }

        return $next($request);
    }
}
