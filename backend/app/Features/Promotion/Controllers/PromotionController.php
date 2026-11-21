<?php

declare(strict_types=1);

namespace App\Features\Promotion\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Features\Promotion\Repositories\PromotionRepository;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class PromotionController
{
    protected PromotionRepository $promotionRepo;
    protected PDO $pdo;

    public function __construct(?PromotionRepository $promotionRepo = null, ?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();
        $this->promotionRepo = $promotionRepo ?? new PromotionRepository($this->pdo);
    }

    /**
     * GET /api/v1/promotions
     * List active promotions.
     */
    public function index(Request $request): Response
    {
        $promotions = $this->promotionRepo->listActive();

        return Response::success([
            'promotions' => $promotions,
            'count' => count($promotions),
        ], 'Danh sách chương trình khuyến mãi');
    }
}
