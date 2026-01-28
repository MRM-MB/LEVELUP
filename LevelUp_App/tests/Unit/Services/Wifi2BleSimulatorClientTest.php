<?php

namespace Tests\Unit\Services;

use App\Services\Wifi2BleSimulatorClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Wifi2BleSimulatorClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('wifi2ble.base_url', 'http://simulator:8000');
        config()->set('wifi2ble.api_key', 'fake-key');
        config()->set('wifi2ble.timeout', 3);
    }

    public function test_list_desks_returns_response(): void
    {
        Http::fake([
            'http://simulator:8000/api/v2/fake-key/desks' => Http::response(['desk-1'], 200),
        ]);

        $client = app(Wifi2BleSimulatorClient::class);

        $this->assertSame(['desk-1'], $client->listDesks());
    }

    public function test_get_desk_builds_endpoint(): void
    {
        Http::fake([
            'http://simulator:8000/api/v2/fake-key/desks/desk-1' => Http::response(['config' => []], 200),
        ]);

        $client = app(Wifi2BleSimulatorClient::class);

        $this->assertSame(['config' => []], $client->getDesk('desk-1'));
    }

    public function test_get_category_normalizes_name(): void
    {
        Http::fake([
            'http://simulator:8000/api/v2/fake-key/desks/desk-1/state' => Http::response(['position_mm' => 700], 200),
        ]);

        $client = app(Wifi2BleSimulatorClient::class);

        $this->assertSame(['position_mm' => 700], $client->getDeskCategory('desk-1', 'STATE'));
    }

    public function test_update_state_throws_on_error(): void
    {
        $this->expectException(RequestException::class);

        Http::fake([
            'http://simulator:8000/api/v2/fake-key/desks/desk-1/state' => Http::response(['error' => 'Desk not found'], 404),
        ]);

        $client = app(Wifi2BleSimulatorClient::class);

        $client->updateDeskState('desk-1', ['position_mm' => 500]);
    }
}
