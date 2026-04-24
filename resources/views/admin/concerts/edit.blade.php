<x-app-layout>
    <section class="admin-dashboard" id="adminDashboard">
        <div class="admin-shell">
            @include('admin.partials.sidebar')
            <main class="admin-main">
                @include('admin.partials.flash')
                <header class="admin-header">
                    <div><h2>Edit Concert</h2><p>Update event metadata to ensure listing accuracy and schedule integrity.</p></div>
                    <div class="admin-header-actions">
                        <button type="button" class="ad-btn ad-icon-btn" id="themeToggleBtn"><span id="themeToggleIcon">◐</span></button>
                        <a href="{{ route('admin.concerts.index') }}" class="ad-btn">Back</a>
                    </div>
                </header>
                <section class="ad-card">
                    <h3 class="ad-panel-title">Edit Concert Information</h3>
                    <form method="POST" action="{{ route('admin.concerts.update', $concert) }}" enctype="multipart/form-data" class="ad-form-grid-2">
                        @csrf @method('PUT')
                        <div class="ad-field">
                            <label class="ad-label" for="title">Title</label>
                            <input class="ad-input" id="title" type="text" name="title" value="{{ old('title', $concert->title) }}" required>
                        </div>
                        <div class="ad-field">
                            <label class="ad-label" for="artist">Artist</label>
                            <input class="ad-input" id="artist" type="text" name="artist" value="{{ old('artist', $concert->artist) }}" required>
                        </div>
                        <div class="ad-field">
                            <label class="ad-label" for="venue_id">Venue</label>
                            <select class="ad-select" id="venue_id" name="venue_id" required>
                                @foreach($venues as $venue)
                                    <option value="{{ $venue->id }}" {{ old('venue_id', $concert->venue_id) == $venue->id ? 'selected' : '' }}>{{ $venue->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ad-field">
                            <label class="ad-label" for="date">Date</label>
                            <input class="ad-input" id="date" type="date" name="date" value="{{ old('date', optional($concert->date)->format('Y-m-d')) }}" required>
                        </div>
                        <div class="ad-field">
                            <label class="ad-label" for="time">Time</label>
                            <input class="ad-input" id="time" type="time" name="time" value="{{ old('time', optional($concert->time)->format('H:i')) }}" required>
                        </div>
                        <div class="ad-field">
                            <label class="ad-label" for="poster">Update Poster</label>
                            <input class="ad-input" id="poster" type="file" name="poster" accept="image/*">
                        </div>
                        <div class="ad-field">
                            <label class="ad-label" for="seat_plan_image">Update Seat Plan Image</label>
                            <input class="ad-input" id="seat_plan_image" type="file" name="seat_plan_image" accept="image/*">
                            <p style="font-size: 0.8rem; color: #94a3b8; margin-top: 0.25rem;">Update venue seat plan image for customer reference</p>
                        </div>
                        <div class="ad-field ad-field-full">
                            <label class="ad-label" for="description">Description</label>
                            <textarea class="ad-textarea" id="description" name="description">{{ old('description', $concert->description) }}</textarea>
                        </div>
                        @if($concert->poster_url)
                            <div class="ad-field ad-field-full">
                                <label class="ad-label">Current Poster</label>
                                <img class="ad-poster" src="{{ asset('storage/'.$concert->poster_url) }}" alt="{{ $concert->title }} poster">
                            </div>
                        @endif
                        <div class="ad-field ad-field-full">
                            <div class="ad-actions-row">
                                <button class="ad-btn ad-btn-primary" type="submit">Update Concert</button>
                            </div>
                        </div>
                    </form>
                </section>
            </main>
        </div>
    </section>
    @include('admin.partials.theme-script')
</x-app-layout>
