<?php

declare(strict_types=1);

namespace App\Features\Script;

use App\Core\Exceptions\BadRequestException;
use App\Core\Request;
use App\Core\Response;

class ScriptController
{
    protected ScriptService $service;

    public function __construct(?ScriptService $service = null)
    {
        $this->service = $service ?? new ScriptService();
    }

    /**
     * GET /api/scripts
     * Trả về danh sách kịch bản (kèm bộ lọc query: ?genre=Kinh dị&players=6)
     */
    public function index(Request $request): Response
    {
        $genre = $request->query('genre');
        $players = $request->query('players');

        $filters = [];
        if (is_string($genre) && trim($genre) !== '') {
            $filters['genre'] = trim($genre);
        }
        if ($players !== null && is_numeric($players)) {
            $filters['players'] = (int) $players;
        }

        $scripts = $this->service->getAllScripts($filters);

        return Response::json([
            'success' => true,
            'data' => $scripts,
            'total' => count($scripts),
        ]);
    }

    /**
     * GET /api/scripts/{id}
     * Trả về thông tin chi tiết một kịch bản theo ID.
     */
    public function show(Request $request, string $id = ''): Response
    {
        $scriptId = $id !== '' ? $id : (string) $request->param('id', '');

        if (!filter_var($scriptId, FILTER_VALIDATE_INT) || (int) $scriptId <= 0) {
            throw new BadRequestException('ID kịch bản không hợp lệ. Vui lòng cung cấp số nguyên dương.');
        }

        $script = $this->service->getScriptById((int) $scriptId);

        return Response::json([
            'success' => true,
            'data' => $script,
        ]);
    }
}
