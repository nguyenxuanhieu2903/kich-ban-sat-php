<?php

declare(strict_types=1);

namespace App\Features\Auth;

use App\Core\Request;
use App\Core\Response;

class AuthController
{
    private AuthService $service;

    public function __construct(?AuthService $service = null)
    {
        $this->service = $service ?? new AuthService();
    }

    public function register(Request $request): Response
    {
        $user = $this->service->register($request->getBodyParams());

        return Response::json([
            'success' => true,
            'message' => 'Đăng ký tài khoản thành công',
            'data' => $user,
            'serverTime' => date('c'),
        ], 201);
    }

    public function login(Request $request): Response
    {
        $user = $this->service->login($request->getBodyParams());

        return Response::json([
            'success' => true,
            'message' => 'Đăng nhập thành công',
            'data' => $user,
            'serverTime' => date('c'),
        ]);
    }

    public function logout(Request $request): Response
    {
        $this->service->logout();

        return Response::json([
            'success' => true,
            'message' => 'Đăng xuất thành công',
            'serverTime' => date('c'),
        ]);
    }

    public function me(Request $request): Response
    {
        return Response::json([
            'success' => true,
            'data' => $this->service->me(),
            'serverTime' => date('c'),
        ]);
    }
}