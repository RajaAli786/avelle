"use strict";
const $ = (s) => document.querySelector(s),
  money = (n) => "₹" + Number(n).toLocaleString("en-IN"),
  esc = (s) =>
    String(s).replace(
      /[&<>"']/g,
      (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c],
    );
const baseUrl = ($('meta[name="store-base"]')?.content || "").replace(/\/$/, "");
const toUrl = (p) => baseUrl + p;
const live = Boolean($('meta[name="csrf-token"]').content);
const read = (key, fallback) => {
  try {
    return JSON.parse(localStorage.getItem(key)) || fallback;
  } catch {
    return fallback;
  }
};
let products = [],
  cart = read("noor-cart", []),
  wishlist = read("noor-wishlist", []),
  category = "All",
  budget = false,
  showWish = false,
  query = "",
  selectedSize = "",
  currentProduct = null,
  promotion = null;
let account = JSON.parse($("#storeAccount")?.textContent || "null"),
  offers = JSON.parse($("#initialOffers")?.textContent || "[]"),
  offerFilter = null,
  pendingOfferCode = "";
let toastTimer;
const toast = (message) => {
  $("#toast").textContent = message;
  $("#toast").classList.add("show");
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => $("#toast").classList.remove("show"), 3200);
};
const save = () => {
  localStorage.setItem("noor-cart", JSON.stringify(cart));
  localStorage.setItem("noor-wishlist", JSON.stringify(wishlist));
  $("#cartCount").textContent = cart.reduce((n, i) => n + i.quantity, 0);
  $("#wishCount").textContent = wishlist.length;
};
const bundled = [
  "photo-1610030469983-98e550d6193c",
  "photo-1434389677669-e08b4cac3105",
  "photo-1548036328-c9fa89d128fa",
  "photo-1515372039744-b8f02a3ae446",
  "photo-1608248543803-ba4f8c70ae0b",
  "photo-1611652022419-a9419f74343d",
  "photo-1549298916-b41d501d3772",
  "photo-1483985988355-763728e1935b",
];
const image = (p, w = 600) =>
  bundled.includes(p.image)
    ? toUrl("/images/" + p.image + ".jpg")
    : "https://images.unsplash.com/" + encodeURIComponent(p.image) + "?w=" + w + "&auto=format&fit=crop&q=85";

function matchesOffer(p, o) {
  return (
    o.scope === "store" ||
    (o.scope === "category" && o.targets.includes(p.category)) ||
    (o.scope === "product" && o.targets.map(String).includes(String(p.id)))
  );
}
function currentOffers() {
  return offers.filter((o) => !o.expires_at || new Date(o.expires_at) > new Date());
}
function renderOffers() {
  offers = currentOffers();
  const moving = offers.filter((o) => ["both", "marquee"].includes(o.mode));
  $("#offerMarquee").hidden = !moving.length;
  $("#offers").hidden = !offers.length;
  $("#offerNavDot").hidden = !offers.length;
  $("#offerNavDot").classList.toggle(
    "pulse-offer",
    offers.some((o) => ["both", "pulse"].includes(o.mode)),
  );
  const group = moving
    .map(
      (o) =>
        '<button data-offer="' +
        esc(o.code) +
        '">' +
        esc(o.title) +
        " <strong>· " +
        esc(o.code) +
        "</strong></button>",
    )
    .join("");
  $("#marqueeTrack").innerHTML = group
    ? '<div class="marquee-group">' +
      group +
      '</div><div class="marquee-group" aria-hidden="true">' +
      group.replaceAll("<button ", '<button tabindex="-1" ') +
      "</div>"
    : "";
  $("#offerCards").innerHTML = offers
    .map(
      (o) =>
        '<article class="offer-card ' +
        (["both", "pulse"].includes(o.mode) ? "pulse-offer" : "") +
        '"><div><div class="offer-saving">' +
        esc(o.saving) +
        "</div><p>" +
        esc(o.title) +
        "<br>" +
        esc(o.scope_label) +
        (o.minimum ? " · Min " + money(o.minimum) : "") +
        (o.maximum ? " · Up to " + money(o.maximum) : "") +
        (o.expires_at
          ? "<br>Until " +
            esc(
              new Date(o.expires_at).toLocaleString("en-IN", {
                timeZone: "Asia/Kolkata",
                day: "numeric",
                month: "short",
                hour: "2-digit",
                minute: "2-digit",
              }),
            ) +
            " IST"
          : "") +
        '</p><div class="offer-code">USE <button data-copy-code="' +
        esc(o.code) +
        '">' +
        esc(o.code) +
        ' ⧉</button></div></div><button data-offer="' +
        esc(o.code) +
        '">Shop<br>offer ↗</button></article>',
    )
    .join("");
  if (offerFilter && !offers.some((o) => o.code === offerFilter.code)) {
    offerFilter = null;
    pendingOfferCode = "";
  }
  $("#offerFilterNote").hidden = !offerFilter;
  if (offerFilter)
    $("#offerFilterNote").innerHTML =
      "Showing finds for " +
      esc(offerFilter.code) +
      " · " +
      esc(offerFilter.saving) +
      '<button id="clearOfferFilter">Clear offer filter ×</button>';
}
async function refreshOffers() {
  if (!live) return;
  try {
    const response = await fetch(toUrl("/api/offers"), {
      cache: "no-store",
      headers: { Accept: "application/json" },
    });
    if (!response.ok) return;
    offers = await response.json();
    renderOffers();
    render();
  } catch {}
}
function openCheckout() {
  $("#bagDialog").close();
  $("#checkoutMode").textContent = account
    ? "Your order will appear in your Avelle profile. Pay cash on delivery."
    : "Your account is created securely with this order. Already registered? Sign in using the link below.";
  $("#checkoutAccountFields").hidden = Boolean(account);
  $("#checkoutAccountFields")
    .querySelectorAll("input")
    .forEach((i) => {
      i.required = !account;
      i.disabled = Boolean(account);
    });
  if (account) {
    for (const key of ["name", "email", "phone", "address", "city", "pincode"]) {
      const el = $("#checkoutForm").elements[key];
      if (el) el.value = account[key] || "";
    }
  }
  $("#checkoutForm").elements.email.readOnly = Boolean(account);
  $("#placeOrder").textContent =
    (live ? "Place COD order · " : "Place demo order · ") + money(totals().total);
  $("#orderError").textContent = "";
  $("#checkoutDialog").showModal();
}

function render() {
  let list = products.filter(
    (p) =>
      (category === "All" || p.category === category) &&
      (!budget || p.price < 999) &&
      (!showWish || wishlist.includes(p.id)) &&
      (!query || (p.name + " " + p.category + " " + p.color).toLowerCase().includes(query)) &&
      (!offerFilter || matchesOffer(p, offerFilter)),
  );
  const sort = $("#sort").value;
  if (sort === "low") list.sort((a, b) => a.price - b.price);
  if (sort === "high") list.sort((a, b) => b.price - a.price);
  if (sort === "rating") list.sort((a, b) => b.rating - a.rating);
  $("#shopTitle").textContent = showWish
    ? "Saved with love"
    : query
      ? "Your search finds"
      : budget
        ? "Great finds under ₹999"
        : category === "All"
          ? "The current favourites."
          : category + " favourites";
  $("#resultCount").textContent = list.length + " finds";
  $("#filters")
    .querySelectorAll("button")
    .forEach((b) => b.classList.toggle("active", b.dataset.category === category));
  $("#budget").classList.toggle("active", budget);
  $("#productGrid").innerHTML = list.length
    ? list
        .map(
          (p) =>
            '<article class="product-card"><div class="product-image"><button data-product="' +
            p.id +
            '" aria-label="View ' +
            esc(p.name) +
            '"><img loading="lazy" src="' +
            image(p) +
            '" alt="' +
            esc(p.name) +
            '"></button><span class="product-tag">' +
            esc(p.tag) +
            '</span><button class="heart ' +
            (wishlist.includes(p.id) ? "saved" : "") +
            '" data-wish="' +
            p.id +
            '" aria-label="Save ' +
            esc(p.name) +
            '" aria-pressed="' +
            wishlist.includes(p.id) +
            '">' +
            (wishlist.includes(p.id) ? "♥" : "♡") +
            '</button><button class="quick-add" data-product="' +
            p.id +
            '" aria-label="Choose size for ' +
            esc(p.name) +
            '">+</button></div><div class="product-meta"><span>' +
            esc(p.category.toUpperCase()) +
            "</span><span>★ " +
            p.rating +
            '</span></div><h3><button data-product="' +
            p.id +
            '" style="padding:0;text-align:left">' +
            esc(p.name) +
            '</button></h3><div class="color">' +
            esc(p.color) +
            '</div><div class="price"><b>' +
            money(p.price) +
            "</b><del>" +
            money(p.old) +
            "</del><small>" +
            Math.max(0, Math.round((1 - p.price / p.old) * 100)) +
            "% off</small></div>" +
            (() => {
              const o = currentOffers().find((o) => matchesOffer(p, o));
              return o
                ? '<button class="product-offer ' +
                    (["both", "pulse"].includes(o.mode) ? "pulse-offer" : "") +
                    '" data-offer="' +
                    esc(o.code) +
                    '" title="' +
                    esc(o.title) +
                    '"><span class="live-dot"></span>' +
                    esc(o.saving) +
                    " · " +
                    esc(o.code) +
                    "</button>"
                : "";
            })() +
            "</article>",
        )
        .join("")
    : '<div class="empty">No finds here yet.<br><button id="resetFilters" style="text-decoration:underline;margin-top:15px">Explore all products</button></div>';
}
function filter(cat) {
  offerFilter = null;
  renderOffers();
  category = cat;
  showWish = false;
  query = "";
  $("#search").value = "";
  render();
  $("#shop").scrollIntoView();
}
function detail(id) {
  const p = products.find((x) => x.id === id);
  if (!p) return;
  currentProduct = p;
  selectedSize = p.sizes.length === 1 ? p.sizes[0] : "";
  $("#productDetail").innerHTML =
    '<div class="detail-grid"><img src="' +
    image(p, 900) +
    '" alt="' +
    esc(p.name) +
    '"><div class="detail-copy"><div class="eyebrow">' +
    esc(p.tag) +
    "</div><h2>" +
    esc(p.name) +
    "</h2><p>" +
    esc(p.color) +
    " · ★ " +
    p.rating +
    '</p><div class="price"><b>' +
    money(p.price) +
    "</b><del>" +
    money(p.old) +
    '</del></div><p>Thoughtfully selected for your everyday. Product photographs are illustrative; final material and fit details will be added before launch.</p><div class="size-label">Choose your size <span id="sizeError"></span></div><div class="sizes">' +
    p.sizes
      .map(
        (s) =>
          '<button data-size="' +
          esc(s) +
          '" class="' +
          (s === selectedSize ? "active" : "") +
          '">' +
          esc(s) +
          "</button>",
      )
      .join("") +
    '</div><button id="addToBag" class="primary full" ' +
    (!p.stock ? "disabled" : "") +
    ">" +
    (p.stock ? "Add a little joy to bag +" : "Currently sold out") +
    "</button><p>Cash on delivery · Shipping ₹79<br>Free shipping on orders ₹1,499+</p></div></div>";
  $("#productDialog").showModal();
}
function addToBag() {
  if (!selectedSize) {
    $("#sizeError").textContent = "— please select a size";
    return;
  }
  let item = cart.find((i) => i.id === currentProduct.id && i.size === selectedSize);
  const total = cart.filter((i) => i.id === currentProduct.id).reduce((n, i) => n + i.quantity, 0);
  if (total >= currentProduct.stock || (item && item.quantity >= 10)) {
    toast("Maximum available quantity reached");
    return;
  }
  if (item) item.quantity++;
  else cart.push({ id: currentProduct.id, size: selectedSize, quantity: 1 });
  promotion = null;
  save();
  $("#productDialog").close();
  toast("A little joy added to your bag ♡");
}
function totals() {
  const subtotal = cart.reduce(
      (n, i) => n + (products.find((p) => p.id === i.id)?.price || 0) * i.quantity,
      0,
    ),
    shipping = subtotal >= 1499 ? 0 : 79,
    discount = promotion?.discount || 0;
  return { subtotal, shipping, discount, total: subtotal + shipping - discount };
}
function bag() {
  save();
  $("#drawerCount").textContent = "(" + cart.reduce((n, i) => n + i.quantity, 0) + ")";
  if (!cart.length) {
    $("#bagContent").innerHTML =
      '<div class="empty">Your bag is waiting for a little joy.<br><button class="primary" id="continueShopping" style="margin-top:20px">Explore the good stuff ↗</button></div>';
    return;
  }
  const t = totals();
  $("#bagContent").innerHTML =
    cart
      .map((i, index) => {
        const p = products.find((p) => p.id === i.id);
        return (
          '<div class="bag-item"><img src="' +
          image(p, 200) +
          '" alt="' +
          esc(p.name) +
          '"><div><h3>' +
          esc(p.name) +
          "</h3><p>" +
          esc(i.size) +
          " · " +
          esc(p.color) +
          "</p><b>" +
          money(p.price) +
          '</b><div class="quantity"><button data-qty="' +
          index +
          '" data-delta="-1" aria-label="Decrease quantity">−</button><span>' +
          i.quantity +
          '</span><button data-qty="' +
          index +
          '" data-delta="1" aria-label="Increase quantity">+</button><button class="remove" data-remove="' +
          index +
          '">Remove</button></div></div></div>'
        );
      })
      .join("") +
    '<form id="promoForm" class="promo-form"><label for="promoCode">Coupon or referral code</label><div style="display:flex;gap:8px"><input id="promoCode" placeholder="Enter code" maxlength="40" value="' +
    esc(promotion?.code || pendingOfferCode || "") +
    '" style="width:100%"><button class="primary" style="padding:10px 15px">Apply</button></div><small>' +
    (promotion
      ? esc(promotion.code) + ' applied · <button type="button" id="removePromo">Remove</button>'
      : "Offers are verified at checkout.") +
    '</small></form><div class="bag-totals"><p><span>Subtotal</span><span>' +
    money(t.subtotal) +
    "</span></p><p><span>Shipping</span><span>" +
    (t.shipping ? money(t.shipping) : "On us ♡") +
    "</span></p>" +
    (t.discount ? "<p><span>Offer savings</span><span>−" + money(t.discount) + "</span></p>" : "") +
    '<p class="total"><b>Total</b><b>' +
    money(t.total) +
    "</b></p><small>" +
    (t.shipping
      ? "Add " + money(1499 - t.subtotal) + " more for free shipping."
      : "You unlocked free shipping.") +
    '</small><button class="primary full" id="checkoutButton">Continue to checkout ↗</button><small>Cash on delivery · ' +
    (live ? "Secure server-verified checkout" : "Demo preview — no real orders") +
    "</small></div>";
}
async function api(url, data) {
  const response = await fetch(toUrl(url), {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      "X-CSRF-TOKEN": $('meta[name="csrf-token"]').content,
    },
    body: JSON.stringify(data),
  });
  const result = await response.json();
  if (!response.ok)
    throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || "Please try again.");
  return result;
}
document.addEventListener("click", (e) => {
  const b = e.target.closest("button");
  if (!b) return;
  if (b.dataset.offer) {
    const o = offers.find((o) => o.code === b.dataset.offer);
    if (o) {
      offerFilter = o;
      category = "All";
      budget = false;
      query = "";
      showWish = false;
      $("#search").value = "";
      pendingOfferCode = o.code;
      renderOffers();
      render();
      $("#shop").scrollIntoView();
      toast("Use " + o.code + " in your bag. Offer conditions apply.");
    }
    return;
  }
  if (b.dataset.copyCode) {
    navigator.clipboard
      ?.writeText(b.dataset.copyCode)
      .then(() => toast("Copied " + b.dataset.copyCode))
      .catch(() => toast("Use code " + b.dataset.copyCode));
    return;
  }
  if (b.id === "clearOfferFilter") {
    offerFilter = null;
    renderOffers();
    render();
    return;
  }
  if (b.id === "pauseMarquee") {
    const paused = $("#offerMarquee").classList.toggle("paused");
    b.setAttribute("aria-pressed", String(paused));
    b.setAttribute("aria-label", paused ? "Resume offer animation" : "Pause offer animation");
    b.textContent = paused ? "▶" : "Ⅱ";
    return;
  }
  if (b.classList.contains("close")) b.closest("dialog").close();
  if (b.dataset.category) {
    budget = false;
    filter(b.dataset.category);
  }
  if (b.dataset.sale || b.id === "steals") {
    budget = true;
    filter("All");
  }
  if (b.dataset.product) detail(Number(b.dataset.product));
  if (b.dataset.wish) {
    const id = Number(b.dataset.wish);
    wishlist = wishlist.includes(id) ? wishlist.filter((x) => x !== id) : [...wishlist, id];
    save();
    render();
  }
  if (b.dataset.size) {
    selectedSize = b.dataset.size;
    document.querySelectorAll("[data-size]").forEach((x) => x.classList.toggle("active", x === b));
    $("#sizeError").textContent = "";
  }
  if (b.id === "addToBag") addToBag();
  if (b.id === "explore") $("#shop").scrollIntoView();
  if (b.id === "searchToggle") {
    $("#searchBar").hidden = !$("#searchBar").hidden;
    if (!$("#searchBar").hidden) $("#search").focus();
  }
  if (b.id === "clearSearch") {
    query = "";
    $("#search").value = "";
    render();
  }
  if (b.id === "wishToggle") {
    showWish = !showWish;
    render();
    $("#shop").scrollIntoView();
  }
  if (b.id === "cartToggle") {
    bag();
    $("#bagDialog").showModal();
  }
  if (b.dataset.qty !== undefined) {
    const i = cart[Number(b.dataset.qty)],
      p = products.find((x) => x.id === i.id),
      delta = Number(b.dataset.delta),
      total = cart.filter((x) => x.id === p.id).reduce((n, x) => n + x.quantity, 0);
    if (delta > 0 && (i.quantity >= 10 || total >= p.stock)) {
      toast("Maximum available quantity reached");
      return;
    }
    i.quantity += delta;
    cart = cart.filter((x) => x.quantity > 0);
    promotion = null;
    bag();
  }
  if (b.dataset.remove !== undefined) {
    cart.splice(Number(b.dataset.remove), 1);
    promotion = null;
    bag();
  }
  if (b.id === "removePromo") {
    promotion = null;
    bag();
  }
  if (b.id === "continueShopping") {
    $("#bagDialog").close();
    $("#shop").scrollIntoView();
  }
  if (b.id === "resetFilters") {
    budget = false;
    showWish = false;
    filter("All");
  }
  if (b.id === "checkoutButton") openCheckout();
  if (b.dataset.info) {
    const content = {
      shipping: [
        "Shipping & returns",
        "Standard shipping is ₹79, free for orders of ₹1,499 or more. The intended return window is 7 days after delivery. This is a pre-launch store; delivery timelines and a support address must be configured before real sales.",
      ],
      contact: [
        "Hello, lovely.",
        "AVELLE is being built. The store owner will add their support email and phone before launch.",
      ],
      privacy: [
        "Your privacy matters",
        "This preview saves your bag, wishlist and newsletter preference on this device. On the Laravel store, checkout details are stored in the store database to fulfil orders. No payment card details are collected.",
      ],
    }[b.dataset.info];
    $("#infoContent").innerHTML = "<h2>" + content[0] + "</h2><p>" + content[1] + "</p>";
    $("#infoDialog").showModal();
  }
});
document.addEventListener("submit", async (e) => {
  if (e.target.id !== "promoForm") return;
  e.preventDefault();
  const code = $("#promoCode").value.trim().toUpperCase();
  if (!code) return;
  try {
    if (live) {
      promotion = await api("/api/promotions/quote", { code, items: cart });
    } else {
      if (code !== "HELLO10") throw new Error("Preview code: HELLO10. Custom codes need the Laravel server.");
      const subtotal = totals().subtotal;
      if (subtotal < 499) throw new Error("Minimum order ₹499 required.");
      promotion = { code, discount: Math.min(300, Math.floor(subtotal * 0.1)) };
    }
    bag();
    toast("Your offer is applied");
  } catch (error) {
    toast(error.message);
  }
});
$("#search").addEventListener("input", (e) => {
  query = e.target.value.toLowerCase().trim();
  showWish = false;
  offerFilter = null;
  renderOffers();
  render();
  $("#shop").scrollIntoView({ behavior: "instant" });
});
$("#sort").addEventListener("change", render);
$("#budget").addEventListener("click", () => {
  budget = !budget;
  render();
});
$("#newsletter")?.addEventListener("submit", (e) => {
  e.preventDefault();
  localStorage.setItem("noor-newsletter", $("#newsletterEmail").value);
  toast("Saved on this device. Email delivery starts after launch.");
  e.target.reset();
});
$("#checkoutForm").addEventListener("submit", async (e) => {
  e.preventDefault();
  const button = $("#placeOrder");
  button.disabled = true;
  button.textContent = "Placing your order…";
  $("#orderError").textContent = "";
  try {
    const data = Object.fromEntries(new FormData(e.target));
    data.items = cart;
    data.code = promotion?.code || null;
    let result;
    if (live) result = await api("/api/orders", data);
    else result = { reference: "DEMO-" + Date.now().toString(36).toUpperCase(), total: totals().total };
    if (result.account) {
      account = result.account;
      $("#accountLabel").textContent = "My account";
    }
    if (result.csrf_token) $('meta[name="csrf-token"]').content = result.csrf_token;
    cart = [];
    promotion = null;
    pendingOfferCode = "";
    save();
    e.target.reset();
    $("#checkoutDialog").close();
    $("#infoContent").innerHTML =
      '<div class="eyebrow">' +
      (live ? "ORDER CONFIRMED" : "DEMO COMPLETE") +
      "</div><h2>A little joy is on its way" +
      (live ? "!" : " — almost!") +
      "</h2><p>" +
      esc(result.reference) +
      "</p><p>Total: " +
      money(result.total) +
      " · Cash on delivery</p><p>" +
      (live
        ? 'Your order is saved in your account. <a class="text-link" href="' +
          toUrl("/account") +
          '">View your profile & orders ↗</a>'
        : "This was a demo. No order was sent and no payment was taken.") +
      "</p>";
    $("#infoDialog").showModal();
  } catch (error) {
    $("#orderError").textContent = error.message;
  } finally {
    button.disabled = false;
    button.textContent = "Try placing order again";
  }
});
document.querySelectorAll("dialog").forEach((d) =>
  d.addEventListener("click", (e) => {
    if (e.target === d) {
      const r = d.getBoundingClientRect();
      if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) d.close();
    }
  }),
);
(async () => {
  try {
    const response = await fetch(live ? toUrl("/api/products") : "/products.json");
    if (!response.ok) throw Error();
    products = await response.json();
    products = products.map((p) => ({
      ...p,
      id: Number(p.id),
      price: Number(p.price),
      old: Number(p.old),
      stock: Number(p.stock),
      rating: Number(p.rating),
    }));
    cart = cart.filter((i) => products.some((p) => p.id === i.id && p.sizes.includes(i.size)));
    const known = [...$("#filters").querySelectorAll("[data-category]")].map((b) => b.dataset.category);
    [...new Set(products.map((p) => p.category))]
      .filter((c) => !known.includes(c))
      .forEach((c) => {
        const b = document.createElement("button");
        b.dataset.category = c;
        b.textContent = c;
        $("#filters").appendChild(b);
      });
    save();
    renderOffers();
    render();
    refreshOffers();
    if (new URLSearchParams(location.search).get("checkout") === "1" && cart.length) openCheckout();
  } catch {
    $("#productGrid").innerHTML =
      '<div class="empty">The good finds couldn’t load. Please refresh in a moment.</div>';
  }
})();

setInterval(() => {
  const before = offers.length;
  offers = currentOffers();
  if (offers.length !== before) {
    renderOffers();
    render();
  }
}, 1000);
setInterval(() => {
  if (!document.hidden) refreshOffers();
}, 30000);
document.addEventListener("visibilitychange", () => {
  if (!document.hidden) refreshOffers();
});
