@extends('layouts.app')

@section('title', 'Control Dashboard')

@section('additional_css')
    @vite('resources/css/rewards.css')
@endsection

@section('additional_js')
    @vite('resources/js/admin-dashboard.js')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endsection

@section('content')
  <!-- Dashboard Sub-Navigation -->
  <div class="rewards-nav"> 
    <a href="{{ route('admin.dashboard', ['tab' => 'desks']) }}"
      class="rewards-nav-link {{ request()->query('tab') === 'desks' ? 'active' : '' }}">
      <i class="fas fa-table"></i>
      Manage Desks
    </a>
    <a href="{{ route('admin.dashboard', ['tab' => 'users']) }}"
      class="rewards-nav-link {{ request()->query('tab') === 'users' ? 'active' : '' }}">
      <i class="fas fa-users"></i>
      Manage Users
    </a>
    <a href="{{ route('admin.dashboard', ['tab' => 'averages']) }}"
      class="rewards-nav-link {{ request()->query('tab') === 'averages' ? 'active' : '' }}">
      <i class="fas fa-chart-bar"></i>
      Users Statistics
    </a>
    <a href="{{ route('admin.dashboard', ['tab' => 'desk-cleaning']) }}"
      class="rewards-nav-link {{ request()->query('tab') === 'desk-cleaning' ? 'active' : '' }}">
      <i class="fas fa-broom"></i>
      Desk Cleaning
    </a>
    <a href="{{ route('admin.dashboard', ['tab' => 'rewards']) }}"
      class="rewards-nav-link {{ request()->query('tab') === 'rewards' ? 'active' : '' }}">
      <i class="fas fa-gift"></i>
      Manage Rewards
    </a>
  </div>

  <div class="auth-page admin-dashboard">
    <div class="auth-content">
      <div class="login-container">

        @foreach (['success', 'error', 'info'] as $msg)
          @if (session($msg))
            <div class="alert alert-{{ $msg === 'error' ? 'danger' : $msg }}">{{ session($msg) }}</div>
          @endif
        @endforeach
        @if ($errors->any())
          <div class="alert alert-danger">
            <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
          </div>
        @endif

        {{-- DESKS TAB --}}
        @if(request()->query('tab') === 'desks')
          @php
            $desks            = $desks ?? collect();
            $availableDeskIds = $availableDeskIds ?? [];
            $editDesk         = $editDesk ?? null;
            $deskSearch       = request('q', '');
            $deskStates       = $deskStates ?? []; // [serial => ['config_name', 'position_cm', 'status']]
          @endphp

          <div class="dashboard-grid">
            {{-- LEFT COLUMN: Desk Form --}}
            <div class="login-card">
              <div class="text section-title">
                {{ isset($editDesk) ? 'Edit Desk' : 'Register Simulator Desks' }}
              </div>

              @if(isset($editDesk))
                {{-- EDIT SINGLE DESK --}}
                <form method="POST" action="{{ route('admin.desks.update', $editDesk) }}" class="form">
                  @csrf
                  @method('PATCH')
                  <input type="hidden" name="tab" value="desks">
                  @if(request('q'))<input type="hidden" name="q" value="{{ request('q') }}">@endif

                  <div class="login-data">
                    <label>Desk Name (optional)</label>
                    <input type="text" name="name" value="{{ old('name', $editDesk->name) }}">
                  </div>

                  <div class="login-data">
                    <label>Simulator Desk ID</label>
                    <input type="text" value="{{ $editDesk->serial_number }}" disabled>
                    <p class="desk-help-text">
                      This ID comes from the simulator API and cannot be changed here.
                    </p>
                  </div>

                  <div class="loginpage-btn" style="margin-top:12px;">
                    <button type="submit">Save Changes</button>
                  </div>
                </form>
              @else
                {{-- CREATE: SIMULATOR DESKS --}}
                <form method="POST" action="{{ route('admin.desks.store') }}" class="form">
                  @csrf
                  <input type="hidden" name="tab" value="desks">

                  <div class="login-data">
                    <label>Desk Name (optional)</label>
                    <input type="text" name="name" value="{{ old('name') }}">
                    <p class="desk-help-text">
                      This name will be applied to all selected desks (you can rename them individually afterwards).
                    </p>
                  </div>

                  @if(empty($availableDeskIds))
                    <p class="desk-help-text" style="margin-top:1rem;">
                      All simulator desks are already managed, or the simulator could not be reached.
                    </p>
                  @else
                    <div class="login-data">
                      <label>Simulator Desks to Register *</label>

                      <div class="desk-register-wrapper">
                        <select name="desk_ids[]" multiple required size="8"
                                class="desk-multi-select desk-register-select">
                            @foreach($availableDeskIds as $id)
                                <option value="{{ $id }}"
                                    @if(collect(old('desk_ids', []))->contains($id)) selected @endif>
                                    {{ $id }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                      <p class="desk-help-text">
                        Hold Ctrl (Windows) or Cmd (macOS) to select multiple desks.
                      </p>
                    </div>

                    <div class="desk-register-actions">
                        <div class="loginpage-btn">
                            <button type="submit">Register Desk(s)</button>
                        </div>
                    </div>
                  @endif
                </form>
              @endif
            </div>

            {{-- RIGHT COLUMN: Desk List --}}
            <div class="login-card">
              <div class="text section-title">Managed Desks</div>

              {{-- Search --}}
              <form method="GET" action="{{ route('admin.dashboard') }}" class="form desk-search-form">
                <input type="hidden" name="tab" value="desks">
                <div class="login-data">
                  <input type="text" name="q" placeholder="Search by name or simulator ID..."
                        value="{{ $deskSearch }}">
                </div>
                <div class="loginpage-btn">
                  <button type="submit">Search</button>
                </div>
              </form>

              {{-- Managed desks (compact cards) --}}
              @if($desks->isEmpty())
                <p class="text-center">No desks registered yet.</p>
              @else
                <div class="userlist-header desklist-header">
                  <div>Name</div>
                  <div>Simulator ID</div>
                </div>

                <div class="user-cards desk-cards">
                  @foreach($desks as $desk)
                    @php
                      $state = $deskStates[$desk->serial_number] ?? null;
                    @endphp

                    <div class="user-card desk-card">
                      <div class="user-info-row desk-info-row">
                        <div class="desk-name">
                          {{ $desk->name ?? ($state['config_name'] ?? '-') }}
                        </div>
                        <div class="desk-serial">
                          {{ $desk->serial_number }}
                        </div>
                      </div>

                      <div class="desk-meta-row">
                        <span class="desk-position">
                          @if($state && !is_null($state['position_cm']))
                            {{ $state['position_cm'] }} cm
                          @else
                            -
                          @endif
                        </span>
                        <span class="desk-status">
                          {{ $state['status'] ?? 'Unknown' }}
                        </span>
                      </div>

                      <div class="user-actions desk-actions">
                          {{-- Edit desk - same style as reward Edit --}}
                          <form style="display: inline;">
                            <div class="loginpage-btn btn-compact">
                              <button type="button"
                                      onclick="window.location='{{ route('admin.dashboard', array_filter([
                                          'tab'       => 'desks',
                                          'q'         => request('q'),
                                          'edit_desk' => $desk->id,
                                      ])) }}'"
                                      title="Edit desk">
                                <i class="fas fa-edit"></i>
                              </button>
                            </div>
                          </form>

                          {{-- Delete desk - same style as reward Delete --}}
                          <form action="{{ route('admin.desks.destroy', $desk) }}" method="POST"
                                style="display: inline;"
                                onsubmit="return confirm('Are you sure you want to remove this desk from management?');">
                            @csrf
                            @method('DELETE')
                            <div class="loginpage-btn btn-compact">
                              <button type="submit"
                                      style="background-color: #dc3545;"
                                      title="Delete desk">
                                <i class="fa-solid fa-trash"></i>
                              </button>
                            </div>
                          </form>
                        </div>
                    </div>
                  @endforeach
                </div>
              @endif
            </div>
          </div>

        {{-- USERS TAB --}}
        @elseif(request()->query('tab') === 'users')
        @php
          $deskOptions = $deskOptions ?? collect();
        @endphp
          <div class="dashboard-grid">
            {{-- LEFT COLUMN: User Form --}}
            <div class="login-card">
              <div class="text section-title">User Form</div>

              @if(isset($editUser))
                <form method="POST" action="{{ route('admin.users.update', $editUser) }}" class="form">
                  @csrf @method('PATCH')
                  @if(request('q'))<input type="hidden" name="q" value="{{ request('q') }}">@endif

                  <div class="d-flex flex-wrap" style="gap:16px;">
                    <div class="login-data" style="flex:1 1 260px;">
                      <label>First Name *</label>
                      <input type="text" name="name" value="{{ old('name', $editUser->name) }}" required>
                    </div>
                    <div class="login-data" style="flex:1 1 260px;">
                      <label>Last Name *</label>
                      <input type="text" name="surname" value="{{ old('surname', $editUser->surname) }}" required>
                    </div>
                  </div>

                  <div class="d-flex flex-wrap" style="gap:16px;">
                    <div class="login-data" style="flex:1 1 260px;">
                      <label>Username *</label>
                      <input type="text" name="username" value="{{ old('username', $editUser->username) }}" required>
                    </div>
                    <div class="login-data" style="flex:1 1 260px;">
                      <label>Date of Birth</label>
                      <input type="date" name="date_of_birth"
                        value="{{ old('date_of_birth', optional($editUser->date_of_birth)->format('Y-m-d')) }}">
                    </div>
                  </div>

                  <div class="d-flex flex-wrap" style="gap:16px;">
                    <div class="login-data" style="flex:1 1 260px;">
                      <label>Sitting Position</label>
                      <input type="number" name="sitting_position" min="60" max="130"
                        value="{{ old('sitting_position', $editUser->sitting_position) }}">
                    </div>
                    <div class="login-data" style="flex:1 1 260px;">
                      <label>Standing Position</label>
                      <input type="number" name="standing_position" min="60" max="130"
                        value="{{ old('standing_position', $editUser->standing_position) }}">
                    </div>
                  </div>
                  {{-- Desk assignment --}}
                  <div class="d-flex flex-wrap" style="gap:16px; margin-top: 8px;">
                    <div class="login-data" style="flex:1 1 260px;">
                      <label>Assigned Desk *</label>
                      <select name="desk_id" id="deskSelect" class="select-styled" required onchange="updateHeightLimits()">
                        <option value="" disabled {{ old('desk_id', $editUser->desk_id) ? '' : 'selected' }}>
                          -- Select a desk --
                        </option>
                        @foreach($deskOptions as $desk)
                          @php
                              $limits = $deskLimits[$desk->id] ?? ['min' => 60, 'max' => 130];
                          @endphp
                          <option value="{{ $desk->id }}"
                            data-min="{{ $limits['min'] }}"
                            data-max="{{ $limits['max'] }}"
                            @selected(old('desk_id', $editUser->desk_id) == $desk->id)>
                            {{ $desk->name ?? ('Desk #'.$desk->id) }} ({{ $desk->serial_number }})
                          </option>
                        @endforeach
                      </select>
                      <p class="desk-help-text">
                        A desk is required for every user.
                      </p>
                    </div>
                  </div>

                  <div class="loginpage-btn" style="margin-top:12px;">
                    <button type="submit">Save Changes</button>
                  </div>
                </form>
                <script>
                    function updateHeightLimits() {
                        const select = document.getElementById('deskSelect');
                        const selectedOption = select.options[select.selectedIndex];
                        const min = selectedOption.getAttribute('data-min') || 60;
                        const max = selectedOption.getAttribute('data-max') || 130;
                        
                        const sittingInput = document.querySelector('input[name="sitting_position"]');
                        const standingInput = document.querySelector('input[name="standing_position"]');
                        
                        if (sittingInput) {
                            sittingInput.min = min;
                            sittingInput.max = max;
                            // Optional: validate current value
                        }
                        if (standingInput) {
                            standingInput.min = min;
                            standingInput.max = max;
                        }
                    }
                    // Run on load
                    document.addEventListener('DOMContentLoaded', updateHeightLimits);
                </script>
              @else
                <form method="POST" action="{{ route('admin.users.store') }}" class="form">
                  @csrf

                  <div class="d-flex flex-wrap" style="gap:16px;">
                    <div class="login-data" style="flex:1 1 260px;">
                      <label>First Name *</label>
                      <input type="text" name="name" value="{{ old('name') }}" required>
                    </div>
                    <div class="login-data" style="flex:1 1 260px;">
                      <label>Last Name *</label>
                      <input type="text" name="surname" value="{{ old('surname') }}" required>
                    </div>
                  </div>

                  <div class="d-flex flex-wrap" style="gap:16px;">
                    <div class="login-data" style="flex:1 1 260px;">
                      <label>Username *</label>
                      <input type="text" name="username" value="{{ old('username') }}" required>
                    </div>
                    <div class="login-data" style="flex:1 1 260px;">
                      <label>Date of Birth</label>
                      <input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}">
                    </div>
                  </div>

                  <div class="d-flex flex-wrap" style="gap:16px;">
                    <div class="login-data" style="flex:1 1 260px;">
                      <label>Password *</label>
                      <input type="password" name="password" required>
                    </div>
                    <div class="login-data" style="flex:1 1 260px;">
                      <label>Confirm Password *</label>
                      <input type="password" name="password_confirmation" required>
                    </div>
                  </div>

                  <div class="d-flex flex-wrap" style="gap:16px;">
                    <div class="login-data" style="flex:1 1 260px;">
                      <label>Sitting Position</label>
                      <input type="number" name="sitting_position" min="60" max="130" value="{{ old('sitting_position') }}">
                    </div>
                    <div class="login-data" style="flex:1 1 260px;">
                      <label>Standing Position</label>
                      <input type="number" name="standing_position" min="60" max="130"
                        value="{{ old('standing_position') }}">
                    </div>
                  </div>

                  {{-- Desk assignment (for new user) --}}
                  <div class="d-flex flex-wrap" style="gap:16px; margin-top: 8px;">
                    <div class="login-data" style="flex:1 1 260px;">
                      <label>Assigned Desk *</label>
                      <select name="desk_id" id="deskSelectNew" class="select-styled" required onchange="updateHeightLimitsNew()">
                        <option value="" disabled {{ old('desk_id') ? '' : 'selected' }}>
                          -- Select a desk --
                        </option>
                        @foreach($deskOptions as $desk)
                          @php
                              $limits = $deskLimits[$desk->id] ?? ['min' => 60, 'max' => 130];
                          @endphp
                          <option value="{{ $desk->id }}"
                            data-min="{{ $limits['min'] }}"
                            data-max="{{ $limits['max'] }}"
                            @selected(old('desk_id') == $desk->id)>
                            {{ $desk->name ?? ('Desk #'.$desk->id) }} ({{ $desk->serial_number }})
                          </option>
                        @endforeach
                      </select>
                      <p class="desk-help-text">
                        A desk is required for every user.
                      </p>
                    </div>
                  </div>

                  <div class="loginpage-btn" style="margin-top:12px;">
                    <button type="submit">Create User</button>
                  </div>
                </form>
                <script>
                    function updateHeightLimitsNew() {
                        const select = document.getElementById('deskSelectNew');
                        const selectedOption = select.options[select.selectedIndex];
                        const min = selectedOption.getAttribute('data-min') || 60;
                        const max = selectedOption.getAttribute('data-max') || 130;
                        
                        // Scope to this form
                        const form = select.closest('form');
                        const sittingInput = form.querySelector('input[name="sitting_position"]');
                        const standingInput = form.querySelector('input[name="standing_position"]');
                        
                        if (sittingInput) {
                            sittingInput.min = min;
                            sittingInput.max = max;
                        }
                        if (standingInput) {
                            standingInput.min = min;
                            standingInput.max = max;
                        }
                    }
                </script>
              @endif
            </div>

            {{-- RIGHT COLUMN: Search + User Cards --}}
            <div class="login-card">
              <form method="GET" action="{{ route('admin.dashboard') }}" class="form"
                style="margin-bottom: 2rem;">
                <input type="hidden" name="tab" value="users">
                <div class="login-data">
                  <input type="text" name="q" placeholder="Search by name/surname/username..." value="{{ request('q') }}">
                </div>
                <div class="loginpage-btn">
                  <button type="submit">Search</button>
                </div>
              </form>

              <div class="text section-title">Users ({{ $users->total() }})</div>

              @if($users->isEmpty())
                <p class="text-center">No users found.</p>
              @else
                <div class="userlist-header">
                  <div>Name</div>
                  <div>Surname</div>
                  <div>Username</div>
                  <div>Role</div>
                </div>

                <div class="user-cards">
                  @foreach($users as $user)
                    <div class="user-card">
                      <div class="user-info-row">
                        <div>{{ $user->name }}</div>
                        <div>{{ $user->surname }}</div>
                        <div>{{ $user->username }}</div>
                        <div>
                          @if($user->role === 'admin')
                            <span class="badge bg-warning text-dark" style="font-size:0.9rem;">admin</span>
                          @else
                            <span class="badge bg-secondary" style="font-size:0.9rem;">user</span>
                          @endif
                        </div>
                      </div>

                      <div class="user-actions">
                        <a href="{{ route('admin.dashboard', array_filter(['tab' => 'users', 'q' => request('q'), 'edit' => $user->user_id])) }}"
                          class="btn-edit btn-compact" title="Edit user">
                          <button type="button">
                            <i class="fa-solid fa-pen"></i>
                          </button>
                        </a>

                        @if($user->role === 'admin')
                          <form action="{{ route('admin.users.demote', $user) }}" method="POST">
                            @csrf @method('PATCH')
                            <div class="loginpage-btn btn-compact">
                              <button type="submit" class="demote-btn" {{ auth()->id() === $user->user_id ? 'disabled' : '' }}
                                title="{{ auth()->id() === $user->user_id ? 'Cannot demote yourself' : 'Demote to user' }}">
                                Demote
                              </button>
                            </div>
                          </form>
                        @else
                          <form action="{{ route('admin.users.promote', $user) }}" method="POST">
                            @csrf @method('PATCH')
                            <div class="loginpage-btn btn-compact">
                              <button type="submit" title="Promote to admin">
                                Promote
                              </button>
                            </div>
                          </form>
                        @endif

                        <form action="{{ route('admin.users.destroy', $user) }}" method="POST">
                          @csrf @method('DELETE')
                          <div class="loginpage-btn btn-compact">
                            <button type="submit" {{ auth()->id() === $user->getKey() ? 'disabled' : '' }}
                              title="{{ auth()->id() === $user->getKey() ? 'Cannot delete yourself' : 'Delete user' }}">
                              Delete
                            </button>
                          </div>
                        </form>
                      </div>
                    </div>
                  @endforeach
                </div>

                <div class="mt-3 text-center">
                  {{ $users->appends(['tab' => 'users', 'q' => request('q')])->links() }}
                </div>
              @endif
            </div>
          </div>

        {{-- AVERAGES TAB --}}
          @elseif(request()->query('tab') === 'averages')
          @php
            $normalizedSitting = 20;
            $normalizedStanding = $avgSitting > 0 ? ($avgStanding / $avgSitting) * 20 : 0;
            $goalStanding = 10;
            $sessionTotal = max($avgSitting + $avgStanding, 0);
            $standingShare = $sessionTotal > 0 ? ($avgStanding / $sessionTotal) * 100 : 0;
            $sittingShare = max(0, 100 - $standingShare);
            $trendDelta = $normalizedStanding - $goalStanding;
            $trendPrefix = $trendDelta >= 0 ? '+' : '';

            if ($avgSitting == 0 && $avgStanding == 0) {
                $status = 'No Data';
                $statusColor = '#6c757d';
                $message = "Collect more sessions to unlock insights.";
            } elseif ($avgSitting == 0) {
                $status = 'Standing Only';
                $statusColor = '#28a745';
                $message = "Teams are fully upright right now.";
                $normalizedStanding = 20;
            } elseif ($normalizedStanding < 5) {
                $status = 'Sedentary';
                $statusColor = '#dc3545';
                $message = "Encourage micro-stands or breaks.";
            } elseif ($normalizedStanding > 15) {
              $status = 'Highly Active';
              $statusColor = '#28a745';
              $message = "Users are exceeding the goal.";
            } else {
                $status = 'Balanced';
                $statusColor = '#17a2b8';
                $message = "Close to the 20:10 LevelUp target.";
            }
          @endphp

          <div class="analytics-stack">
            <div class="login-card analytics-chart-card analytics-chart-card--featured">
              <div class="analytics-chart-header">
                <div>
                  <p class="analytics-eyebrow">Live dataset</p>
                  <h3>Average minutes per posture</h3>
                </div>
                <div class="analytics-chart-meta">
                  <span class="analytics-pill muted">Goal 20 : 10</span>
                  <span class="analytics-pill" style="background: {{ $statusColor }};">{{ $status }}</span>
                </div>
              </div>
              <article class="barchart" aria-labelledby="avgBarTitle">
                <h2 id="avgBarTitle" class="visually-hidden">Average Sitting and Standing</h2>
                <script>
                window.avgSitting = {{ $avgSitting }};
                window.avgStanding = {{ $avgStanding }};
                </script>
                <canvas id="averageStatsChart"></canvas>
              </article>
                <div class="analytics-inline-summary">
                  <div>
                    <span>Status</span>
                    <strong style="color: {{ $statusColor }};">{{ $status }}</strong>
                    <small>Live reading</small>
                  </div>
                  <div>
                    <span>Current ratio</span>
                    <strong>20 : {{ number_format($normalizedStanding, 1) }}</strong>
                    <small>{{ $trendPrefix }}{{ number_format($trendDelta, 1) }} vs goal</small>
                  </div>
                  <div>
                    <span>Standing share</span>
                    <strong>{{ number_format($standingShare, 0) }}%</strong>
                    <small>{{ number_format($sittingShare, 0) }}% seated</small>
                  </div>
                  <div>
                    <span>Users tracked</span>
                    <strong>{{ $totalUsers }}</strong>
                    <small>live desks</small>
                  </div>
                </div>
            </div>
          </div>

        {{-- DESK CLEANING TAB --}}
        @elseif(request()->query('tab') === 'desk-cleaning')
          @php
            $allManagedDesks = $allManagedDesks ?? collect();
            $deskStates      = $deskStates ?? [];
          @endphp

          @if($allManagedDesks->isEmpty())
            <div class="login-card" style="width:100%; max-width: 720px; margin: 0 auto;">
              <div class="text section-title">Set Height for Multiple Desks</div>
              <p class="text-center" style="margin-top: 1rem;">
                There are no managed desks yet. Go to <strong>Manage Desks</strong> tab to register desks first.
              </p>
            </div>
          @else
            <form method="POST" action="{{ route('admin.desks.bulk-height') }}" class="desk-cleaning-form">
              @csrf
              <input type="hidden" name="tab" value="desk-cleaning">
              @if(request('q'))<input type="hidden" name="q" value="{{ request('q') }}">@endif

              <div class="dashboard-grid">
                {{-- LEFT COLUMN: Desk list with checkboxes --}}
                <div class="login-card">
                  <div class="text section-title">Select Desks</div>

                  <div class="login-data">
                    <div class="desk-select-all-row">
                      <label class="desk-select-all-label">
                        <input type="checkbox" id="selectAllDesksCheckbox">
                        <span>Select all desks</span>
                      </label>
                    </div>

                    <div class="desk-list-scroll">
                      @foreach($allManagedDesks as $desk)
                        @php
                          $state     = $deskStates[$desk->serial_number] ?? null;
                          $labelName = $desk->name
                            ?? ($state['config_name'] ?? ('Desk #'.$desk->id));
                          $posText   = $state && !is_null($state['position_cm'])
                            ? $state['position_cm'].' cm'
                            : 'unknown height';
                        @endphp

                        <label class="desk-checkbox-row">
                          <input type="checkbox"
                                name="desk_ids[]"
                                value="{{ $desk->id }}"
                                class="desk-checkbox">
                          <div class="desk-checkbox-info">
                            <div class="desk-checkbox-name">{{ $labelName }}</div>
                            <div class="desk-checkbox-meta">
                              <span class="desk-checkbox-serial">{{ $desk->serial_number }}</span>
                              <span class="desk-checkbox-pos">• {{ $posText }}</span>
                            </div>
                          </div>
                        </label>
                      @endforeach
                    </div>

                    <p class="desk-help-text" style="margin-top:0.5rem;">
                      Tick one or more desks, or use <strong>Select all desks</strong> above.
                    </p>
                  </div>
                </div>

                {{-- RIGHT COLUMN: Height input + button --}}
                <div class="login-card">
                  <div class="text section-title">Target Height</div>

                  <div class="login-data">
                    <label>Target height (cm) *</label>
                    <input type="number" name="height_cm" min="60" max="130" required>
                    <p class="desk-help-text">
                      This will send a command to the simulator to move all selected desks
                      to the specified height.
                    </p>
                  </div>

                  <div class="loginpage-btn desk-bulk-submit">
                    <button type="submit">Move Selected Desks</button>
                  </div>
                </div>
              </div>
            </form>
          @endif

        {{-- REWARDS TAB --}}
        @elseif(request()->query('tab') === 'rewards')
          <div class="dashboard-grid">
            {{-- LEFT COLUMN: Reward Form --}}
            <div class="login-card">
              <div class="text section-title">Reward Form</div>

              @if(isset($editReward))
                <form method="POST" action="{{ route('admin.rewards.update', $editReward) }}" enctype="multipart/form-data" class="form">
                  @csrf @method('PUT')
                  @if(request('q'))<input type="hidden" name="q" value="{{ request('q') }}">@endif

                  <div class="login-data">
                    <label>Reward Name *</label>
                    <input type="text" name="card_name" value="{{ old('card_name', $editReward->card_name) }}" required>
                  </div>

                  <div class="login-data">
                    <label>Points Amount *</label>
                    <input type="number" name="points_amount" min="0" value="{{ old('points_amount', $editReward->points_amount) }}" required>
                  </div>

                  <div class="login-data" style="margin-bottom: 20px;">
                    <label>Description</label>
                    <textarea name="card_description" rows="3">{{ old('card_description', $editReward->card_description) }}</textarea>
                  </div>

                  <div class="login-data">
                    <label>Reward Image</label>
                    <input type="file" name="card_image" accept="image/*">
                  </div>
                    @if($editReward->card_image)
                      <div style="margin-top: 8px; margin-bottom: 16px;">
                        <img src="{{ asset($editReward->card_image) }}" alt="Current image" style="width: 100px; height: 60px; object-fit: cover;">
                        <p style="font-size: 0.85em; color: #666;">Current image</p>
                      </div>
                    @endif

                  <div class="loginpage-btn" style="margin-top:12px;">
                    <button type="submit">Save Changes</button>
                  </div>
                </form>
              @else
                <form method="POST" action="{{ route('admin.rewards.store') }}" enctype="multipart/form-data" class="form">
                  @csrf

                  <div class="login-data">
                    <label>Reward Name *</label>
                    <input type="text" name="card_name" value="{{ old('card_name') }}" required>
                  </div>

                  <div class="login-data">
                    <label>Points Amount *</label>
                    <input type="number" name="points_amount" min="0" value="{{ old('points_amount') }}" required>
                  </div>

                  <div class="login-data">
                    <label>Description</label>
                    <textarea name="card_description" rows="3">{{ old('card_description') }}</textarea>
                  </div>

                  <div class="login-data">
                    <label>Reward Image</label>
                    <input type="file" name="card_image" accept="image/*">
                    <p style="font-size: 0.85em; color: #666; margin-top: 4px;">Optional: Upload an image for the reward card</p>
                  </div>

                  <div class="loginpage-btn" style="margin-top:12px;">
                    <button type="submit">Create Reward</button>
                  </div>
                </form>
              @endif
            </div>

            {{-- RIGHT COLUMN: Rewards List --}}
            <div class="login-card">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <div class="text section-title">Active Rewards</div>
                <button type="button" class="loginpage-btn" id="toggleArchivedBtn"
                  style="background: linear-gradient(43deg, var(--color-primary) 0%, #171231 46%, #1c2046 100%); color: white; border-radius: 8px;">
                  <i class="fas fa-archive"></i> Show Archived
                </button>
              </div>

              @if($activeRewards && $activeRewards->isEmpty())
                <p style="text-align: center; color: #999;">No active rewards found.</p>
              @else
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                  @foreach($activeRewards ?? [] as $reward)
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6;">
                      <div style="display: flex; align-items: center; gap: 1rem; flex: 1;">
                        @php
                            $filename = $reward->card_image;
                            $storagePath = $filename ? public_path('storage/images/giftcards/' . basename($filename)) : null;
                            $publicPath = $filename ? public_path('images/giftcards/' . basename($filename)) : null;
                        @endphp

                        @if($filename && file_exists($storagePath))
                            <img src="{{ asset('storage/images/giftcards/' . basename($filename)) }}"
                                alt="{{ $reward->card_name }}" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                        @elseif($filename && file_exists($publicPath))
                            <img src="{{ asset('images/giftcards/' . basename($filename)) }}"
                                alt="{{ $reward->card_name }}" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                        @else
                            <img src="{{ asset('images/giftcards/placeholder.png') }}"
                                alt="{{ $reward->card_name }}" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                        @endif
                        
                        <div>
                          <h4 style="margin: 0 0 0.25rem 0; font-size: 1.1em;">{{ $reward->card_name }}</h4>
                          <p style="color: #666; font-size: 0.9em; margin: 0.25rem 0;">{{ Str::limit($reward->card_description ?? '', 50) }}</p>
                          <p style="color: #007bff; font-weight: bold; margin: 0.25rem 0;">{{ $reward->points_amount }} Points</p>
                        </div>
                      </div>

                      <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <form style="display: inline;">
                          <div class="loginpage-btn btn-compact">
                            <button type="button" 
                                    onclick="window.location='{{ route('admin.dashboard', array_filter(['tab' => 'rewards', 'q' => request('q'), 'edit_reward' => $reward->id])) }}'" 
                                    title="Edit reward">
                              <i class="fas fa-edit"></i>
                            </button>
                          </div>
                        </form>

                        <form action="{{ route('admin.rewards.archive', $reward) }}" method="POST" style="display: inline;">
                          @csrf @method('PATCH')
                          <div class="loginpage-btn btn-compact">
                            <button type="submit" style="background-color: #ffc107;" title="Archive reward"
                                    onclick="return confirm('Are you sure you want to archive this reward?')">
                              <i class="fas fa-archive"></i>
                            </button>
                          </div>
                        </form>
                      </div>
                    </div>
                  @endforeach
                </div>
              @endif

              <!-- Archived Rewards Section -->
              <div id="archivedRewardsSection" style="display: none; margin-top: 2rem; padding-top: 1rem; border-top: 2px solid #ddd;">
                <div class="text section-title">Archived Rewards</div>
                
                @if($archivedRewards && $archivedRewards->isEmpty())
                  <p style="text-align: center; color: #999;">No archived rewards found.</p>
                @else
                  <div style="display: flex; flex-direction: column; gap: 1rem;">
                    @foreach($archivedRewards ?? [] as $reward)
                      <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6; opacity: 0.7;">
                        <div style="display: flex; align-items: center; gap: 1rem; flex: 1;">
                          @php
                              $filename = $reward->card_image;
                              $storagePath = $filename ? public_path('storage/images/giftcards/' . basename($filename)) : null;
                              $publicPath = $filename ? public_path('images/giftcards/' . basename($filename)) : null;
                          @endphp

                          @if($filename && file_exists($storagePath))
                              <img src="{{ asset('storage/images/giftcards/' . basename($filename)) }}"
                                  alt="{{ $reward->card_name }}" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                          @elseif($filename && file_exists($publicPath))
                              <img src="{{ asset('images/giftcards/' . basename($filename)) }}"
                                  alt="{{ $reward->card_name }}" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                          @else
                              <img src="{{ asset('images/giftcards/placeholder.png') }}"
                                  alt="{{ $reward->card_name }}" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                          @endif

                          <div>
                            <h4 style="margin: 0 0 0.25rem 0; font-size: 1.1em;">{{ $reward->card_name }} <span style="color: #999;">(Archived)</span></h4>
                            <p style="color: #666; font-size: 0.9em; margin: 0.25rem 0;">{{ Str::limit($reward->card_description ?? '', 50) }}</p>
                            <p style="color: #007bff; font-weight: bold; margin: 0.25rem 0;">{{ $reward->points_amount }} Points</p>
                          </div>
                        </div>

                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                          <form action="{{ route('admin.rewards.unarchive', $reward) }}" method="POST" style="display: inline;">
                            @csrf @method('PATCH')
                            <div class="loginpage-btn btn-compact">
                              <button type="submit" style="background-color: #28a745;" title="Unarchive reward"
                                      onclick="return confirm('Are you sure you want to unarchive this reward?')">
                                <i class="fas fa-undo"></i>
                              </button>
                            </div>
                          </form>

                          <form action="{{ route('admin.rewards.destroy', $reward) }}" method="POST" style="display: inline;">
                            @csrf @method('DELETE')
                            <div class="loginpage-btn btn-compact">
                              <button type="submit" style="background-color: #dc3545;" title="Delete reward"
                                      onclick="return confirm('Are you sure you want to delete this reward? This cannot be undone.')">
                                <i class="fas fa-trash"></i>
                              </button>
                            </div>
                          </form>
                        </div>
                      </div>
                    @endforeach
                  </div>
                @endif
              </div>
            </div>
          </div>
        @endif
      </div>
    </div>
  </div>
@endsection