<?php

declare(strict_types=1);

namespace App\Features\Game;

use App\Core\Exceptions\BadRequestException;
use App\Core\Exceptions\HttpException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Request;
use App\Core\Response;

class GameController
{
    private GameRepository $repo;

    public function __construct(?GameRepository $repo = null)
    {
        $this->repo = $repo ?? new GameRepository();
    }

    public function index(Request $request): Response
    {
        $games = $this->repo->findAll([
            'q' => $request->query('q'),
            'category' => $request->query('category'),
            'is_18_plus' => $request->query('is_18_plus'),
        ]);

        return Response::json([
            'success' => true,
            'data' => $games,
            'serverTime' => date('c'),
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $game = $this->repo->findById((int) $id);
        if ($game === null) {
            throw new NotFoundException('Không tìm thấy kịch bản #' . $id);
        }

        return Response::json([
            'success' => true,
            'data' => $game,
            'serverTime' => date('c'),
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requireAdmin();

        $body = $request->getBodyParams();
        $errors = [];

        if (empty($body['title'])) {
            $errors['title'][] = 'Tên kịch bản không được để trống';
        }
        if (empty($body['min_players']) || empty($body['max_players']) || (int) $body['min_players'] > (int) $body['max_players']) {
            $errors['players'][] = 'Số người chơi tối thiểu phải nhỏ hơn hoặc bằng tối đa';
        }
        if (empty($body['price_day_vnd']) || empty($body['price_evening_vnd'])) {
            $errors['price'][] = 'Phải nhập đầy đủ giá khung ngày và khung tối (VND)';
        }

        if (!empty($errors)) {
            throw new BadRequestException('Dữ liệu tạo kịch bản không hợp lệ', $errors);
        }

        $slug = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', (string) $body['title']), '-')) . '-' . time();

        $id = $this->repo->create([
            'title' => trim((string) $body['title']),
            'slug' => $slug,
            'category' => trim((string) ($body['category'] ?? 'Trinh thám')),
            'description' => $body['description'] ?? null,
            'min_players' => (int) $body['min_players'],
            'max_players' => (int) $body['max_players'],
            'duration_minutes' => (int) ($body['duration_minutes'] ?? 210),
            'is_18_plus' => !empty($body['is_18_plus']),
            'price_day_vnd' => (int) $body['price_day_vnd'],
            'price_evening_vnd' => (int) $body['price_evening_vnd'],
        ]);

        return Response::json([
            'success' => true,
            'message' => 'Tạo kịch bản thành công',
            'data' => $this->repo->findById($id),
        ], 201);
    }

    private function requireAdmin(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $role = strtoupper((string) ($_SESSION['role'] ?? ''));

        if ($userId <= 0 || $role === '') {
            throw new HttpException('Bạn cần đăng nhập để thực hiện thao tác này', 401);
        }

        if ($role !== 'ADMIN') {
            throw new HttpException('Chỉ quản trị viên mới có quyền tạo kịch bản', 403);
        }
    }
}