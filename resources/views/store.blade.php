<!DOCTYPE html>
<html lang="en">

<head>
    @include('market.favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="store-base" content="{{ url('/') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AVELLE — Find your next favourite</title>
    <meta name="description"
        content="Style, beauty and everyday favourites for women. Explore AVELLE's curated collections and live offers.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('style.css') }}?v=4">
</head>

<body>
    <div class="utility-bar"><span>Thoughtful finds for every version of you.</span><span>Free shipping above ₹1,499
            <i>•</i> Cash on delivery</span></div>
    <header class="store-header">
        <div class="header-inner"><a class="logo" href="{{ url('/') }}"
                aria-label="Avelle home">AVELLE<span>®</span></a>
            <div class="header-search" id="searchBar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="10.5" cy="10.5" r="6.5" />
                    <path d="m16 16 4.5 4.5" />
                </svg><input id="search" type="search" placeholder="Search for your next favourite…"
                    aria-label="Search products"><button id="clearSearch" aria-label="Clear search">×</button></div>
            <div class="header-actions"><a href="{{ url('/account') }}" id="accountLink" aria-label="My account"><svg
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="8" r="4" />
                        <path d="M4 22v-2a8 8 0 0 1 16 0v2" />
                    </svg><span id="accountLabel">{{ auth()->check() ? 'My account' : 'Sign in' }}</span></a><button
                    id="wishToggle" aria-label="Wishlist"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M20.5 5.5a5.5 5.5 0 0 0-8 0 5.5 5.5 0 0 0-8 8L12 21l7.5-7.5a5.5 5.5 0 0 0 1-8Z" />
                    </svg><span>Saved</span><small id="wishCount">0</small></button><button id="cartToggle"
                    aria-label="Open shopping bag"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M5 7h14l1 14H4L5 7Z" />
                        <path d="M8 8V6a4 4 0 0 1 8 0v2" />
                    </svg><span>Bag</span><small id="cartCount">0</small></button></div>
        </div>
        <nav class="main-nav" aria-label="Main navigation">
            <div><button data-category="All" class="active">Discover</button><button data-category="Ethnic">Indian
                    wear</button><button data-category="Western">Western wear</button><button
                    data-category="Beauty">Beauty & care</button><button
                    data-category="Accessories">Bags</button><button data-category="Jewellery">Jewellery</button><button
                    data-category="Footwear">Footwear</button></div><a href="#offers" class="nav-offers"><span
                    class="live-dot" id="offerNavDot" hidden></span>Offers for you ↗</a>
        </nav>
    </header>
    <div class="offer-marquee" id="offerMarquee" hidden><span class="marquee-label"><span class="live-dot"></span> LIVE
            OFFERS</span>
        <div class="marquee-window">
            <div class="marquee-track" id="marqueeTrack"></div>
        </div><button id="pauseMarquee" aria-label="Pause offer animation" aria-pressed="false">Ⅱ</button>
    </div>
    <main>
        <section class="campaign wrap">
            <div class="campaign-main">
                <div class="campaign-copy"><span class="pill-label">THE EVERYDAY STYLE CLUB</span>
                    <h1>Good style.<br>Great finds.<br><span>All you.</span></h1>
                    <p>Fashion, beauty and the little extras.<br>Find something that feels like you.</p><button
                        class="primary" id="explore">Explore the collection <span>↗</span></button>
                    <div class="campaign-foot"><span>CURATED FOR YOUR EVERYDAY</span><span>01 / THE NEW EDIT</span>
                    </div>
                </div>
                <div class="campaign-photo"><img src="{{ asset('images/photo-1539109136881-3be0616acf4b.jpg') }}"
                        alt="Modern street style with a blue outfit and shoulder bag">
                    <div class="photo-note">Your next<br><em>main-character moment.</em></div>
                </div>
                <div class="vertical-label">AVELLE / EVERYDAY, BUT BETTER</div>
            </div>
            <div class="campaign-aside"><button class="aside-card accessories-edit" data-category="Accessories"><img
                        src="{{ asset('images/photo-1548036328-c9fa89d128fa.jpg') }}" alt="A black shoulder bag"><span
                        class="aside-eyebrow">THE FINISHING TOUCH</span><strong>Little details.<br>Big
                        energy.</strong><span class="aside-cta">Explore bags <i>↗</i></span></button><button
                    class="aside-card beauty-edit" data-category="Beauty">
                    <div><span class="aside-eyebrow">YOUR DAILY GLOW</span><strong>Make time<br>for you.</strong><span
                            class="aside-cta">Beauty & care ↗</span></div><img
                        src="{{ asset('images/photo-1608248543803-ba4f8c70ae0b.jpg') }}" alt="Skincare essentials">
                </button></div>
        </section>
        <section class="trust-row wrap">
            <div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M2 5h12v12H2zM14 10h4l4 5v2h-8" />
                    <circle cx="6" cy="18" r="2" />
                    <circle cx="18" cy="18" r="2" />
                </svg><span><b>A little extra, on us</b><small>Free delivery above ₹1,499</small></span></div>
            <div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M4 9h10a6 6 0 0 1 0 12h-3M8 4 3 9l5 5" />
                </svg><span><b>Changed your mind?</b><small>7-day return request window</small></span></div>
            <div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="m12 2 8 4v6c0 6-8 10-8 10S4 18 4 12V6l8-4Z" />
                    <path d="m8 12 3 3 5-6" />
                </svg><span><b>Shop with confidence</b><small>Pay cash on delivery</small></span></div>
            <div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20.5 5.5a5.5 5.5 0 0 0-8 0 5.5 5.5 0 0 0-8 8L12 21l7.5-7.5a5.5 5.5 0 0 0 1-8Z" />
                </svg><span><b>Made for your everyday</b><small>Style without the endless scroll</small></span></div>
        </section>
        <section class="section wrap" id="categories">
            <div class="section-heading">
                <div>
                    <div class="eyebrow">A WHOLE WORLD OF YOU</div>
                    <h2>Find your kind of favourite.</h2>
                </div><a href="#shop" class="text-link">Explore everything ↗</a>
            </div>
            <div class="category-grid">
                @foreach ([['Ethnic', 'Indian wear', 'photo-1610030469983-98e550d6193c'], ['Western', 'Western wear', 'photo-1515372039744-b8f02a3ae446'], ['Beauty', 'Beauty & care', 'photo-1608248543803-ba4f8c70ae0b'], ['Accessories', 'Bags & accessories', 'photo-1548036328-c9fa89d128fa'], ['Jewellery', 'Jewellery', 'photo-1611652022419-a9419f74343d'], ['Footwear', 'Footwear', 'photo-1549298916-b41d501d3772']] as $cat)
                <button class="category-card" data-category="{{ $cat[0] }}">
                    <div><img src="{{ asset('images/' . $cat[2] . '.jpg') }}" alt="{{ $cat[1] }}"
                            loading="lazy"><span>↗</span></div><strong>{{ $cat[1] }}</strong>
                </button>
                @endforeach
            </div>
        </section>
        <section class="offers-section wrap" id="offers" hidden>
            <div class="section-heading">
                <div>
                    <div class="eyebrow"><span class="live-dot"></span> GOOD FINDS. EVEN BETTER PRICES.</div>
                    <h2>A little something off.</h2>
                </div><span class="offer-status">Live offers · updated automatically</span>
            </div>
            <div class="offers-grid" id="offerCards"></div>
        </section>
        <section class="section wrap products-section" id="shop">
            <div class="section-heading">
                <div>
                    <div class="eyebrow">YOUR NEXT EVERYDAY FAVOURITES</div>
                    <h2 id="shopTitle">The current favourites.</h2>
                </div>
                <div class="shop-heading-right"><span id="resultCount"></span><select id="sort"
                        aria-label="Sort products">
                        <option value="featured">Recommended</option>
                        <option value="low">Price: low to high</option>
                        <option value="high">Price: high to low</option>
                        <option value="rating">Top rated</option>
                    </select></div>
            </div>
            <div class="filter-row">
                <div class="filter-tabs" id="filters"><button class="active" data-category="All">All
                        finds</button><button data-category="Ethnic">Indian wear</button><button
                        data-category="Western">Western wear</button><button
                        data-category="Beauty">Beauty</button><button data-category="Accessories">Bags</button><button
                        data-category="Jewellery">Jewellery</button><button data-category="Footwear">Footwear</button>
                </div><button id="budget">Under ₹999 <span>↗</span></button>
            </div>
            <div class="offer-filter-note" id="offerFilterNote" hidden></div>
            <div class="product-grid" id="productGrid" aria-live="polite"></div>
        </section>
        <section class="discovery-banner wrap">
            <div>
                <div class="eyebrow">STYLE THAT FITS YOUR EVERYDAY</div>
                <h2>Your mood.<br>Your style.<br><span>Your Avelle.</span></h2>
                <p>Small refreshes. New favourites.<br>All the reasons to make it yours.</p><button class="primary"
                    id="steals">Discover finds under ₹999 ↗</button>
            </div><img src="{{ asset('images/photo-1469334031218-e382a71b716b.jpg') }}"
                alt="A curated collection of colourful fashion" loading="lazy">
            <div class="banner-stamp">A LITTLE<br>MORE<br><b>YOU.</b></div>
        </section>
        <section class="membership wrap">
            <div><span class="eyebrow">YOUR OWN LITTLE CORNER</span>
                <h2>Great finds deserve a home.</h2>
                <p>Create an account to keep your profile and orders in one place.</p>
            </div><a class="primary" href="{{ url('/account') }}">Meet your Avelle account ↗</a>
        </section>
    </main>
    <footer>
        <div class="footer-main wrap">
            <div><a class="logo" href="{{ url('/') }}">AVELLE<span>®</span></a>
                <p>For every version of you.<br>Fashion. Beauty. Everyday favourites.</p>
            </div>
            <div><b>THE COLLECTION</b><button data-category="Ethnic">Indian wear</button><button
                    data-category="Western">Western wear</button><button data-category="Beauty">Beauty &
                    care</button><button data-category="Accessories">Bags & accessories</button></div>
            <div><b>YOUR AVELLE</b><a href="{{ url('/account') }}">My profile & orders</a><a
                    href="{{ url('/account/login') }}">Sign in</a><button data-info="shipping">Shipping &
                    returns</button><button data-info="contact">Contact</button></div>
            <div class="footer-message"><span>FIND YOUR<br>NEXT <em>favourite.</em></span><button
                    data-info="privacy">Privacy policy ↗</button></div>
        </div>
        <div class="footer-bottom wrap"><span>© 2026 AVELLE. Made for your everyday.</span><span>INDIA / INR ₹</span>
        </div>
    </footer>
    <dialog id="productDialog"><button class="close" aria-label="Close">×</button>
        <div id="productDetail"></div>
    </dialog>
    <dialog id="bagDialog" class="drawer">
        <div class="drawer-head">
            <h2>Your little joys <span id="drawerCount"></span></h2><button class="close"
                aria-label="Close bag">×</button>
        </div>
        <div id="bagContent"></div>
    </dialog>
    <dialog id="checkoutDialog"><button class="close" aria-label="Close checkout">×</button>
        <div class="checkout-inner">
            <div class="eyebrow">ONE LAST LITTLE STEP</div>
            <h2>Make it yours.</h2>
            <p id="checkoutMode"></p>
            <form id="checkoutForm"><label>Full name<input name="name" required maxlength="100"
                        autocomplete="name"></label>
                <div class="form-row"><label>Email<input name="email" type="email" required
                            autocomplete="email"></label><label>Mobile number<input name="phone" required
                            pattern="[6-9][0-9]{9}" title="10-digit Indian mobile number" autocomplete="tel"></label>
                </div><label>Delivery address
                    <textarea name="address" required maxlength="500" autocomplete="street-address"></textarea>
                </label>
                <div class="form-row"><label>City<input name="city" required maxlength="100"
                            autocomplete="address-level2"></label><label>PIN code<input name="pincode" required
                            pattern="[1-9][0-9]{5}" title="6-digit Indian PIN code"
                            autocomplete="postal-code"></label></div>
                <div class="checkout-account" id="checkoutAccountFields">
                    <h3>Your order. Your own account.</h3>
                    <p>Set a password to create your profile with this order. Already registered? <a
                            href="{{ url('/account/login') }}?checkout=1">Sign in first</a>.</p>
                    <div class="form-row"><label>Create password<input name="password" type="password"
                                minlength="10" autocomplete="new-password"><small>At least 10
                                characters</small></label><label>Confirm password<input name="password_confirmation"
                                type="password" minlength="10" autocomplete="new-password"></label></div>
                </div>
                <div class="payment-note">◉ Cash on delivery <span>Pay when your joy arrives.</span></div><button
                    class="primary full" id="placeOrder">Place order</button>
                <p id="orderError" role="alert"></p>
            </form>
        </div>
    </dialog>
    <dialog id="infoDialog"><button class="close" aria-label="Close">×</button>
        <div class="info-content" id="infoContent"></div>
    </dialog>
    <div id="toast" role="status"></div>
    <script id="storeAccount" type="application/json">
        @json($accountData)
    </script>
    <script id="initialOffers" type="application/json">
        @json($offers)
    </script>
    <script src="{{ asset('app.js') }}?v=4"></script>
</body>

</html>