@extends('layouts.app')

@section('title', 'Statistics')

<!-- Main Content -->
@section('content')
    <main class="statistics-content">
        <header class="statistics-container">
            <h1>Your statistics </h1>
        </header>

        <!-- Bar Chart -->
        <section class="stats-grid-container">
            <article class="barchart" aria-labelledby="barTitle">
                <h2 id="barTitle" class="visually-hidden">Daily Activity</h2>
                <canvas id="barChart" role="img" aria-label="Bar chart"></canvas>
            </article>
        </section>

        <!-- Toggle Button -->
        <button id="togglePieChart" class="togglePieBtn" aria-expanded="false" aria-controls="allTimeChart">
            Show All-Time Statistics
        </button>

        <!-- Pie Chart -->
        <section class="stats-grid-container" id="allTimeChart">
            <article class="piechart">
                <h2 id="pieTitle" class="visually-hidden">All-Time Activity</h2>
                <canvas id="pieChart" role="img" aria-label="Pie Chart"></canvas>
            </article>
        </section>
    </main>
@endsection

@section('scripts')
    <script>
        const healthCycle = @json($healthCycle ?? null);
        const totalSitting = {{ $totalSitting ?? 0}};
        const totalStanding = {{ $totalStanding ?? 0}};
    </script>
    @vite('resources/js/statistics.js')
@endsection