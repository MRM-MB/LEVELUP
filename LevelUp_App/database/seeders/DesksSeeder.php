<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Desk;
use App\Services\Wifi2BleSimulatorClient;
use Illuminate\Support\Facades\Log;

class DesksSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Only seed if table is empty (prevents duplicates)
        if (Desk::query()->exists()) {
            return;
        }

        try {
            /** @var Wifi2BleSimulatorClient $client */
            $client = app(Wifi2BleSimulatorClient::class);

            // GET /api/v2/<api_key>/desks returns an array of IDs
            $simulatorDeskIds = $client->listDesks();
        } catch (\Throwable $e) {
            Log::error('Failed to load desks from simulator', ['exception' => $e]);
            $simulatorDeskIds = [];
        }

        // If simulator returned 0 desks → fallback to defaults
        if (count($simulatorDeskIds) === 0) {

            Log::warning('Simulator returned 0 desks. Seeding default fallback desks.');

            $fallback = [
                ['serial_number' => 'cd:fb:1a:53:fb:e6', 'name' => 'Default Desk 1'],
                ['serial_number' => 'ee:62:5b:b8:73:1d', 'name' => 'Default Desk 2'],
                ['serial_number' => '70:9e:d5:e7:8c:98', 'name' => 'Default Desk 3'],
            ];

            foreach ($fallback as $desk) {
                Desk::create([
                    'name'          => $desk['name'],
                    'desk_model'    => 'Linak Desk',
                    'serial_number' => $desk['serial_number'],
                ]);
            }

            return;
        }

        // Otherwise: register half of the simulator desks
        $totalDesks = count($simulatorDeskIds);

        $halfCount = (int) floor($totalDesks / 2);

        // If there is only 1 desk, still seed that one
        if ($halfCount === 0) {
            $halfCount = 1;
        }

        // Take the first half of the list
        $deskIdsToSeed = array_slice($simulatorDeskIds, 0, $halfCount);

        foreach ($deskIdsToSeed as $deskId) {
            Desk::create([
                'name'          => null,
                'desk_model'    => 'Linak Desk',
                'serial_number' => $deskId,
            ]);
        }
    }
}
