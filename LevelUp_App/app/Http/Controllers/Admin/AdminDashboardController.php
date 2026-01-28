<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Reward;
use App\Models\Desk;
use App\Services\Wifi2BleSimulatorClient;
use Illuminate\Http\Request;
use Throwable;

class AdminDashboardController extends Controller
{
    public function __construct(
        private readonly Wifi2BleSimulatorClient $simClient
    ) {}

    public function index(Request $request, Wifi2BleSimulatorClient $simClient)
    {
        if (!$request->has('tab')) {
            return redirect()->route('admin.dashboard', ['tab' => 'desks']);
        }

        $tab = $request->query('tab');

        // ----- USERS TAB -----
        $q        = trim($request->get('q', ''));
        $editId   = $request->integer('edit');
        $editUser = $editId ? User::find($editId) : null;

        $users = User::query()
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('surname', 'like', "%{$q}%")
                        ->orWhere('username', 'like', "%{$q}%");
                });
            })
            ->orderBy('name')
            ->orderBy('surname')
            ->orderBy('username')
            ->paginate(15)
            ->withQueryString();

        $deskOptions = collect();
        $deskLimits = []; // [desk_id => ['min' => 60, 'max' => 130]]

        if ($tab === 'users') {
            $deskOptions = Desk::orderBy('name')
                ->orderBy('serial_number')
                ->get();
            
            foreach ($deskOptions as $d) {
                try {
                    $data = $simClient->getDesk($d->serial_number);
                    $deskLimits[$d->id] = [
                        'min' => isset($data['config']['min_position_mm']) ? (int) ceil($data['config']['min_position_mm'] / 10) : 60,
                        'max' => isset($data['config']['max_position_mm']) ? (int) floor($data['config']['max_position_mm'] / 10) : 130,
                    ];
                } catch (Throwable $e) {
                    $deskLimits[$d->id] = ['min' => 60, 'max' => 130];
                }
            }
        }
        
        // ----- REWARDS TAB -----
        $activeRewards   = Reward::where('archived', false)->orderBy('card_name')->get();
        $archivedRewards = Reward::where('archived', true)->orderBy('card_name')->get();
        $editRewardId    = $request->integer('edit_reward');
        $editReward      = $editRewardId ? Reward::find($editRewardId) : null;

        // --- Average statistics for all users ---
        $avgSitting = \App\Models\HealthCycle::avg('sitting_minutes');
        $avgStanding = \App\Models\HealthCycle::avg('standing_minutes');
        $totalUsers = \App\Models\User::count();

        // ---------- DESKS & CLEANING TAB DATA ----------
        $desks            = collect();
        $availableDeskIds = [];
        $editDesk         = null;
        $deskStates       = [];
        $allManagedDesks  = collect();

        if (in_array($tab, ['desks', 'desk-cleaning'], true)) {
            $allManagedDesks = Desk::orderBy('name')
                ->orderBy('serial_number')
                ->get();

            if ($tab === 'desks') {
                $deskSearch = trim($request->get('q', ''));

                $deskQuery = Desk::query();
                if ($deskSearch !== '') {
                    $deskQuery->where(function ($q) use ($deskSearch) {
                        $q->where('name', 'like', "%{$deskSearch}%")
                          ->orWhere('serial_number', 'like', "%{$deskSearch}%");
                    });
                }

                $desks = $deskQuery
                    ->orderBy('name')
                    ->orderBy('serial_number')
                    ->paginate(10) // showing only the first 10 desks per page
                    ->withQueryString();

                $managedSerials = $allManagedDesks->pluck('serial_number')->all();

                // available simulator desks (not yet registered)
                try {
                    $simIds           = $simClient->listDesks();
                    $availableDeskIds = array_values(array_diff($simIds, $managedSerials));
                } catch (Throwable $e) {
                    $availableDeskIds = [];
                }

                // currently edited desk
                $editDeskId = $request->integer('edit_desk');
                $editDesk   = $editDeskId ? Desk::find($editDeskId) : null;
            }

            // fetch config/state for each managed desk
            foreach ($allManagedDesks as $desk) {
                try {
                    $data = $simClient->getDesk($desk->serial_number);

                    $deskStates[$desk->serial_number] = [
                        'config_name' => data_get($data, 'config.name'),
                        'position_cm' => isset($data['state']['position_mm'])
                            ? (int) round($data['state']['position_mm'] / 10)
                            : null,
                        'status'      => data_get($data, 'state.status'),
                        'min_cm'      => isset($data['config']['min_position_mm']) 
                            ? (int) ceil($data['config']['min_position_mm'] / 10) 
                            : 60,
                        'max_cm'      => isset($data['config']['max_position_mm']) 
                            ? (int) floor($data['config']['max_position_mm'] / 10) 
                            : 130,
                    ];
                } catch (Throwable $e) {
                    $deskStates[$desk->serial_number] = [
                        'config_name' => null,
                        'position_cm' => null,
                        'status'      => 'Unavailable',
                        'min_cm'      => 60,
                        'max_cm'      => 130,
                    ];
                }
            }
        }

        return view('admin.dashboard', compact(
            'users',
            'q',
            'editUser',
            'activeRewards',
            'archivedRewards',
            'editReward',
            'desks',
            'availableDeskIds',
            'editDesk',
            'deskStates',
            'allManagedDesks',
            'deskOptions',
            'deskLimits',
            'avgSitting',
            'avgStanding',
            'totalUsers'
        ));
    }
}