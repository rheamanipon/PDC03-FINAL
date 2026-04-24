<x-app-layout>
    <div style="margin-bottom: 2rem;">
        <div style="display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; align-items: center;">
            <div style="text-align: center; padding: 1rem; background: rgba(255, 102, 0, 0.08); border-left: 4px solid var(--accent-primary);">
                <p style="font-size: 0.75rem; letter-spacing: 0.15em; text-transform: uppercase; margin-bottom: 0.5rem; color: var(--text-tertiary);">Step 1</p>
                <h3 style="margin: 0; font-size: 1rem; font-weight: 700;">Choose Tickets</h3>
            </div>
            <div style="text-align: center; padding: 1rem; background: rgba(0, 0, 0, 0.03); border-left: 4px solid transparent;">
                <p style="font-size: 0.75rem; letter-spacing: 0.15em; text-transform: uppercase; margin-bottom: 0.5rem; color: var(--text-tertiary);">Step 2</p>
                <h3 style="margin: 0; font-size: 1rem; font-weight: 700;">Review Order</h3>
            </div>
            <div style="text-align: center; padding: 1rem; background: rgba(0, 0, 0, 0.03); border-left: 4px solid transparent;">
                <p style="font-size: 0.75rem; letter-spacing: 0.15em; text-transform: uppercase; margin-bottom: 0.5rem; color: var(--text-tertiary);">Step 3</p>
                <h3 style="margin: 0; font-size: 1rem; font-weight: 700;">Checkout</h3>
            </div>
            <div style="text-align: center; padding: 1rem; background: rgba(0, 0, 0, 0.03); border-left: 4px solid transparent;">
                <p style="font-size: 0.75rem; letter-spacing: 0.15em; text-transform: uppercase; margin-bottom: 0.5rem; color: var(--text-tertiary);">Step 4</p>
                <h3 style="margin: 0; font-size: 1rem; font-weight: 700;">Get Tickets</h3>
            </div>
        </div>
    </div>

    <div style="margin-bottom: 3rem;">
        <p style="color: var(--accent-primary); font-weight: 700; text-transform: uppercase; font-size: 0.875rem; letter-spacing: 0.1em; margin-bottom: 0.5rem;">Ticket Selection</p>
        <h1 class="page-title" style="font-size: 3.5rem;">CHOOSE YOUR TICKETS</h1>
        <p style="color: var(--text-secondary); font-size: 1.1rem; margin-top: 1rem;">{{ $concert->title }} • {{ $concert->date->format('M d, Y') }} • {{ $concert->venue->name }}</p>
    </div>

    <div class="grid-2 gap-8">
        <!-- MAIN: TICKET SELECTION FORM -->
        <div class="card no-hover">
            <div class="card-header">
                <div>
                    <h3 class="card-title">TICKET TYPES</h3>
                    <p style="color: var(--text-secondary); font-size: 0.95rem;">Max 5 tickets per transaction</p>
                </div>
            </div>

            <form action="{{ route('bookings.store', $concert) }}" method="POST">
                @csrf
                @php $prices = $concert->ticketPrices->pluck('price', 'section'); @endphp

                <div class="card-body">
                    <!-- Capacity Check Warning -->
                    @php 
                        $totalSold = $concert->bookings->sum(fn($b) => $b->tickets->count());
                        $remaining = $concert->venue->capacity - $totalSold;
                    @endphp
                    @if($remaining <= 0)
                        <div style="background: #fee2e2; border: 1px solid #fecaca; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1.5rem; text-align: center;">
                            <p style="color: #dc2626; font-weight: 600; margin: 0;">🎫 SOLD OUT</p>
                            <p style="color: #991b1b; margin-top: 0.5rem;">This event has reached venue capacity.</p>
                        </div>
                        <div class="card-footer">
                            <button type="button" class="btn btn-secondary w-full" disabled>Tickets Unavailable</button>
                        </div>
                    @else
                        <!-- Ticket Type Selection -->
                        <div style="margin-bottom: 1.5rem;">
                            <label for="ticket_type" style="display: block; font-weight: 700; margin-bottom: 0.5rem; color: var(--text-primary);">Ticket Type</label>
                            <select id="ticket_type" name="ticket_type" required style="width: 100%; padding: 0.75rem 1rem; border: 1px solid rgba(0,0,0,0.12); border-radius: 0.25rem; font-size: 0.95rem;">
                                <option value="">Select ticket type</option>
                                @foreach($prices as $type => $price)
                                    <option value="{{ $type }}" data-price="{{ $price }}"> {{ $type }} - ${{ number_format($price, 2) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Quantity Selection (for VIP and Gen Ad) -->
                        <div id="quantity-section" style="margin-bottom: 1.5rem; display: none;">
                            <label for="ticket_quantity" style="display: block; font-weight: 700; margin-bottom: 0.5rem; color: var(--text-primary);">Quantity</label>
                            <div style="display: grid; grid-template-columns: 1fr 150px; gap: 0.75rem; align-items: flex-end; margin-bottom: 1.5rem;">
                                <select id="ticket_quantity" name="ticket_quantity" style="width: 100%; padding: 0.75rem 1rem; border: 1px solid rgba(0,0,0,0.12); border-radius: 0.25rem; font-size: 0.95rem;">
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                    <option value="5">5</option>
                                </select>
                                <button type="button" id="add-quantity-ticket-btn" style="padding: 0.75rem 1rem; background: var(--accent-primary); color: white; border: none; border-radius: 0.25rem; font-weight: 700; cursor: pointer; white-space: nowrap; width: 100%;">+ ADD TICKET</button>
                            </div>
                        </div>

                        <!-- Seat Selection (for UBB and LBB) -->
                        <div id="seat-selection" style="margin-bottom: 1.5rem; display: none;">
                            <label for="seat_dropdown" style="display: block; font-weight: 700; margin-bottom: 0.5rem; color: var(--text-primary);">Select Seat</label>
                            <div style="display: grid; grid-template-columns: 1fr 150px; gap: 0.75rem; align-items: flex-end; margin-bottom: 1.5rem;">
                                <select id="seat_dropdown" name="seat_dropdown" style="width: 100%; padding: 0.75rem 1rem; border: 1px solid rgba(0,0,0,0.12); border-radius: 0.25rem; font-size: 0.95rem; color: var(--text-primary);">
                                    <option value="">Choose a seat...</option>
                                </select>
                                <button type="button" id="add-seat-ticket-btn" style="padding: 0.75rem 1rem; background: var(--accent-primary); color: white; border: none; border-radius: 0.25rem; font-weight: 700; cursor: pointer; white-space: nowrap; width: 100%;">+ ADD TICKET</button>
                            </div>
                        </div>

                        <!-- Added Tickets List -->
                        <div id="added-tickets" style="margin-bottom: 1rem; padding: 0.85rem; background: rgba(255, 102, 0, 0.05); border-left: 4px solid var(--accent-primary); border-radius: 0.5rem; display: none;">
                            <h4 style="font-weight: 700; margin-bottom: 0.75rem; color: var(--text-primary); font-size: 1rem;">Selected Tickets</h4>
                            <div id="tickets-list" style="display: flex; flex-direction: column; gap: 0.5rem;"></div>
                        </div>

                        <div id="total-preview" style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(148, 163, 184, 0.12); border-radius: 0.5rem; padding: 1rem; text-align: center; margin-bottom: 1rem; display: none;">
                            <div style="font-size: 1.25rem; font-weight: 700; color: var(--accent-primary); margin-bottom: 0.25rem;" id="total-price">$0.00</div>
                            <div style="font-size: 0.875rem; color: var(--text-secondary);" id="total-details"></div>
                            <div id="selected-seats-summary" style="font-size: 0.875rem; color: var(--text-secondary); margin-top: 0.5rem; display: none;">
                                <strong>Seats:</strong> <span id="seats-summary-text"></span>
                            </div>
                        </div>

                        <!-- Hidden inputs for cart items -->
                        <input type="hidden" id="cart_items" name="cart_items" value="">

                        <div style="text-align: center; color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 1.5rem;">
                            <strong>Max 5 tickets per purchase</strong> | Tickets are assigned randomly within your selected type
                        </div>
                    @endif
                </div>

                <div class="card-footer">
                    <button id="checkout-button" type="submit" class="btn btn-primary w-full" style="font-weight: 700; letter-spacing: 0.05em;" disabled>SELECT TICKET TYPE</button>
                </div>
            </form>
        </div>

        <!-- SIDEBAR: EVENT INFO -->
        <div class="card no-hover">
            <div class="card-header">
                <h3 class="card-title">EVENT INFO</h3>
            </div>

            <div class="card-body">
                <div style="border-left: 3px solid var(--accent-primary); padding-left: 1.5rem; margin-bottom: 2rem;">
                    <h4 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem;">{{ $concert->title }}</h4>
                    <p style="color: var(--accent-secondary); font-weight: 600; margin-bottom: 1rem;">by {{ $concert->artist }}</p>
                    
                    <div style="display: flex; flex-direction: column; gap: 0.75rem; color: var(--text-secondary); font-size: 0.95rem;">
                        <div style="display: flex; justify-content: space-between;">
                            <span>Date</span>
                            <span>{{ $concert->date->format('M d, Y') }}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>Time</span>
                            <span>{{ $concert->time->format('g:i A') }}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>Venue</span>
                            <span>{{ $concert->venue->name }}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>Capacity</span>
                            <span>{{ $concert->venue->capacity }} seats</span>
                        </div>
                    </div>
                </div>

                @if($concert->seat_plan_image)
                    <div style="margin-bottom: 1.5rem;">
                        <img src="{{ asset('storage/' . $concert->seat_plan_image) }}" alt="Seat Plan" style="width: 100%; height: auto; border-radius: 0.5rem;" />
                        <p style="font-size: 0.8rem; color: var(--text-secondary); text-align: center; margin-top: 0.5rem;">Reference seat plan - tickets assigned within type</p>
                    </div>
                @else
                    <div style="margin-bottom: 1.5rem; padding: 1rem; border: 1px dashed rgba(148, 163, 184, 0.5); border-radius: 0.5rem; background: rgba(255,255,255,0.02); text-align: center; color: var(--text-secondary);">
                        <p style="margin: 0; font-weight: 700;">No Seat Plan Available</p>
                        <p style="margin: 0.5rem 0 0; font-size: 0.85rem;">Upload a seat plan image in the concert admin panel to show it here.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div style="margin-top: 1.5rem;">
        <div class="card no-hover">
            <div class="card-header">
                <h3 class="card-title">All Available Prices</h3>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    @foreach($prices as $type => $price)
                        <div style="display: flex; justify-content: space-between; padding: 0.85rem 1rem; background: rgba(255, 255, 255, 0.03); border-radius: 0.5rem;">
                            <span>{{ $type }}</span>
                            <span style="font-weight: 700;">${{ number_format($price, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const ticketTypeSelect = document.getElementById('ticket_type');
            const quantitySelect = document.getElementById('ticket_quantity');
            const quantitySection = document.getElementById('quantity-section');
            const seatSelection = document.getElementById('seat-selection');
            const seatDropdown = document.getElementById('seat_dropdown');
            const addQuantityTicketBtn = document.getElementById('add-quantity-ticket-btn');
            const addSeatTicketBtn = document.getElementById('add-seat-ticket-btn');
            const addedTicketsDiv = document.getElementById('added-tickets');
            const ticketsList = document.getElementById('tickets-list');
            const cartItemsInput = document.getElementById('cart_items');
            const totalPreview = document.getElementById('total-preview');
            const totalPriceEl = document.getElementById('total-price');
            const totalDetailsEl = document.getElementById('total-details');
            const selectedSeatsSummary = document.getElementById('selected-seats-summary');
            const seatsSummaryText = document.getElementById('seats-summary-text');
            const checkoutButton = document.getElementById('checkout-button');

            let seatsData = [];
            let cartItems = [];
            const concertId = {{ $concert->id }};
            const maxTickets = 5;
            const seatSections = ['Lower Box B (LBB)', 'Upper Box B (UBB)'];

            function getCartQuantity() {
                return cartItems.reduce((sum, item) => sum + (item.quantity ?? 1), 0);
            }

            function getTicketPrice(type) {
                const option = Array.from(ticketTypeSelect.options).find(opt => opt.value === type);
                return option ? parseFloat(option.dataset.price) : 0;
            }

            // Load seats data
            async function loadSeats() {
                try {
                    const response = await fetch(`/concerts/${concertId}/seats`);
                    seatsData = await response.json();
                    populateSeatDropdown();
                } catch (error) {
                    console.error('Error loading seats:', error);
                }
            }

            // Populate seat dropdown
            function populateSeatDropdown() {
                const ticketType = ticketTypeSelect.value;
                seatDropdown.innerHTML = '<option value="">Choose a seat...</option>';

                const availableSeats = seatsData.filter(seat => {
                    const sectionMatch = seatSections.includes(ticketType) && seat.section === ticketType;
                    const isAvailable = seat.status === 'available';
                    const notAdded = !cartItems.some(item => item.seat_id == seat.id);
                    return sectionMatch && isAvailable && notAdded;
                });

                availableSeats.forEach(seat => {
                    const option = document.createElement('option');
                    option.value = JSON.stringify({ id: seat.id, number: seat.seat_number });
                    option.textContent = `${seat.section} - Seat ${seat.seat_number}`;
                    seatDropdown.appendChild(option);
                });
            }

            function updateCartInput() {
                cartItemsInput.value = JSON.stringify(cartItems);
            }

            // Add ticket to the cart
            function addTicket() {
                const type = ticketTypeSelect.value;
                if (!type) {
                    alert('Please select a ticket type');
                    return;
                }

                const currentQuantity = getCartQuantity();
                if (currentQuantity >= maxTickets) {
                    alert(`Maximum ${maxTickets} tickets per booking`);
                    return;
                }

                if (seatSections.includes(type)) {
                    if (!seatDropdown.value) {
                        alert('Please select a seat');
                        return;
                    }

                    if (currentQuantity + 1 > maxTickets) {
                        alert(`Total tickets cannot exceed ${maxTickets}`);
                        return;
                    }

                    const seatData = JSON.parse(seatDropdown.value);
                    cartItems.push({
                        ticket_type: type,
                        seat_id: seatData.id,
                        seat_number: seatData.number
                    });
                    populateSeatDropdown();
                } else {
                    const quantity = parseInt(quantitySelect.value, 10);
                    if (quantity < 1) {
                        alert('Please choose at least one ticket');
                        return;
                    }

                    if (currentQuantity + quantity > maxTickets) {
                        alert(`Total tickets cannot exceed ${maxTickets}`);
                        return;
                    }

                    const existingIndex = cartItems.findIndex(item => item.ticket_type === type && item.quantity);
                    if (existingIndex !== -1) {
                        cartItems[existingIndex].quantity += quantity;
                    } else {
                        cartItems.push({
                            ticket_type: type,
                            quantity: quantity
                        });
                    }
                }

                updateCartInput();
                updateCartDisplay();
                updatePreview();
            }

            // Update tickets display
            function updateCartDisplay() {
                ticketsList.innerHTML = '';
                if (cartItems.length > 0) {
                    cartItems.forEach((item, index) => {
                        const ticketTag = document.createElement('div');
                        ticketTag.style.cssText = 'display: flex; justify-content: space-between; align-items: center; background: rgba(255, 255, 255, 0.5); padding: 0.5rem 0.75rem; border-radius: 0.25rem; border-left: 3px solid var(--accent-primary); gap: 0.75rem; border: 1px solid rgba(0,0,0,0.12);';
                        
                        const ticketInfo = document.createElement('span');
                        ticketInfo.style.cssText = 'font-weight: 600; color: var(--text-primary); flex: 1; font-size: 0.95rem;';
                        ticketInfo.textContent = item.quantity ? `${item.quantity} × ${item.ticket_type}` : `${item.ticket_type} - Seat ${item.seat_number}`;
                        
                        const removeBtn = document.createElement('button');
                        removeBtn.type = 'button';
                        removeBtn.className = 'remove-ticket-btn';
                        removeBtn.dataset.index = index;
                        removeBtn.setAttribute('aria-label', 'Remove ticket');
                        removeBtn.style.cssText = 'background: var(--accent-primary); color: white; border: none; padding: 0.35rem; border-radius: 0.25rem; cursor: pointer; font-size: 1rem; font-weight: 700; line-height: 1; width: 2rem; height: 2rem; display: inline-flex; align-items: center; justify-content: center;';
                        removeBtn.textContent = '×';

                        ticketTag.appendChild(ticketInfo);
                        ticketTag.appendChild(removeBtn);
                        ticketsList.appendChild(ticketTag);
                    });
                    addedTicketsDiv.style.display = 'block';

                    document.querySelectorAll('.remove-ticket-btn').forEach(btn => {
                        btn.addEventListener('click', (e) => {
                            const index = parseInt(e.target.dataset.index, 10);
                            cartItems.splice(index, 1);
                            populateSeatDropdown();
                            updateCartDisplay();
                            updatePreview();
                            updateCartInput();
                        });
                    });
                } else {
                    addedTicketsDiv.style.display = 'none';
                }

                updateCartInput();
            }

            // Update preview
            function updatePreview() {
                const totalQuantity = getCartQuantity();
                if (totalQuantity === 0) {
                    totalPreview.style.display = 'none';
                    checkoutButton.disabled = true;
                    checkoutButton.textContent = 'ADD TICKETS';
                    return;
                }

                const total = cartItems.reduce((sum, item) => {
                    const price = getTicketPrice(item.ticket_type);
                    return sum + price * (item.quantity ?? 1);
                }, 0);

                const details = cartItems.map(item => {
                    return item.quantity ? `${item.quantity} × ${item.ticket_type}` : `${item.ticket_type} - Seat ${item.seat_number}`;
                }).join(', ');

                const seatItems = cartItems.filter(item => item.seat_id).map(item => `Seat ${item.seat_number}`);

                totalPriceEl.textContent = `$${total.toFixed(2)}`;
                totalDetailsEl.textContent = details;
                selectedSeatsSummary.style.display = seatItems.length ? 'block' : 'none';
                seatsSummaryText.textContent = seatItems.join(', ');
                totalPreview.style.display = 'block';
                checkoutButton.disabled = false;
                checkoutButton.textContent = `REVIEW ORDER ($${total.toFixed(2)})`;
            }

            // Handle ticket type change
            ticketTypeSelect.addEventListener('change', function() {
                const type = this.value;
                if (seatSections.includes(type)) {
                    quantitySection.style.display = 'none';
                    seatSelection.style.display = 'block';
                    loadSeats();
                } else if (type === 'VIP Standing' || type === 'General Admission (Gen Ad)') {
                    quantitySection.style.display = 'block';
                    seatSelection.style.display = 'none';
                } else {
                    quantitySection.style.display = 'none';
                    seatSelection.style.display = 'none';
                }

                updatePreview();
            });

            addQuantityTicketBtn.addEventListener('click', addTicket);
            addSeatTicketBtn.addEventListener('click', addTicket);

            updatePreview();
        });
    </script>

    <style>
        .card.no-hover:hover {
            transform: none !important;
            box-shadow: var(--shadow-md) !important;
        }
        .card.no-hover:hover::after {
            opacity: 0 !important;
        }
    </style>
</x-app-layout>
