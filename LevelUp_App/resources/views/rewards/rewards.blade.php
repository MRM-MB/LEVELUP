@extends('layouts.app')

@section('additional_css')
    @vite('resources/css/rewards.css')
@endsection

@section('additional_js')
    @vite('resources/js/rewards.js')
@endsection

@section('title', 'Rewards')

@section('content')
    <!-- Rewards Sub-Navigation -->
    <div class="rewards-nav">
        <a href="{{ url('/rewards?tab=available') }}"
            class="rewards-nav-link {{ request()->query('tab', 'all') === 'available' ? 'active' : '' }}">
            <i class="fas fa-gift"></i>
            Available
        </a>
        <a href="{{ url('/rewards?tab=saved') }}"
            class="rewards-nav-link {{ request()->query('tab') === 'saved' ? 'active' : '' }}">
            <i class="fas fa-bookmark"></i>
            Saved
        </a>
        <a href="{{ url('/rewards?tab=all') }}"
            class="rewards-nav-link {{ request()->query('tab', 'all') === 'all' ? 'active' : '' }}">
            <i class="fas fa-list"></i>
            All
        </a>
        <a href="{{ url('/rewards?tab=history') }}"
            class="rewards-nav-link {{ request()->query('tab') === 'history' ? 'active' : '' }}">
            <i class="fas fa-history"></i>
            History
        </a>

    </div>

    <main class="content">
        <!-- Content Area -->
        <div class="rewards-content">
            @if(request()->query('tab') === 'available')
                <!-- Available Rewards Content -->
                <div class="rewards-grid" id="availableRewardsGrid">
                </div>
            @elseif(request()->query('tab') === 'saved')
                <!-- Saved Rewards Content -->
                <div class="rewards-grid" id="savedRewardsGrid">
                </div>
            @elseif(request()->query('tab', 'all') === 'all')
                <!-- All Rewards Content -->
                <div class="rewards-grid" id="allRewardsGrid">
                    @foreach($rewards as $reward)
                        <div class="reward-card" data-reward-id="{{ $reward->id }}">
                            <button class="save-btn" data-reward-id="{{ $reward->id }}" type="button">
                                <img src="{{ asset('images/giftcards/heart_unchecked.png') }}" alt="Save" class="heart-icon">
                            </button>

                            <div class="reward-image">
                                <img src="{{ $reward->card_image ? asset($reward->card_image) : asset('images/giftcards/placeholder.png') }}"
                                    alt="{{ $reward->card_name }}">
                            </div>
                            <div class="reward-content">
                                <h3>{{ $reward->card_name }}</h3>
                                <p class="reward-description">{{ $reward->card_description }}</p>
                                <button class="redeem-btn" data-points="{{ $reward->points_amount }}" type="button">
                                    <span class="btn-text">Not Yet</span>
                                </button>
                                <p class="reward-points">{{ $reward->points_amount }} Points</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @elseif(request()->query('tab') === 'history')
                  <!-- History Tab Content -->
                      @if($redeemedRewards->count() > 0)
                          <table class="history-table">
                              <thead>
                                  <tr>
                                      <th>#</th>
                                      <th>Reward Name</th>
                                      <th>Points Spent</th>
                                      <th>Description</th>
                                      <th>Redeemed Date</th>
                                  </tr>
                              </thead>
                              <tbody>
                                  @foreach($redeemedRewards as $index => $reward)
                                      <tr>
                                          <td>{{ $index + 1 }}</td>
                                          <td>{{ $reward->pivot->card_name_snapshot }}</td>
                                          <td>{{ $reward->pivot->points_amount_snapshot }}</td>
                                          <td>{{ $reward->pivot->card_description_snapshot }}</td>
                                          <td>{{
  \Carbon\Carbon::parse($reward->pivot->redeemed_at)->format('M d, Y H:i') }}</td>
                                      </tr>
                                  @endforeach
                              </tbody>
                          </table>
                      @else
                          <p class="no-saved-message">You haven't redeemed any rewards yet. Browse rewards and redeem some!</p>
                      @endif
            @endif
        </div>

        <!-- Hidden container with all rewards for cloning -->
        <div id="rewardsTemplate" style="display: none;">
            @foreach($rewards as $reward)
                <div class="reward-card" data-reward-id="{{ $reward->id }}">
                    <button class="save-btn" data-reward-id="{{ $reward->id }}" type="button">
                        <img src="{{ asset('images/giftcards/heart_unchecked.png') }}" alt="Save" class="heart-icon">
                    </button>

                    <div class="reward-image">
                        <img src="{{ $reward->card_image ? asset($reward->card_image) : asset('images/giftcards/placeholder.png') }}"
                            alt="{{ $reward->card_name }}">
                    </div>
                    <div class="reward-content">
                        <h3>{{ $reward->card_name }}</h3>
                        <p class="reward-description">{{ $reward->card_description }}</p>
                        <button class="redeem-btn" data-points="{{ $reward->points_amount }}" type="button">
                            <span class="btn-text">Not Yet</span>
                        </button>
                        <p class="reward-points">{{ $reward->points_amount }} Points</p>
                    </div>
                </div>
            @endforeach
        </div>
    </main>
@endsection