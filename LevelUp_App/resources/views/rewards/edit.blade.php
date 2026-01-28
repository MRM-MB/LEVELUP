@extends('layouts.app')

@section('title', 'Edit Reward')

@section('content')
<div class="container">
    <h1>Edit Reward</h1>

    <form action="{{ route('rewards.update', $reward->id) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        
        <div class="form-group">
            <label for="card_name">Reward Name</label>
            <input type="text" id="card_name" name="card_name" value="{{ $reward->card_name }}" required class="form-control">
            @error('card_name') <span class="text-danger">{{ $message }}</span> @enderror
        </div>

        <div class="form-group">
            <label for="points_amount">Points Required</label>
            <input type="number" id="points_amount" name="points_amount" value="{{ $reward->points_amount }}" required min="0" class="form-control">
            @error('points_amount') <span class="text-danger">{{ $message }}</span> @enderror
        </div>

        <div class="form-group">
            <label for="card_description">Description</label>
            <textarea id="card_description" name="card_description" required class="form-control">{{ $reward->card_description }}</textarea>
            @error('card_description') <span class="text-danger">{{ $message }}</span> @enderror
        </div>

        <div class="form-group">
            <label for="card_image">Image</label>
            @if($reward->card_image)
                <div>
                    <img src="{{ asset('storage/' . $reward->card_image) }}" alt="{{ $reward->card_name }}" style="max-width: 200px;">
                </div>
            @endif
            <input type="file" id="card_image" name="card_image" accept="image/*" class="form-control">
            @error('card_image') <span class="text-danger">{{ $message }}</span> @enderror
        </div>

        <button type="submit" class="btn btn-primary">Update Reward</button>
        <a href="{{ route('rewards.index') }}" class="btn btn-secondary">Cancel</a>
    </form>
</div>
@endsection