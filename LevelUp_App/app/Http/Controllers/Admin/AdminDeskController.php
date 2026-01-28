<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Desk;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Services\Wifi2BleSimulatorClient;
use Throwable;

class AdminDeskController extends Controller
{
    public function __construct(private readonly Wifi2BleSimulatorClient $simClient)
    {
    }

    /**
     * Store one or more simulator desks into our DB so we can manage them.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'       => ['nullable', 'string', 'max:255'],
            'desk_ids'   => ['required', 'array', 'min:1'],
            'desk_ids.*' => ['string', 'max:255'],
        ]);

        $alreadyManaged = Desk::whereIn('serial_number', $data['desk_ids'])
            ->pluck('serial_number')
            ->all();

        if (!empty($alreadyManaged)) {
            return back()
                ->withInput()
                ->with('error', 'These desks are already managed: ' . implode(', ', $alreadyManaged));
        }

        foreach ($data['desk_ids'] as $deskId) {
            Desk::create([
                'name'          => $data['name'] ?? null,
                'desk_model'    => 'Linak Desk',
                'serial_number' => $deskId,
            ]);
        }

        $count = count($data['desk_ids']);
        
        return back()->with('success',sprintf('%d desk(s) added to management successfully.', $count));
    }

    /**
     * Update only local metadata (name + cleaning height).
     */
    public function update(Request $request, Desk $desk): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $desk->name = $data['name'] ?? null;
        $desk->save();

        return back()->with('success', 'Desk updated successfully.');
    }

    /**
     * Remove a desk from our DB (simulator desk itself remains).
     */
    public function destroy(Desk $desk): RedirectResponse
    {
        // Check if any user is using this desk
        $hasUsers = User::where('desk_id', $desk->id)->exists();

        if ($hasUsers) {
            return back()->with('error', 'This desk is assigned to one or more users and cannot be deleted.');
        }

        $desk->delete();

        return back()
            ->with('success', 'Desk removed from management (simulator desk is unchanged).');
    }

    public function bulkHeight(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'desk_ids'   => ['required', 'array', 'min:1'],
            'desk_ids.*' => ['integer', 'exists:desks,id'],
            'height_cm'  => ['required', 'integer', 'between:60,130'],
        ]);

        $positionMm = $data['height_cm'] * 10;

        $desks  = Desk::whereIn('id', $data['desk_ids'])->get();
        $failed = [];

        foreach ($desks as $desk) {
            try {
                // Call the simulator API: PUT /desks/{id}/state { position_mm: ... }
                $this->simClient->updateDeskState($desk->serial_number, [
                    'position_mm' => $positionMm,
                ]);
            } catch (Throwable $e) {
                $failed[] = $desk->serial_number;
            }
        }

        if (!empty($failed)) {
            return back()->with('error',
                'Some desks could not be moved: ' . implode(', ', $failed)
            );
        }

        return back()->with('success',
            sprintf('Moved %d desk(s) to %d cm.', $desks->count(), $data['height_cm'])
        );
    }
}
