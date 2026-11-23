<section class="admin-page" id="reviews">
    <div class="page-section-heading">
        <div>
            <h2>Product reviews</h2>
            <p>Review customer feedback, approve it for the storefront and add a store response.</p>
        </div><span class="badge text-bg-warning">{{ $actionCounts['reviews'] }} awaiting approval</span>
    </div>
    <form class="admin-review-filter" method="GET" action="{{ url('/admin') }}#reviews"><label>Review status<select
                class="form-select" name="review_status">
                @foreach (['all' => 'All reviews', 'pending' => 'Pending approval', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)
                <option value="{{ $value }}" @selected($reviewFilter===$value)>{{ $label }}</option>
                @endforeach
            </select>
        </label><button class="btn btn-primary">Apply filter</button><a href="{{ url('/admin') }}#reviews"
            class="btn btn-light">Reset</a><small>Latest 100 matching reviews</small></form>
    @forelse($reviewQueue as $r)
    <article class="editor-card admin-review-card" data-search-row>
        <div class="admin-review-heading">
            <div><span
                    class="admin-review-stars">{{ str_repeat('★', $r->rating) }}{{ str_repeat('☆', 5 - $r->rating) }}</span>
                <h3>{{ $r->product }}</h3>
                <p>{{ $r->customer }} · {{ \Illuminate\Support\Carbon::parse($r->created_at)->format('d M Y') }}
                </p>
            </div><span
                class="order-status {{ $r->status === 'approved' ? 'status-delivered' : ($r->status === 'rejected' ? 'status-cancelled' : 'status-placed') }}">{{ ucfirst($r->status) }}</span>
        </div>
        <h4>{{ $r->title }}</h4>
        <p class="admin-review-body">{{ $r->body }}</p>
        <form method="POST" action="{{ url('/admin/reviews/' . $r->id) }}">@csrf<input type="hidden"
                name="_section" value="reviews">
            <div class="editor"><label>Moderation status<select name="status" class="form-select">
                        @foreach (['pending', 'approved', 'rejected'] as $s)
                        <option @selected($r->status === $s)>{{ $s }}</option>
                        @endforeach
                    </select>
                </label><label style="grid-column:span 2">Public store reply
                    <textarea class="form-control" name="reply" maxlength="2000" rows="3">{{ $r->reply }}</textarea>
                </label></div><button type="submit" class="btn btn-primary">Save review decision</button>
        </form>
    </article>@empty<div class="card dashboard-empty"><i class="bi bi-chat-square-heart" aria-hidden="true"></i>
        <h4>No reviews in this view.</h4>
        <p>Customer reviews will appear here after submission.</p>
    </div>
    @endforelse
</section>
<section class="admin-page" id="returns">
    <h2>Return requests</h2>
    <p>Approve, receive and record refunds. Refunds must be processed separately before entering their reference.</p>
    @forelse($returnQueue as $r)
    <details class="editor-card" data-search-row>
        <summary>{{ $r->reference }} · {{ $r->customer }} · {{ $r->status }}</summary>
        <p>{{ $r->reason }} — {{ $r->notes }}</p>
        @foreach (json_decode($r->items) as $line)
        <p>{{ $line->name ?? 'Item' }} × {{ $line->quantity }}</p>
        @endforeach
        <form method="POST" action="{{ url('/admin/returns/' . $r->id) }}">
            @csrf<div class="editor"><label>Status<select class="form-select" name="status">
                        @foreach (['requested', 'approved', 'rejected', 'received', 'completed'] as $s)
                        <option @selected($r->status === $s)>{{ $s }}</option>
                        @endforeach
                    </select>
                </label><label>Refund amount ₹<input class="form-control" name="refund_amount" type="number"
                        min="0" value="{{ $r->refund_amount }}" required></label><label>Refund
                    reference<input class="form-control" name="refund_reference"
                        value="{{ $r->refund_reference }}"></label></div><label class="d-block my-3">Admin note
                <textarea class="form-control" name="admin_note">{{ $r->admin_note }}</textarea>
            </label>
            @if (!$r->restocked_at)
            <label class="d-block my-3"><input type="checkbox" name="restock" value="1"> Restock received
                items (once only)</label>@else<p>Restocked {{ $r->restocked_at }}</p>
            @endif
            <button type="submit" class="btn btn-primary">
                Update return</button>
        </form>
    </details>@empty<p>No return requests yet.</p>
    @endforelse
</section>
<section class="admin-page" id="pages">
    <h2>Store content pages</h2>
    @foreach ($contentPages as $page)
    <details class="editor-card" data-search-row>
        <summary>{{ $page->title }}</summary>
        <form method="POST" action="{{ url('/admin/pages/' . $page->id) }}">@csrf<label
                class="d-block my-3">Title<input class="form-control" name="title" value="{{ $page->title }}"
                    required></label><label class="d-block my-3">Content (plain text; use ## Heading for sections)
                <textarea class="form-control" rows="10" name="body" required>{{ $page->body }}</textarea>
            </label>@include('admin.seo-fields', ['seoRecord' => $page])<label>Published<select class="form-select" name="active">
                    <option value="1" @selected($page->active)>Yes</option>
                    <option value="0" @selected(!$page->active)>No</option>
                </select></label><button type="submit" class="btn btn-primary mt-3">Save page</button></form>
    </details>
    @endforeach
</section>
<section class="admin-page" id="support">
    <h2>Support requests</h2>
    @forelse($inquiries as $i)
    <div class="editor-card" data-search-row>
        <h3>#{{ $i->id }} · {{ $i->subject }}</h3>
        <p>{{ $i->name }} · {{ $i->email }} · {{ $i->created_at }}</p>
        <p style="white-space:pre-wrap">{{ $i->message }}</p>
        <form method="POST" action="{{ url('/admin/support/' . $i->id) }}">@csrf<select class="form-select"
                name="status">
                <option @selected($i->status === 'open')>open</option>
                <option @selected($i->status === 'resolved')>resolved</option>
            </select><button type="submit" class="btn btn-primary mt-2">Save status</button></form>
    </div>@empty<p>No support requests yet.</p>
    @endforelse
</section>
<section class="admin-page" id="store-settings">
    <h2>Store settings</h2>
    <form class="editor-card" method="POST" action="{{ url('/admin/store-settings') }}">@csrf<div class="editor">
            @foreach (['store_name' => 'Store name', 'shipping_fee' => 'Shipping fee ₹', 'free_shipping_minimum' => 'Free shipping minimum ₹', 'return_days' => 'Return window in days', 'support_email' => 'Support email', 'support_phone' => 'Support phone'] as $key => $label)
            <label>{{ $label }}<input class="form-control" name="{{ $key }}"
                    value="{{ $storeSettings[$key] }}" @required(in_array($key, ['store_name', 'shipping_fee' , 'free_shipping_minimum' , 'return_days' ]))></label>
            @endforeach
            <label>
                Cash on delivery<select class="form-select" name="cod_enabled">
                    <option value="1" @selected($storeSettings['cod_enabled'])>Enabled</option>
                    <option value="0" @selected(!$storeSettings['cod_enabled'])>Disabled</option>
                </select></label>
        </div><label class="d-block my-3">Business address
            <textarea class="form-control" name="business_address">{{ $storeSettings['business_address'] }}</textarea>
        </label>
        <details class="border rounded p-3 my-3">
            <summary>Storefront SEO: home, shop, categories & offers</summary>
            <p class="text-secondary small mt-3">Leave blank for automatic metadata. Keywords are optional.</p>
            @foreach (['home' => 'Home', 'shop' => 'Shop', 'categories' => 'Category directory', 'offers' => 'Offers'] as $seoKey => $seoLabel)
            <fieldset class="border rounded p-3 my-3">
                <legend class="float-none w-auto fs-6 px-2">{{ $seoLabel }}</legend>
                @foreach (['title' => 150, 'description' => 320, 'keywords' => 500] as $seoField => $seoMax)
                <label class="d-block mb-3">SEO {{ $seoField }}<input class="form-control"
                        name="seo_{{ $seoKey }}_{{ $seoField }}" maxlength="{{ $seoMax }}"
                        value="{{ $storeSettings['seo_' . $seoKey . '_' . $seoField] ?? '' }}"></label>
                @endforeach
            </fieldset>
            @endforeach
        </details><button type="submit" class="btn btn-primary">Save store settings</button>
    </form>
</section>