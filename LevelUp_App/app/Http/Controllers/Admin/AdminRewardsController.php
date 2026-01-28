<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reward;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class AdminRewardsController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'card_name'         => ['required', 'string', 'max:255', Rule::unique('rewards_catalog')],
            'points_amount'     => ['required', 'integer', 'min:0'],
            'card_description'  => ['nullable', 'string', 'max:1000'],
            'card_image'        => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
        ], [
            'card_name.required' => 'The reward name is required.',
            'points_amount.required' => 'The points amount is required.',
        ]);

        if ($request->hasFile('card_image')) {
            $imagePath = $request->file('card_image')->store('images/giftcards', 'public');
            $data['card_image'] = $imagePath;
        }
        else {
            $data['card_image'] = 'images/giftcards/placeholder.png'; 
        }

        $data['archived'] = false;

        Reward::create($data);

        return back()->with('success', 'Reward created successfully.');
    }

    public function update(Request $request, Reward $reward): RedirectResponse
    {
        $data = $request->validate([
            'card_name'         => ['required', 'string', 'max:255', Rule::unique('rewards_catalog')->ignore($reward->id)],
            'points_amount'     => ['required', 'integer', 'min:0'],
            'card_description'  => ['nullable', 'string', 'max:1000'],
            'card_image'        => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
        ]);

        if ($request->hasFile('card_image')) {
            // Delete old image if exists
            if ($reward->card_image) {
                Storage::disk('public')->delete($reward->card_image);
            }
            $imagePath = $request->file('card_image')->store('images/giftcards', 'public');
            $data['card_image'] = $imagePath;
        }

        // Archive status is handled only via archive()/unarchive()
        $reward->update($data);

        return redirect()->route('admin.dashboard', array_filter([
            'q' => $request->get('q'),
        ]))->with('success', 'Reward updated successfully.');
    }

    public function archive(Reward $reward): RedirectResponse
    {
        if ($reward->archived === true) {
            return back()->with('info', 'This reward is already archived.');
        }

        $reward->update(['archived' => true]);

        return back()->with('success', "Reward '{$reward->card_name}' has been archived.");
    }

    public function unarchive(Reward $reward): RedirectResponse
    {
        if ($reward->archived === false) {
            return back()->with('info', 'This reward is already active.');
        }

        $reward->update(['archived' => false]);

        return back()->with('success', "Reward '{$reward->card_name}' has been unarchived.");
    }

    public function destroy(Reward $reward): RedirectResponse
    {
        // Check if reward is archived
        if (!$reward->archived) {
            return back()->with('error', 'Only archived rewards can be deleted.');
        }

        // Delete favorite relationships
        $reward->favoritedBy()->detach();

        // Delete image file if exists
        if ($reward->card_image) {
            Storage::disk('public')->delete($reward->card_image);
        }

        $reward->delete();

        return back()->with('success', 'Reward deleted successfully.');
    }
}