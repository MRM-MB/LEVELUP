@extends('layouts.app')

@section('title', 'Home')

@section('additional_css')
<style>
    .admin-contact-card {
        position: fixed;
        bottom: 80px;
        right: 40px;
        background: rgba(255, 255, 255, 0.9);
        padding: 18px 24px;
        border-radius: 16px;
        box-shadow: 0 8px 32px rgba(31, 38, 135, 0.15);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.4);
        border-left: 5px solid #7f4af1;
        max-width: 340px;
        z-index: 90;
        animation: slideInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .admin-contact-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 40px rgba(31, 38, 135, 0.2);
    }

    .icon-box {
        background: linear-gradient(135deg, #7f4af1 0%, #9670ff 100%);
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        flex-shrink: 0;
        box-shadow: 0 4px 10px rgba(127, 74, 241, 0.3);
    }

    @media (max-width: 768px) {
        .admin-contact-card {
            position: relative;
            bottom: auto;
            right: auto;
            margin: 2rem auto 0;
            width: 100%;
            max-width: 100%;
            border-left: none;
            border-top: 5px solid #7f4af1;
            background: rgba(255, 255, 255, 0.95);
        }
    }
</style>
@endsection

<!-- Main Content -->
@section('content')
    <main class="content @guest guest-background @endguest">
        <div class="welcome-container">
            @auth
                {{-- Welcome message for logged-in users --}}
                <div class="welcome-text"><span class="welcome-purple">Welcome back,</span> <span class="user-highlight">{{ auth()->user()->name }}</span></div>
                <div class="welcome-subtitle">Ready to level up your health today?</div>
            @else
                {{-- Welcome message for guests --}}
                <div class="welcome-text"><span class="welcome-purple">Welcome to</span> <span class="user-highlight">LevelUp</span></div>
                <div class="welcome-subtitle">Stand up for your health! Please log in to start tracking your progress.</div>
            @endauth
            
            <div class="github-pill-container">
                <a href="https://github.com/Lara-Ghi/LevelUp" class="github-pill" target="_blank" rel="noopener noreferrer">
                    <i class="fab fa-github"></i>
                    Made by the wonderful Group 3 - LevelUp
                </a>
            </div>
        </div>

        @guest
            <div class="admin-contact-card">
                <div style="display: flex; align-items: flex-start; gap: 16px;">
                    <div class="icon-box">
                        <i class="fas fa-user-lock" style="font-size: 1.1rem;"></i>
                    </div>
                    <div>
                        <h4 style="margin: 0 0 6px 0; color: #2d3748; font-size: 1.05rem; font-weight: 700; font-family: 'Poppins', sans-serif;">Need an account?</h4>
                        <p style="color: #64748b; font-size: 0.9rem; margin: 0; line-height: 1.5; font-weight: 500;">
                            Please contact your administrator to get your username and password.
                        </p>
                    </div>
                </div>
            </div>
        @endguest
    </main>
@endsection