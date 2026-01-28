@extends('layouts.app')

@section('title', 'Create Reward')

@section('content')
<div class="container">
    <h1>Create New Reward</h1>

    <form action="{{ route('rewards.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        
        <div class="form-group">
            <label for="card_name">Reward Name</label>
            <input type="text" id="card_name" name="card_name" required class="form-control">
            @error('card_name') <span class="text-danger">{{ $message }}</span> @enderror
        </div>

        <div class="form-group">
            <label for="points_amount">Points Required</label>
            <input type="number" id="points_amount" name="points_amount" required min="0" class="form-control">
            @error('points_amount') <span class="text-danger">{{ $message }}</span> @enderror
        </div>

        <div class="form-group">
            <label for="card_description">Description</label>
            <textarea id="card_description" name="card_description" required class="form-control"></textarea>
            @error('card_description') <span class="text-danger">{{ $message }}</span> @enderror
        </div>

        <div class="form-group">
            <label for="card_image">Image</label>
            <input type="file" id="card_image" name="card_image" accept="image/*" class="form-control">
            @error('card_image') <span class="text-danger">{{ $message }}</span> @enderror
        </div>

        <button type="submit" class="btn btn-success">Create Reward</button>
        <a href="{{ route('rewards.index') }}" class="btn btn-secondary">Cancel</a>
    </form>
</div>
@endsection