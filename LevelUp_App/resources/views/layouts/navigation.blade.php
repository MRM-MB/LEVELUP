<header>
    <nav class="modern-nav">
        <!-- NAVBAR for all the pages -->
        <div class="nav-bubble-container">
            <div class="nav-gradient-container">
                <div class="nav-g1"></div>
                <div class="nav-g2"></div>
                <div class="nav-g3"></div>
                <div class="nav-g4"></div>
                <div class="nav-g5"></div>
                <div class="nav-g6"></div>
                <div class="nav-g7"></div>
                <div class="nav-g8"></div>
                <div class="nav-g9"></div>
                <div class="nav-g10"></div>
                <div class="nav-g11"></div>
                <div class="nav-g12"></div>
                <div class="nav-g13"></div>
                <div class="nav-g14"></div>
                <div class="nav-g15"></div>
            </div>
        </div>
        
        <div class="nav-container">
            <!-- Logo -->
            <div class="nav-logo">
                <a href="{{ url('/') }}" aria-label="Home">
                    <img src="{{ asset('nav-logo.png') }}" alt="LevelUp Logo" class="nav-logo-img">
                </a>
            </div>
            
            <!-- Navigation Links -->
            <div class="nav-links">
                <a href="{{ url('/') }}" class="nav-link {{ request()->is('/') ? 'active' : '' }}">
                    <i class="fa-solid fa-house"></i>
                    Home
                </a>
                
                {{-- Only show these links when user is logged in --}}
                @auth
                    <a href="{{ url('/statistics') }}" class="nav-link {{ request()->is('statistics*') ? 'active' : '' }}">
                        <i class="fas fa-chart-bar"></i>
                        Statistics
                    </a>
                    <a href="{{ url('/rewards') }}" class="nav-link {{ request()->is('rewards*') ? 'active' : '' }}">
                        <i class="fas fa-trophy"></i>
                        Rewards
                    </a>
                    {{-- Control Dashboard (only for admin) --}}
                    @if(Auth::check() && Auth::user()->role === 'admin')
                        <a href="{{ route('admin.dashboard', ['tab' => 'desks']) }}" 
                        class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                            <i class="fa-solid fa-crown"></i>
                            Control Dashboard
                        </a>
                    @endif
                @endauth
            </div>

            <!-- Enhanced User Actions -->
            <div class="nav-actions">
                {{-- Only show points for logged-in users --}}
                @auth
                    <!-- Simple Blue Points Display -->
                    <div class="points-display">
                        <div>
                            <i class="fas fa-star"></i>
                            <span class="points-number" id="totalPoints" data-total-points="{{ Auth::user()->total_points ?? 0 }}">
                                {{ Auth::user()->total_points ?? 0 }}
                            </span>
                            <span class="points-label">Points</span>
                        </div>
                    </div>
                @endauth
                @auth
                    <a href="{{ url('/profile') }}" class="nav-link {{ request()->is('profile*') ? 'active' : '' }}">
                        <i class="fa-solid fa-user"></i>
                        Profile
                    </a>

                    <form action="{{ route('logout') }}" method="POST" style="display:inline;" id="logout-form">
                        @csrf
                        <button type="submit" class="login-btn" style="border:none;">
                            <i class="fa-solid fa-right-from-bracket"></i> Logout
                        </button>
                    </form>
                    <script>
                        // Clear timer state before form submission for reliable reset
                        document.getElementById('logout-form').addEventListener('submit', function() {
                            localStorage.removeItem('levelup_timer_state');
                        });
                    </script>
                @endauth

                {{-- Show only when not logged in --}}
                @guest
                    <a href="{{ route('login') }}" class="login-btn {{ request()->routeIs('login') ? 'active' : '' }}">
                        Log in
                    </a>
                @endguest
            </div>
        </div>
    </nav>
</header>
