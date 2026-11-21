<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Exceptions\BadRequestException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Request;
use App\Features\Script\ScriptController;
use App\Features\Script\ScriptRepository;
use App\Features\Script\ScriptService;
use PHPUnit\Framework\TestCase;

class ScriptFeatureTest extends TestCase
{
    private array $sampleData = [
        [
            'id' => '1',
            'code' => 'KBS-01',
            'title' => 'Sương Mù Biệt Thự Cổ',
            'genre' => 'Kinh dị',
            'description' => 'Cốt truyện kinh dị...',
            'min_players' => '6',
            'max_players' => '6',
            'male_players' => '3',
            'female_players' => '3',
            'duration_minutes' => '240',
            'price_per_player' => '220000',
            'difficulty' => '4',
            'cover_image' => 'https://example.com/cover.jpg',
            'status' => 'ACTIVE',
            'created_at' => '2026-11-17 12:00:00',
            'updated_at' => '2026-11-17 12:00:00',
        ],
    ];

    public function testServiceFormatsScriptTypesProperly(): void
    {
        $mockRepo = $this->createMock(ScriptRepository::class);
        $mockRepo->method('findAll')->willReturn($this->sampleData);

        $service = new ScriptService($mockRepo);
        $result = $service->getAllScripts();

        $this->assertCount(1, $result);
        $script = $result[0];

        $this->assertSame(1, $script['id']);
        $this->assertSame('KBS-01', $script['code']);
        $this->assertSame(6, $script['min_players']);
        $this->assertSame(220000.0, $script['price_per_player']);
        $this->assertSame(4, $script['difficulty']);
    }

    public function testServiceThrowsNotFoundWhenScriptDoesNotExist(): void
    {
        $mockRepo = $this->createMock(ScriptRepository::class);
        $mockRepo->method('findById')->with(999)->willReturn(null);

        $service = new ScriptService($mockRepo);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Kịch bản với ID #999 không tồn tại');

        $service->getScriptById(999);
    }

    public function testControllerRejectsInvalidScriptId(): void
    {
        $service = new ScriptService($this->createMock(ScriptRepository::class));
        $controller = new ScriptController($service);

        $request = new Request('GET', '/api/scripts/abc');

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('ID kịch bản không hợp lệ');

        $controller->show($request, 'abc');
    }

    public function testControllerReturnsJsonForValidDetail(): void
    {
        $mockRepo = $this->createMock(ScriptRepository::class);
        $mockRepo->method('findById')->with(1)->willReturn($this->sampleData[0]);

        $service = new ScriptService($mockRepo);
        $controller = new ScriptController($service);

        $request = new Request('GET', '/api/scripts/1');
        $response = $controller->show($request, '1');

        $this->assertSame(200, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertTrue($body['success']);
        $this->assertSame('Sương Mù Biệt Thự Cổ', $body['data']['title']);
        $this->assertSame('KBS-01', $body['data']['code']);
    }
}
