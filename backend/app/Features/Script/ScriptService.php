<?php

declare(strict_types=1);

namespace App\Features\Script;

use App\Core\Exceptions\NotFoundException;

class ScriptService
{
    protected ScriptRepository $repository;

    public function __construct(?ScriptRepository $repository = null)
    {
        $this->repository = $repository ?? new ScriptRepository();
    }

    /**
     * Lấy danh sách toàn bộ kịch bản và chuẩn hóa kiểu dữ liệu số.
     *
     * @param array{genre?: ?string, players?: ?int} $filters
     * @return array<int, array<string, mixed>>
     */
    public function getAllScripts(array $filters = []): array
    {
        $rows = $this->repository->findAll($filters);

        return array_map([$this, 'formatScript'], $rows);
    }

    /**
     * Lấy chi tiết một kịch bản theo ID.
     *
     * @throws NotFoundException Nếu kịch bản không tồn tại.
     * @return array<string, mixed>
     */
    public function getScriptById(int $id): array
    {
        $script = $this->repository->findById($id);

        if ($script === null) {
            throw new NotFoundException("Kịch bản với ID #{$id} không tồn tại hoặc đã ngừng hoạt động.");
        }

        return $this->formatScript($script);
    }

    /**
     * Chuẩn hóa và ép kiểu các trường dữ liệu kịch bản.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function formatScript(array $data): array
    {
        return [
            'id' => (int) $data['id'],
            'code' => (string) $data['code'],
            'title' => (string) $data['title'],
            'genre' => (string) $data['genre'],
            'description' => (string) $data['description'],
            'min_players' => (int) $data['min_players'],
            'max_players' => (int) $data['max_players'],
            'male_players' => isset($data['male_players']) ? (int) $data['male_players'] : null,
            'female_players' => isset($data['female_players']) ? (int) $data['female_players'] : null,
            'duration_minutes' => (int) $data['duration_minutes'],
            'price_per_player' => (float) $data['price_per_player'],
            'difficulty' => (int) $data['difficulty'],
            'cover_image' => $data['cover_image'] ?? null,
            'status' => (string) $data['status'],
            'created_at' => $data['created_at'] ?? null,
            'updated_at' => $data['updated_at'] ?? null,
        ];
    }
}
