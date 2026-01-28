<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Wifi2BleSimulatorClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly Wifi2BleSimulatorClient $client
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $minHeight = 60;
        $maxHeight = 130;

        if ($request->filled('desk_id')) {
            try {
                // We need to find the serial number for this desk ID
                $desk = \App\Models\Desk::find($request->desk_id);
                if ($desk) {
                    $simDesk = $this->client->getDesk($desk->serial_number);
                    if (isset($simDesk['config']['min_position_mm'])) {
                        $minHeight = ceil($simDesk['config']['min_position_mm'] / 10);
                    }
                    if (isset($simDesk['config']['max_position_mm'])) {
                        $maxHeight = floor($simDesk['config']['max_position_mm'] / 10);
                    }
                }
            } catch (\Throwable $e) {
                // Fallback
            }
        }

        $data = $request->validate([
            'name'              => ['required', 'string', 'max:100'],
            'surname'           => ['required', 'string', 'max:100'], 
            'username'          => ['required', 'string', 'max:60', Rule::unique('users', 'username')],
            'date_of_birth'     => ['nullable', 'date'],
            'password'          => [
                'required',
                'string',
                'min:16',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#^()_+\-=\[\]{};:\'"\\|,.<>\/~`]).+$/'
            ],
            'sitting_position'  => ['nullable', 'integer', "between:$minHeight,$maxHeight"],
            'standing_position' => ['nullable', 'integer', "between:$minHeight,$maxHeight"],
            'desk_id'           => ['nullable', 'integer', 'exists:desks,id'],
        ], [
            'password.min' => 'Password must be at least 16 characters long.',
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
            'password.confirmed' => 'Passwords do not match.',
        ]);

        $data['role'] = 'user';
        $data['password'] = bcrypt($data['password']);

        User::create($data);

        return back()->with('success', 'User created.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $minHeight = 60;
        $maxHeight = 130;
        $deskId = $request->has('desk_id') ? $request->input('desk_id') : $user->desk_id;

        if ($deskId) {
            try {
                $desk = \App\Models\Desk::find($deskId);
                if ($desk) {
                    $simDesk = $this->client->getDesk($desk->serial_number);
                    if (isset($simDesk['config']['min_position_mm'])) {
                        $minHeight = ceil($simDesk['config']['min_position_mm'] / 10);
                    }
                    if (isset($simDesk['config']['max_position_mm'])) {
                        $maxHeight = floor($simDesk['config']['max_position_mm'] / 10);
                    }
                }
            } catch (\Throwable $e) {
                // Fallback
            }
        }

        $data = $request->validate([
            'name'              => ['required', 'string', 'max:100'],
            'surname'           => ['required', 'string', 'max:100'],
            'username'          => ['required', 'string', 'max:60', Rule::unique('users', 'username')->ignore($user->user_id, 'user_id')],
            'date_of_birth'     => ['nullable', 'date'],
            'sitting_position'  => ['nullable', 'integer', "between:$minHeight,$maxHeight"],
            'standing_position' => ['nullable', 'integer', "between:$minHeight,$maxHeight"],
            'desk_id'           => ['nullable', 'integer', 'exists:desks,id'],
        ]);

        // Role change (from user to admin) is handled only via promote()
        // Role change (from admin to user) is handled only via demote()
        $user->update($data);

        return redirect()->route('admin.dashboard', array_filter([
            'q' => $request->get('q'),
        ]))->with('success', 'User updated.');
    }

    public function promote(User $user): RedirectResponse
    {
        if ($user->role === 'admin') {
            return back()->with('info', 'This user is already an admin.');
        }
        $user->update(['role' => 'admin']);
        return back()->with('success', "User {$user->username} promoted to admin.");
    }

    public function demote(User $user)
    {
        if (auth()->id() === $user->user_id) {
            return back()->with('error', 'You cannot demote yourself.');
        }

        if ($user->role !== 'admin') {
            return back()->with('info', 'This user is not an admin.');
        }

        $user->update(['role' => 'user']);

        return back()->with('success', "{$user->name} has been demoted to user.");
    }

    public function destroy(User $user): RedirectResponse
    {
        if (auth()->id() === $user->getKey()) {
            return back()->with('error', 'You cannot delete your own account.');
        }
        $user->delete();
        return back()->with('success', 'User deleted.');
    }
}
