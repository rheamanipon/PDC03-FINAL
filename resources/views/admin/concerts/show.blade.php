<x-app-layout>
    <section class="admin-dashboard" id="adminDashboard">
        <div class="admin-shell">
            @include('admin.partials.sidebar')
            <main class="admin-main">
                @include('admin.partials.flash')
                <header class="admin-header">
                    <div>
                        <h2>{{ $concert->title }}</h2>
                        <p>{{ $concert->artist }} at {{ optional($concert->venue)->name ?? 'No Venue' }}</p>
                    </div>
                    <div class="admin-header-actions">
                        <button type="button" class="ad-btn ad-icon-btn" id="themeToggleBtn"><span id="themeToggleIcon">◐</span></button>
                        <a href="{{ route('admin.concerts.index') }}" class="ad-btn">Back</a>
                        <a href="{{ route('admin.concerts.edit', $concert) }}" class="ad-btn ad-btn-primary">Edit</a>
                    </div>
                </header>

                <section class="ad-card ad-concert-show-card">
@php
                        $ticketCounts = [
                            'total' => \App\Models\Ticket::whereHas('booking', function($q) use ($concert) {
                                $q->where('concert_id', $concert->id);
                            })->count(),
                        ];
                        $revenue = (float) $concert->bookings->sum('total_price');
                        $remainingTickets = optional($concert->venue)->capacity - $ticketCounts['total'];
                    @endphp

                    <div class="ad-concert-show-layout">
                        <div class="ad-concert-main">
                            <div class="ad-concert-poster-wrap">
                                @if($concert->poster_url)
                                    <img class="ad-poster" src="{{ asset('storage/'.$concert->poster_url) }}" alt="{{ $concert->title }} poster">
                                @else
                                    <div class="ad-empty-poster">No poster uploaded</div>
                                @endif
                            </div>

                            <div class="ad-concert-details-panel">
                                <h3 class="ad-panel-title">Concert Details</h3>
                                <div class="ad-concert-details-grid">
                                    <div class="ad-concert-detail-box"><span class="ad-label">Concert ID</span><strong>#{{ $concert->id }}</strong></div>
                                    <div class="ad-concert-detail-box"><span class="ad-label">Artist</span><strong>{{ $concert->artist }}</strong></div>
                                    <div class="ad-concert-detail-box"><span class="ad-label">Venue</span><strong>{{ optional($concert->venue)->name ?? 'N/A' }}</strong></div>
                                    <div class="ad-concert-detail-box"><span class="ad-label">Location</span><strong>{{ optional($concert->venue)->location ?? 'N/A' }}</strong></div>
                                    <div class="ad-concert-detail-box"><span class="ad-label">Date</span><strong>{{ optional($concert->date)->format('M d, Y') ?? 'N/A' }}</strong></div>
                                    <div class="ad-concert-detail-box"><span class="ad-label">Time</span><strong>{{ optional($concert->time)->format('h:i A') ?? 'N/A' }}</strong></div>
                                </div>
                            </div>
                        </div>

                        <aside class="ad-concert-analytics">
                            <h3 class="ad-panel-title">Performance Analytics</h3>
                            <div class="ad-kpi-grid ad-concert-kpis">
                                <div class="ad-kpi"><span class="label">Total Tickets Sold</span><span class="value">{{ number_format($ticketCounts['total']) }}</span></div>
                                <div class="ad-kpi"><span class="label">Total Bookings</span><span class="value">{{ $concert->bookings->count() }}</span></div>
                                <div class="ad-kpi"><span class="label">Total Revenue</span><span class="value">${{ number_format($revenue, 2) }}</span></div>
                                <div class="ad-kpi"><span class="label">Tickets Available</span><span class="value">{{ $remainingTickets > 0 ? number_format($remainingTickets) : '0' }}</span></div>
                            </div>

                            <div class="ad-chart-block">
                                <p class="ad-label">Sales Overview</p>
                                <div class="ad-chart-row">
                                    <span>Bookings</span>
                                    <div class="ad-chart-track"><div class="ad-chart-fill sold" style="width: 100%;"></div></div>
                                    <strong>{{ $concert->bookings->count() }}</strong>
                                </div>
                                <div class="ad-chart-row">
                                    <span>Tickets</span>
                                    <div class="ad-chart-track"><div class="ad-chart-fill available" style="width: 100%;"></div></div>
                                    <strong>{{ number_format($ticketCounts['total']) }}</strong>
                                </div>
                            </div>

                            {{-- Concert Seat Plan Image --}}
                            @if($concert->seat_plan_image)
                                <div class="ad-card" style="margin-top: 1rem;">
                                    <h3 class="ad-panel-title">Concert Seat Plan</h3>
                                    <div style="text-align: center;">
                                        <img src="{{ asset('storage/' . $concert->seat_plan_image) }}" alt="Concert Seat Plan" style="max-width: 100%; height: auto; border-radius: 0.5rem; box-shadow: 0 10px 25px rgba(0,0,0,0.3);">
                                    </div>
                                </div>
                            @endif
                        </aside>
                    </div>
                </section>

                <section class="ad-card" style="margin-top: 1rem;">
                    <h3 class="ad-panel-title">Description</h3>
                    <p>{{ $concert->description ?: 'No description available for this concert.' }}</p>
                </section>
            </main>
        </div>
    </section>
    @include('admin.partials.theme-script')
</x-app-layout>
