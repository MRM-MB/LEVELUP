@extends('layouts.app')

@section('title', 'Profile')

@section('content')
    <main class="profile-container">
        
        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-error">
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <header class="page-header">
            <h1> <span>My Profile</span></h1>
            <p class="page-subtitle">View your personal info and table preferences</p>
        </header>

        <div class="profile-card">

            <div class="profile-header">
                <img id="userPhoto" src="{{ asset('images/users/default.jpg') }}" class="profile-avatar">

                <div class="profile-ident">
                    <div class="profile-handle">{{ '@' . ($user->username ?? 'unknown') }}</div>
                </div>
            </div>

            <dl class="profile-dl">
                <dt>Full Name</dt>
                <dd>{{ $user->name }}{{ $user->surname ? ' ' . $user->surname : '' }}</dd>
                <dt>Date of Birth</dt>
                <dd>{{ $user->date_of_birth ? $user->date_of_birth->format('F j, Y') : 'Not set' }}</dd>
            </dl>
        </div>

        <div class="profile-card">
            <div class="table-settings">
                <h2>Desk Info</h2>

                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <div style="display: inline-flex; gap: 2rem;">
                        <div>
                            <span style="color: var(--color-accent); font-size: 0.875rem; font-weight: 600; text-transform: uppercase; 
      letter-spacing: 0.5px;">Desk ID</span>
                            <div style="margin-top: 0.25rem; font-size: 1rem; color: black;">
                                {{ $user->desk_id ?? 'Not assigned' }}
                            </div>
                        </div>

                        @if($user->desk)
                                                <div>
                                                    <span style="color: var(--color-accent); font-size: 0.875rem; font-weight: 600; text-transform: uppercase; 
                              letter-spacing: 0.5px;">Desk Model</span>
                                                    <div style="margin-top: 0.25rem; font-size: 1rem; color: black;">
                                                        {{ $user->desk->desk_model }}
                                                    </div>
                                                </div>

                                                <div>
                                                    <span style="color: var(--color-accent); font-size: 0.875rem; font-weight: 600; text-transform: uppercase; 
                              letter-spacing: 0.5px;">Serial Number</span>
                                                    <div style="margin-top: 0.25rem; font-size: 1rem; color: black;">
                                                        {{ $user->desk->serial_number }}
                                                    </div>
                                                </div>
                        @endif
                    </div>
                </div>

                <form id="heightForm" action="{{ route('profile.update') }}" method="POST">
                    @csrf
                    
                    <!-- Hidden fields for required user data -->
                    <input type="hidden" name="name" value="{{ $user->name }}">
                    <input type="hidden" name="surname" value="{{ $user->surname }}">
                    <input type="hidden" name="date_of_birth" value="{{ $user->date_of_birth?->format('Y-m-d') }}">
                    
                    <div class="table-grid">
                        <div class="height-setting">
                            <label>Standing Height</label>
                            <div class="height-input">
                                <span id="standingDisplay" class="height-display">
                                    {{ $user->standing_position ?? 'Not set' }}
                                </span>
                                @if($user->standing_position)
                                    <span class="unit">cm</span>
                                @endif
                                <input type="number" 
                                       id="standingInput" 
                                       name="standing_position" 
                                       value="{{ $user->standing_position }}" 
                                       min="{{ $minHeight }}" 
                                       max="{{ $maxHeight }}" 
                                       class="height-input-field hidden">
                                <span id="standingUnit" class="unit hidden">cm</span>
                            </div>
                        </div>

                        <div class="height-setting">
                            <label>Sitting Height</label>
                            <div class="height-input">
                                <span id="sittingDisplay" class="height-display">
                                    {{ $user->sitting_position ?? 'Not set' }}
                                </span>
                                @if($user->sitting_position)
                                    <span class="unit">cm</span>
                                @endif
                                <input type="number" 
                                       id="sittingInput" 
                                       name="sitting_position" 
                                       value="{{ $user->sitting_position }}" 
                                       min="{{ $minHeight }}" 
                                       max="{{ $maxHeight }}" 
                                       class="height-input-field hidden">
                                <span id="sittingUnit" class="unit hidden">cm</span>
                            </div>
                        </div>
                    </div>

                    <div class="height-controls">
                        <button type="button" id="editHeightsBtn" class="edit-heights-btn">
                            ✏️ Edit Heights
                        </button>
                        <div id="saveControls" class="hidden">
                            <button type="submit" class="save-heights-btn">
                                💾 Save Changes
                            </button>
                            <button type="button" id="cancelBtn" class="cancel-heights-btn">
                                ❌ Cancel
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>
@endsection

@section('scripts')
    @vite('resources/js/profile.js')
@endsection