<?php

declare(strict_types=1);

namespace App\Features\Promotion\Controllers;

use App\Core\Middleware\AuthMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class AdminPromotionController
{
    protected PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
    }

    protected function requireAdmin(Request $request): ?Response
    {
        $auth = AuthMiddleware::authenticate($request);
        if ($auth instanceof Response) {
            return $auth;
        }

        if (strtoupper((string) ($auth['role_code'] ?? '')) !== 'ADMIN') {
            return Response::fail('Chỉ Quản Trị Viên mới có quyền thực hiện thao tác này', 'FORBIDDEN', 403);
        }

        return null;
    }

    /**
     * GET /api/v1/admin/promotions
     */
    public function index(Request $request): Response
    {
        $check = $this->requireAdmin($request);
        if ($check !== null) return $check;

        $stmt = $this->pdo->query(
            'SELECT `id`, `code`, `name`, `promotion_type`, `discount_type`, `discount_value`,
                    `start_at`, `end_at`, `rules`, `is_active`, `created_at`, `updated_at`
             FROM `promotions`
             ORDER BY `id` DESC'
        );
        $promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($promotions as &$p) {
            if (!empty($p['rules']) && is_string($p['rules'])) {
                $p['rules'] = json_decode($p['rules'], true);
            }
        }

        return Response::success([
            'promotions' => $promotions,
            'count' => count($promotions),
        ], 'Danh sách toàn bộ chương trình khuyến mãi (Admin)');
    }

    /**
     * POST /api/v1/admin/promotions
     */
    public function create(Request $request): Response
    {
        $check = $this->requireAdmin($request);
        if ($check !== null) return $check;

        $body = $request->getBodyParams();
        $code = strtoupper(trim((string) ($body['code'] ?? '')));
        $name = trim((string) ($body['name'] ?? ''));
        $promotionType = strtoupper(trim((string) ($body['promotion_type'] ?? 'DISCOUNT')));
        $rawDiscountType = strtoupper(trim((string) ($body['discount_type'] ?? 'FIXED')));
        $discountType = (str_contains($rawDiscountType, 'PERCENT')) ? 'PERCENT' : 'FIXED';
        $discountValue = max(0, (int) ($body['discount_value'] ?? 0));
        $startAt = !empty($body['start_at']) ? trim((string) $body['start_at']) : date('Y-m-d H:i:s');
        $endAt = !empty($body['end_at']) ? trim((string) $body['end_at']) : date('Y-m-d H:i:s', strtotime('+30 days'));
        $rules = isset($body['rules']) ? (is_array($body['rules']) ? json_encode($body['rules']) : (string) $body['rules']) : null;
        $isActive = isset($body['is_active']) ? (int) (bool) $body['is_active'] : 1;

        if ($code === '' || $name === '') {
            return Response::fail('Mã và tên khuyến mãi không được để trống', 'VALIDATION_ERROR', 422);
        }

        // Kiểm tra trùng code
        $chk = $this->pdo->prepare('SELECT `id` FROM `promotions` WHERE `code` = :code LIMIT 1');
        $chk->bindValue(':code', $code, PDO::PARAM_STR);
        $chk->execute();
        if ($chk->fetchColumn() !== false) {
            return Response::fail('Mã khuyến mãi này đã tồn tại trên hệ thống', 'DUPLICATE_CODE', 409);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO `promotions` (`code`, `name`, `promotion_type`, `discount_type`, `discount_value`,
                                      `start_at`, `end_at`, `rules`, `is_active`, `created_at`, `updated_at`)
             VALUES (:code, :name, :pt, :dt, :dv, :start, :end, :rules, :active, NOW(), NOW())'
        );
        $stmt->bindValue(':code', $code, PDO::PARAM_STR);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':pt', $promotionType, PDO::PARAM_STR);
        $stmt->bindValue(':dt', $discountType, PDO::PARAM_STR);
        $stmt->bindValue(':dv', $discountValue, PDO::PARAM_INT);
        $stmt->bindValue(':start', $startAt, PDO::PARAM_STR);
        $stmt->bindValue(':end', $endAt, PDO::PARAM_STR);
        $stmt->bindValue(':rules', $rules, $rules === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':active', $isActive, PDO::PARAM_INT);
        $stmt->execute();

        $promoId = (int) $this->pdo->lastInsertId();

        return Response::success(['promotion_id' => $promoId], 'Tạo khuyến mãi mới thành công', null, 201);
    }

    /**
     * PATCH /api/v1/admin/promotions/{id}
     */
    public function update(Request $request, string $id): Response
    {
        $check = $this->requireAdmin($request);
        if ($check !== null) return $check;

        $promoId = (int) $id;
        $body = $request->getBodyParams();

        $allowed = ['name', 'discount_type', 'discount_value', 'start_at', 'end_at', 'rules', 'is_active'];
        $sets = [];
        $params = [':id' => $promoId];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $body)) {
                $val = $body[$col];
                if ($col === 'discount_value') {
                    $val = (int) $val;
                } elseif ($col === 'discount_type') {
                    $val = str_contains(strtoupper((string) $val), 'PERCENT') ? 'PERCENT' : 'FIXED';
                } elseif ($col === 'is_active') {
                    $val = (int) (bool) $val;
                } elseif ($col === 'rules' && is_array($val)) {
                    $val = json_encode($val);
                }
                $sets[] = "`{$col}` = :{$col}";
                $params[":{$col}"] = $val;
            }
        }

        if (empty($sets)) {
            return Response::fail('Không có dữ liệu cập nhật', 'VALIDATION_ERROR', 422);
        }

        $sets[] = '`updated_at` = NOW()';
        $stmt = $this->pdo->prepare('UPDATE `promotions` SET ' . implode(', ', $sets) . ' WHERE `id` = :id');
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return Response::success(['promotion_id' => $promoId], 'Cập nhật khuyến mãi thành công');
    }

    /**
     * DELETE /api/v1/admin/promotions/{id}
     */
    public function delete(Request $request, string $id): Response
    {
        $check = $this->requireAdmin($request);
        if ($check !== null) return $check;

        $promoId = (int) $id;
        $stmt = $this->pdo->prepare('UPDATE `promotions` SET `is_active` = 0, `updated_at` = NOW() WHERE `id` = :id');
        $stmt->bindValue(':id', $promoId, PDO::PARAM_INT);
        $stmt->execute();

        return Response::success(['promotion_id' => $promoId], 'Đã ngưng kích hoạt khuyến mãi');
    }
}
