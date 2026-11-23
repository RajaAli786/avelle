<div class="account-section-heading">
    <span class="eyebrow">YOUR EXPERIENCE, SOMEONE’S NEXT FAVOURITE</span>
    <h1>My reviews</h1>
    <p>Write about delivered purchases and see whether your review is pending or approved.</p>
</div>
<div class="account-info"><i class="bi bi-info-circle" aria-hidden="true"></i>
    <p>Reviews appear publicly after admin approval. Updating a review submits it for approval again.</p>
</div>
@forelse($reviewProducts as $reviewProduct) @php($mine = $myReviews->firstWhere('product_id', $reviewProduct->id))
    <article class="form-card account-review-card" id="review-product-{{ $reviewProduct->id }}">
        <div class="account-review-heading">
            <div><span class="account-reviewed-label"><i class="bi bi-patch-check" aria-hidden="true"></i> Verified
                    delivered purchase</span>
                <h2>{{ $reviewProduct->name }}</h2>
            </div><span
                class="account-state {{ $mine?->status ?? 'ready' }}">{{ $mine?->status === 'pending' ? 'Pending approval' : ($mine?->status === 'approved' ? 'Approved' : ($mine?->status === 'rejected' ? 'Needs an update' : 'Ready to review')) }}</span>
        </div>
        @if ($mine)
            <p class="account-review-state">
                {{ $mine->status === 'pending' ? 'Your review is saved. It will appear on the product page after approval.' : ($mine->status === 'approved' ? 'Your review is approved and visible on the product page.' : 'You can update your review and send it for approval again.') }}
            </p>
            @if ($mine->reply)
                <div class="review-reply"><b>Store response</b>
                    <p>{{ $mine->reply }}</p>
                </div>
            @endif
        @endif
        <details @if (!$mine || request('product') == $reviewProduct->id) open @endif>
            <summary>{{ $mine ? 'Edit your review' : 'Write your review' }}</summary>
            <form method="POST" action="{{ url('/products/' . $reviewProduct->id . '/reviews') }}">@csrf<input
                    type="hidden" name="account_reviews" value="1"><label>Rating<select name="rating" required>
                        @for ($rating = 5; $rating >= 1; $rating--)
                            <option value="{{ $rating }}" @selected((int) ($mine?->rating ?? 5) === $rating)>{{ $rating }} stars
                            </option>
                        @endfor
                    </select></label><label>Review title<input name="title" required maxlength="120"
                        value="{{ $mine?->title }}" placeholder="What stood out to you?"></label><label>Your review
                    <textarea name="body" required minlength="10" maxlength="2000" rows="4">{{ $mine?->body }}</textarea>
                </label><button
                    class="pink-button">{{ $mine ? 'Update & submit for approval' : 'Submit for approval' }}</button>
            </form>
        </details>
</article>@empty<div class="form-card account-empty"><i class="bi bi-chat-square-heart" aria-hidden="true"></i>
        <h2>Your reviews start after delivery.</h2>
        <p>Once a product from your order has been delivered, you can share your experience here.</p><a
            href="{{ url('/account/orders') }}" class="pink-button">View my orders →</a>
    </div>@endforelse @foreach ($myReviews->whereNotIn('product_id', $reviewProducts->pluck('id')) as $pastReview)
        <article class="form-card">
            <div class="account-review-heading">
                <h2>{{ $pastReview->product_name }}</h2><span
                    class="account-state {{ $pastReview->status }}">{{ ucfirst($pastReview->status) }}</span>
            </div>
            <p>{{ str_repeat('★', $pastReview->rating) }} · {{ $pastReview->title }}</p>
            <p>{{ $pastReview->body }}</p>
        </article>
    @endforeach
