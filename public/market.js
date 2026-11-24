"use strict";
const $ = (s) => document.querySelector(s),
  $$ = (s) => [...document.querySelectorAll(s)];
const ctx = JSON.parse($("#shopContext").textContent),
  base = ctx.base.replace(/\/$/, ""),
  url = (p) => base + p,
  money = (n) => "₹" + Number(n).toLocaleString("en-IN"),
  esc = (s) =>
    String(s ?? "").replace(
      /[&<>"']/g,
      (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c],
    );
const read = (k, d) => {
  try {
    return JSON.parse(localStorage.getItem(k)) ?? d;
  } catch {
    return d;
  }
};
let cart = read("avelle-cart-v5", read("noor-cart", [])),
  wishes = read("avelle-wishlist-v5", read("noor-wishlist", [])),
  code = read("avelle-coupon-v5", ""),
  offers = ctx.offers || [],
  quote = null,
  quoteError = "",
  selectedSize = "",
  selectedVariant = null,
  toastTimer;
const productMap = new Map(),
  userId = ctx.user ? String(ctx.user.id) : "guest";
function addProducts(rows) {
  for (const raw of rows) {
    const p = {
      ...raw,
      id: Number(raw.id),
      price: Number(raw.price),
      old: Number(raw.old),
      stock: Number(raw.stock),
      display_price: Number(raw.display_price ?? raw.price),
      variants: (raw.variants || []).map((v) => ({
        ...v,
        id: Number(v.id),
        stock: Number(v.stock),
        price: v.price === null ? null : Number(v.price),
      })),
    };
    productMap.set(p.id, p);
  }
}
addProducts(JSON.parse($("#pageProducts")?.textContent || "[]"));
const key = (i) => i.id + "-" + (i.variant_id || 0) + "-" + i.size;
function normalize(items) {
  const map = new Map();
  for (const item of items || []) {
    const i = {
      id: Number(item.id),
      size: String(item.size || ""),
      variant_id: item.variant_id ? Number(item.variant_id) : null,
      quantity: Math.min(10, Math.max(1, parseInt(item.quantity, 10) || 1)),
    };
    if (!Number.isInteger(i.id) || i.id < 1 || !i.size) continue;
    const k = key(i);
    if (map.has(k)) map.get(k).quantity = Math.min(10, map.get(k).quantity + i.quantity);
    else map.set(k, i);
  }
  return [...map.values()].slice(0, 30);
}
cart = normalize(cart);
wishes = [...new Set(wishes.map(Number).filter(Number.isInteger))].slice(0, 100);
function toast(text, bagLink = false) {
  const el = $("#marketToast");
  el.innerHTML = esc(text) + (bagLink ? ' <a href="' + url("/cart") + '">View bag →</a>' : "");
  el.classList.add("visible");
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.remove("visible"), 4000);
}
async function request(path, data, method = "POST") {
  const response = await fetch(url(path), {
    method,
    headers: { Accept: "application/json", "Content-Type": "application/json", "X-CSRF-TOKEN": ctx.csrf },
    ...(method === "GET" ? {} : { body: JSON.stringify(data) }),
  });
  let result;
  try {
    result = await response.json();
  } catch {
    throw Error("We could not load this information. Please refresh and try again.");
  }
  if (!response.ok) {
    const error = new Error(Object.values(result.errors || {}).flat()[0] || result.message || "Please try again.");
    if (response.status === 401 || response.status === 419 || result.login_url) {
      error.loginUrl = result.login_url || url("/account/login") + "?return_to=" + encodeURIComponent(location.href);
    }
    throw error;
  }
  return result;
}
function localSave() {
  localStorage.setItem("avelle-cart-v5", JSON.stringify(cart));
  localStorage.setItem("avelle-wishlist-v5", JSON.stringify(wishes));
  localStorage.setItem("avelle-coupon-v5", JSON.stringify(code));
  localStorage.setItem("avelle-cart-owner", userId);
  updateBadges();
}
let saveQueue = Promise.resolve();
function saveCart() {
  localSave();
  if (ctx.user) {
    const snapshot = cart.map((i) => ({ ...i }));
    saveQueue = saveQueue.catch(() => {}).then(() => request("/api/cart", { items: snapshot }, "PUT"));
    return saveQueue;
  }
  return Promise.resolve();
}
function updateBadges() {
  const count = cart.reduce((n, i) => n + i.quantity, 0);
  $$("[data-cart-count]").forEach((e) => {
    e.hidden = !count;
    e.textContent = count;
  });
  $$("[data-wish-count]").forEach((e) => {
    e.hidden = !wishes.length;
    e.textContent = wishes.length;
  });
  $$("[data-wish-product]").forEach((b) => {
    const saved = wishes.includes(Number(b.dataset.wishProduct));
    b.classList.toggle("saved", saved);
    b.setAttribute("aria-pressed", String(saved));
  });
}
async function ensureProducts(ids) {
  const missing = [...new Set(ids.map(Number))].filter((id) => !productMap.has(id));
  if (missing.length) {
    const response = await fetch(url("/api/products?ids=" + missing.join(",")), {
      headers: { Accept: "application/json" },
    });
    if (!response.ok) throw Error("Unable to load current product information.");
    addProducts(await response.json());
  }
}
function activeOffers() {
  return offers.filter((o) => !o.expires_at || new Date(o.expires_at) > new Date());
}
function eligible(p, o) {
  if (o.seller_id && String(p.seller_id) !== String(o.seller_id)) return false;
  return (
    o.scope === "store" ||
    (o.scope === "category" && o.targets.includes(p.category)) ||
    (o.scope === "product" && o.targets.map(String).includes(String(p.id)))
  );
}
function offerCard(o) {
  return (
    '<article class="coupon-card ' +
    (["both", "pulse"].includes(o.mode) ? "soft-pulse" : "") +
    '"><div class="coupon-value">' +
    esc(o.saving) +
    "</div><h2>" +
    esc(o.title) +
    "</h2><p>" +
    esc(o.scope_label) +
    "</p><ul>" +
    (o.minimum
      ? "<li>" +
        (o.seller_id ? "Eligible seller subtotal " : "Minimum subtotal ") +
        money(o.minimum) +
        "</li>"
      : "") +
    (o.maximum ? "<li>Maximum savings " + money(o.maximum) + "</li>" : "") +
    (o.expires_at
      ? "<li>Until " +
        esc(new Date(o.expires_at).toLocaleString("en-IN", { timeZone: "Asia/Kolkata" })) +
        " IST</li>"
      : "") +
    '</ul><button class="coupon-copy" data-copy-code="' +
    esc(o.code) +
    '">' +
    esc(o.code) +
    ' <span>Copy code ⧉</span></button><a class="text-link" href="' +
    url("/shop") +
    '">Shop the collection ↗</a></article>'
  );
}
function renderOffers() {
  offers = activeOffers();
  const moving = offers.filter((o) => ["both", "marquee"].includes(o.mode));
  $("#liveOfferBar").hidden = !moving.length;
  const group = moving
    .map(
      (o) => '<a href="' + url("/offers") + '">' + esc(o.title) + " <strong>" + esc(o.code) + "</strong></a>",
    )
    .join("");
  $("#offerBarTrack").innerHTML = group
    ? '<div class="offer-bar-group">' +
      group +
      '</div><div class="offer-bar-group" aria-hidden="true">' +
      group.replaceAll("<a ", '<a tabindex="-1" ') +
      "</div>"
    : "";
  $$(".sale-link>span").forEach((e) => e.classList.toggle("status-light", Boolean(offers.length)));
  $$("[data-product-offer]").forEach((el) => {
    const p = productMap.get(Number(el.dataset.productOffer));
    const offer = p && offers.find((o) => eligible(p, o));
    el.textContent = offer ? offer.saving + " · " + offer.code : "";
    el.title = offer ? offer.title + (offer.minimum ? " · Min " + money(offer.minimum) : "") : "";
    el.classList.toggle("soft-pulse", Boolean(offer && ["both", "pulse"].includes(offer.mode)));
  });
  if ($("#detailOffers")) {
    const p = productMap.get(Number($("#productDetail").dataset.product));
    $("#detailOffers").innerHTML = offers
      .filter((o) => eligible(p, o))
      .map(
        (o) =>
          '<div class="detail-offer-row"><b>' +
          esc(o.saving) +
          ' <button data-copy-code="' +
          esc(o.code) +
          '">' +
          esc(o.code) +
          " ⧉</button></b><p>" +
          esc(o.title) +
          (o.minimum ? " · Min " + money(o.minimum) : "") +
          (o.maximum ? " · Up to " + money(o.maximum) : "") +
          "</p></div>",
      )
      .join("");
  }
  if ($("#offersPageGrid"))
    $("#offersPageGrid").innerHTML = offers.length
      ? offers.map(offerCard).join("")
      : '<div class="empty-state"><h2>More good offers are on their way.</h2><a href="' +
        url("/shop") +
        '" class="pink-button">Explore the collection ↗</a></div>';
  const feature = $(".home-offer-feature");
  if (feature) {
    feature.hidden = !offers.length;
    if (offers[0]) {
      const o = offers[0];
      feature.querySelector(".home-coupon").innerHTML =
        "<span>" +
        esc(o.saving) +
        "</span><strong>" +
        esc(o.title) +
        '</strong><div>USE CODE <button data-copy-code="' +
        esc(o.code) +
        '">' +
        esc(o.code) +
        " ⧉</button></div><small>" +
        (o.minimum ? (o.seller_id ? "Min eligible seller subtotal " : "Min bag ") + money(o.minimum) : "") +
        (o.maximum ? " · Max savings " + money(o.maximum) : "") +
        "</small>";
    }
  }
}
async function refreshOffers() {
  try {
    const r = await fetch(url("/api/offers"), { cache: "no-store", headers: { Accept: "application/json" } });
    if (r.ok) {
      offers = await r.json();
      renderOffers();
    }
  } catch {}
}
async function wish(id, save) {
  const previous = [...wishes];
  wishes = save ? [...new Set([...wishes, id])] : wishes.filter((x) => x !== id);
  localSave();
  try {
    if (ctx.user) await request("/api/wishlist", { product_id: id, saved: save });
    if ($("#wishlistGrid")) await renderWishlist();
    toast(save ? "Saved to your wishlist." : "Removed from your wishlist.");
  } catch (error) {
    wishes = previous;
    localSave();
    toast(error.message);
  }
}
function card(p) {
  return (
    '<article class="shop-card" data-product-card="' +
    p.id +
    '"><div class="shop-card-image"><a href="' +
    esc(p.url) +
    '"><img src="' +
    esc(p.cover) +
    '" alt="' +
    esc(p.name) +
    '" loading="lazy">' +
    (p.second
      ? '<img class="hover-image" src="' +
        esc(p.second) +
        '" alt="' +
        esc(p.name) +
        ' alternate view" loading="lazy">'
      : "") +
    '</a><button class="wish-button" data-wish-product="' +
    p.id +
    '" aria-label="Save ' +
    esc(p.name) +
    '" aria-pressed="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M20 4c-3-2-6 0-8 2-2-2-5-4-8-2-5 4 0 10 8 17 8-7 13-13 8-17Z"/></svg></button>' +
    (!p.stock ? '<span class="sold-out-label">Sold out</span>' : "") +
    '</div><div class="shop-card-info"><div class="card-category">' +
    esc(p.brand || p.category) +
    '</div><a class="card-name" href="' +
    esc(p.url) +
    '">' +
    esc(p.name) +
    '</a><div class="card-price"><strong>' +
    money(p.display_price) +
    "</strong>" +
    (p.old > p.display_price
      ? "<del>" +
        money(p.old) +
        "</del><span>" +
        Math.round((1 - p.display_price / p.old) * 100) +
        "% off</span>"
      : "") +
    '</div><div class="card-rating">' +
    (p.review_count
      ? "<span>★ " + p.review_average + "</span><small>(" + p.review_count + ")</small>"
      : "<small>New find</small>") +
    '</div><div class="product-offer-note" data-product-offer="' +
    p.id +
    '"></div></div></article>'
  );
}
async function renderWishlist() {
  await ensureProducts(wishes);
  $("#wishlistGrid").innerHTML = wishes.length
    ? wishes
        .map((id) => {
          const p = productMap.get(id);
          return p
            ? card(p)
            : '<div class="form-card"><p>This saved find is no longer available.</p><button class="outline-button" data-wish-product="' +
                id +
                '">Remove from wishlist</button></div>';
        })
        .join("")
    : '<div class="empty-state"><span>♡</span><h2>A little room for what you love.</h2><p>Tap the heart on a product to save it here.</p><a class="pink-button" href="' +
      url("/shop") +
      '">Explore favourites ↗</a></div>';
  updateBadges();
  renderOffers();
}
function lineProduct(i) {
  const p = productMap.get(i.id);
  if (!p) return null;
  const v = i.variant_id ? p.variants.find((v) => v.id === i.variant_id) : null;
  return {
    p,
    v,
    price: v?.price ?? p.price,
    color: v?.color ?? p.color,
    available: p.stock > 0 && (!i.variant_id || Boolean(v && v.stock)),
  };
}
function summaryHTML(checkout = false) {
  const estimate = cart.reduce((n, i) => n + (lineProduct(i)?.price || 0) * i.quantity, 0);
  const subtotal = quote?.subtotal ?? estimate,
    shipping =
      quote?.shipping ??
      (subtotal >= Number(ctx.settings.free_shipping_minimum) ? 0 : Number(ctx.settings.shipping_fee)),
    discount = quote?.discount || 0,
    total = quote?.total ?? subtotal + shipping;
  return (
    "<h2>" +
    (checkout ? "Your order" : "Price details") +
    "</h2>" +
    (checkout
      ? '<div class="checkout-summary-items">' +
        cart
          .map((i) => {
            const data = lineProduct(i);
            return data
              ? '<div class="checkout-summary-line"><img src="' +
                  esc(data.p.cover) +
                  '" alt=""><div>' +
                  esc(data.p.name) +
                  "<small>" +
                  esc(i.size) +
                  " · Qty " +
                  i.quantity +
                  "</small></div><b>" +
                  money(data.price * i.quantity) +
                  "</b></div>"
              : "";
          })
          .join("") +
        "</div>"
      : "") +
    '<form class="coupon-form" data-coupon-form><label for="bagCoupon">Have a coupon or referral code?</label><div><input id="bagCoupon" name="code" placeholder="Enter code" maxlength="40" value="' +
    esc(code) +
    '"><button>Apply</button></div>' +
    (code && quote?.code
      ? '<div class="coupon-applied"><span>✓ ' +
        esc(quote.code) +
        ' applied</span><button type="button" data-remove-coupon>Remove</button></div>'
      : code
        ? '<div class="coupon-applied"><span>' +
          esc(code) +
          '</span><button type="button" data-remove-coupon>Remove</button></div>'
        : "") +
    "</form>" +
    (quoteError ? '<p class="inline-error">' + esc(quoteError) + "</p>" : "") +
    "<dl><div><dt>Subtotal (" +
    cart.reduce((n, i) => n + i.quantity, 0) +
    " items)</dt><dd>" +
    money(subtotal) +
    "</dd></div><div><dt>Delivery fee</dt><dd>" +
    (shipping ? money(shipping) : "FREE") +
    "</dd></div><div><dt>Coupon savings</dt><dd>−" +
    money(discount) +
    '</dd></div><div><dt>Wallet credit</dt><dd>−' + money(quote?.wallet_used || 0) +
    '</dd></div><div class="summary-total"><dt>Total amount</dt><dd>' +
    money(total) +
    "</dd></div></dl>" +
    (!checkout
      ? '<a class="pink-button full ' +
        (quoteError ? "disabled-checkout" : "") +
        '" href="' +
        url("/checkout") +
        '">Continue to checkout →</a>'
      : "") +
    '<p class="summary-note">' +
    (shipping
      ? "Add " +
        money(Math.max(0, Number(ctx.settings.free_shipping_minimum) - subtotal)) +
        " more for free shipping."
      : "Your bag qualifies for free shipping.") +
    "</p><small>Cash on delivery · Prices are verified before your order is placed.</small>"
  );
}
if (!code && ctx.referral_code) {
  code = ctx.referral_code;
  localSave();
}
let renderSequence = 0;
async function renderCart() {
  const sequence = ++renderSequence;
  await ensureProducts(cart.map((i) => i.id));
  if (sequence !== renderSequence) return;
  if (!cart.length) {
    const empty =
      '<div class="empty-state"><span>♧</span><h2>Your bag is waiting for a favourite.</h2><p>Find something that feels like you.</p><a class="pink-button" href="' +
      url("/shop") +
      '">Start shopping ↗</a></div>';
    if ($("#cartLines")) $("#cartLines").innerHTML = empty;
    if ($("#cartSummary")) $("#cartSummary").hidden = true;
    if ($("#checkoutSummary")) $("#checkoutSummary").innerHTML = empty;
    if ($("#placeOrderButton")) $("#placeOrderButton").disabled = true;
    return;
  }
  if ($("#cartSummary")) $("#cartSummary").hidden = false;
  if ($("#cartLines"))
    $("#cartLines").innerHTML = cart
      .map((i, index) => {
        const d = lineProduct(i);
        return (
          '<article class="cart-line"><img src="' +
          esc(d?.p.cover || url("/images/placeholder.svg")) +
          '" alt="' +
          esc(d?.p.name || "Unavailable product") +
          '"><div class="cart-line-info"><h3>' +
          (d ? '<a href="' + esc(d.p.url) + '">' + esc(d.p.name) + "</a>" : "This product is unavailable") +
          "</h3><p>" +
          esc(i.size) +
          (d ? " · " + esc(d.color) : "") +
          '</p><div class="cart-line-price"><b>' +
          money((d?.price || 0) * i.quantity) +
          "</b></div>" +
          (!d?.available ? '<p class="inline-error">This option is currently unavailable.</p>' : "") +
          '<div class="cart-controls"><div class="quantity-controls"><button data-cart-index="' +
          index +
          '" data-quantity="-1" aria-label="Decrease quantity">−</button><span>' +
          i.quantity +
          '</span><button data-cart-index="' +
          index +
          '" data-quantity="1" aria-label="Increase quantity">+</button></div><button class="move-to-wishlist" data-move-to-wish="' +
          index +
          '">♡ Save for later</button><button class="cart-remove" data-remove-line="' +
          index +
          '">Remove</button></div></div></article>'
        );
      })
      .join("");
  quote = null;
  quoteError = "";
  try {
    const result = await request("/api/promotions/quote", { items: cart, code: code || null, use_wallet: $("#useWallet")?.checked ? 1 : 0 });
    if (sequence !== renderSequence) return;
    quote = result;
  } catch (error) {
    if (sequence !== renderSequence) return;
    quoteError = error.message;
  }
  if ($("#cartSummary")) $("#cartSummary").innerHTML = summaryHTML(false);
  if ($("#checkoutSummary")) $("#checkoutSummary").innerHTML = summaryHTML(true);
  if ($("#placeOrderButton"))
    $("#placeOrderButton").disabled = Boolean(quoteError) || !Number(ctx.settings.cod_enabled);
  return !quoteError;
}
function productSelection() {
  if (!$("#productDetail")) return;
  const p = productMap.get(Number($("#productDetail").dataset.product));
  const color = $("#productColor")?.value;
  if (p.variants.length) {
    const options = p.variants.filter((v) => v.color === color);
    $$("[data-select-size]").forEach((b) => {
      b.disabled = !options.some((v) => v.size === b.dataset.selectSize && v.stock > 0);
      if (b.disabled && selectedSize === b.dataset.selectSize) selectedSize = "";
      b.classList.toggle("selected", selectedSize === b.dataset.selectSize);
      b.setAttribute("aria-pressed", String(selectedSize === b.dataset.selectSize));
    });
    selectedVariant = options.find((v) => v.size === selectedSize) || null;
  } else {
    $$("[data-select-size]").forEach((b) => {
      b.classList.toggle("selected", selectedSize === b.dataset.selectSize);
      b.setAttribute("aria-pressed", String(selectedSize === b.dataset.selectSize));
    });
  }
  const price = selectedVariant?.price ?? p.display_price;
  $("#detailPrice").textContent = money(price);
  if ($("#detailDiscount"))
    $("#detailDiscount").textContent = p.old > price ? Math.round((1 - price / p.old) * 100) + "% OFF" : "";
  $("#detailSku").textContent = selectedVariant?.sku || p.sku || "AVL-" + p.id;
  const stock = p.variants.length
    ? (selectedVariant?.stock ??
      Math.max(0, ...p.variants.filter((v) => v.color === color).map((v) => v.stock)))
    : p.stock;
  $("#productAvailability").textContent = stock
    ? "In stock" + (stock <= 5 ? " · Only " + stock + " left" : "")
    : "Currently unavailable";
  $("#productQuantity").max = String(Math.min(10, stock || 1));
  if (Number($("#productQuantity").value) > Math.min(10, stock))
    $("#productQuantity").value = String(Math.max(1, Math.min(10, stock)));
  $("#addProductToBag").disabled = !stock;
  $("#buyProductNow").disabled = !stock;
}
async function addProduct(buyNow = false) {
  await ready;
  const p = productMap.get(Number($("#productDetail").dataset.product));
  if (!selectedSize) {
    $("#productChoiceError").textContent = "Please choose a size.";
    return;
  }
  if (p.variants.length && !selectedVariant) {
    $("#productChoiceError").textContent = "Choose an available size and colour.";
    return;
  }
  const quantity = Math.max(1, Math.min(10, parseInt($("#productQuantity").value, 10) || 1)),
    item = { id: p.id, size: selectedSize, variant_id: selectedVariant?.id ?? null, quantity },
    same = cart.find((i) => key(i) === key(item)),
    stock = selectedVariant?.stock ?? p.stock,
    total = cart.filter((i) => i.id === p.id).reduce((n, i) => n + i.quantity, 0);
  if ((same?.quantity || 0) + quantity > Math.min(10, stock) || total + quantity > p.stock) {
    $("#productChoiceError").textContent =
      "This quantity is not available. Check your bag or choose fewer items.";
    return;
  }
  if (!same && cart.length >= 30) {
    toast("Your bag can contain up to 30 different options.");
    return;
  }
  if (same) same.quantity += quantity;
  else cart.push(item);
  $("#productChoiceError").textContent = "";
  await saveCart();
  toast("Added to your bag.", true);
  if (buyNow) location.href = url("/checkout");
}
function showInfo(name) {
  const valid = ["description", "specifications", "reviews"];
  if (!valid.includes(name)) return;
  $$("[data-info-tab]").forEach((b) => {
    b.setAttribute("aria-selected", String(b.dataset.infoTab === name));
    b.tabIndex = b.dataset.infoTab === name ? 0 : -1;
  });
  $$(".information-panel").forEach((p) => (p.hidden = p.id !== "panel-" + name));
}
document.addEventListener("click", async (e) => {
  const b = e.target.closest("button,a");
  if (!b) return;
  try {
    if (b.dataset.copyCode) {
      e.preventDefault();
      if (navigator.clipboard) {
        try {
          await navigator.clipboard.writeText(b.dataset.copyCode);
          toast("Copied code " + b.dataset.copyCode);
        } catch {
          toast("Use code " + b.dataset.copyCode + " in your bag.");
        }
      } else toast("Use code " + b.dataset.copyCode + " in your bag.");
      return;
    }
    if (b.id === "offerAnimationToggle") {
      const paused = $("#liveOfferBar").classList.toggle("paused");
      b.setAttribute("aria-pressed", String(paused));
      b.setAttribute("aria-label", paused ? "Resume offer animation" : "Pause offer animation");
      b.textContent = paused ? "▶" : "Ⅱ";
      return;
    }
    if (b.dataset.galleryImage) {
      $("#mainProductImage").src = b.dataset.galleryImage;
      $$("[data-gallery-image]").forEach((t) => t.classList.toggle("active", t === b));
      return;
    }
    if (b.hasAttribute("data-zoom-image")) {
      $("#zoomImage").src = $("#mainProductImage").src;
      $("#galleryZoom").showModal();
      return;
    }
    if (b.hasAttribute("data-close-dialog")) {
      b.closest("dialog").close();
      return;
    }
    if (b.dataset.categoryImage) {
      $("#categoryMainImage").src = b.dataset.categoryImage;
      return;
    }
    if (b.dataset.infoTab) {
      showInfo(b.dataset.infoTab);
      return;
    }
    if (b.getAttribute("href") === "#reviews") {
      showInfo("reviews");
      return;
    }
    if (b.dataset.selectSize) {
      selectedSize = b.dataset.selectSize;
      productSelection();
      $("#productChoiceError").textContent = "";
      return;
    }
    if (b.id === "addProductToBag" || b.id === "buyProductNow") {
      await addProduct(b.id === "buyProductNow");
      return;
    }
    if (b.dataset.wishProduct) {
      e.preventDefault();
      await ready;
      const id = Number(b.dataset.wishProduct);
      await wish(id, !wishes.includes(id));
      return;
    }
    if (b.dataset.cartIndex !== undefined) {
      await ready;
      const index = Number(b.dataset.cartIndex),
        item = cart[index],
        delta = Number(b.dataset.quantity);
      if (!item) return;
      const d = lineProduct(item),
        total = cart.filter((i) => i.id === item.id).reduce((n, i) => n + i.quantity, 0);
      if (
        delta > 0 &&
        (!d?.available ||
          item.quantity >= 10 ||
          item.quantity >= Number(d.v?.stock ?? d.p.stock) ||
          total >= d.p.stock)
      ) {
        toast("The requested quantity is unavailable.");
        return;
      }
      item.quantity += delta;
      if (item.quantity <= 0) cart.splice(index, 1);
      await saveCart();
      await renderCart();
      return;
    }
    if (b.dataset.removeLine !== undefined) {
      await ready;
      cart.splice(Number(b.dataset.removeLine), 1);
      await saveCart();
      await renderCart();
      return;
    }
    if (b.dataset.moveToWish !== undefined) {
      await ready;
      const index = Number(b.dataset.moveToWish),
        item = cart[index];
      if (item) {
        await wish(item.id, true);
        cart.splice(index, 1);
        await saveCart();
        await renderCart();
      }
      return;
    }
    if (b.hasAttribute("data-remove-coupon")) {
      code = "";
      localSave();
      await renderCart();
      return;
    }
    if (b.classList.contains("disabled-checkout")) {
      e.preventDefault();
      toast("Update your bag before continuing.");
      return;
    }
    if (b.dataset.fillAddress) {
      const rows = JSON.parse($("#savedAddresses").textContent),
        a = rows.find((a) => Number(a.id) === Number(b.dataset.fillAddress));
      if (a)
        for (const field of ["name", "phone", "address", "city", "state", "pincode"])
          $("#checkoutForm").elements[field].value = a[field] || "";
      toast("Delivery address selected.");
      return;
    }
  } catch (error) {
    toast(error.message);
  }
});
document.addEventListener("submit", async (e) => {
  if (e.target.hasAttribute("data-coupon-form")) {
    e.preventDefault();
    await ready;
    code = new FormData(e.target).get("code").trim().toUpperCase();
    localSave();
    if (await renderCart()) toast(code ? "Your coupon is applied." : "Coupon removed.");
    return;
  }
  if (e.target.id === "checkoutForm") {
    e.preventDefault();
    await ready;
    const button = $("#placeOrderButton");
    button.disabled = true;
    button.textContent = "Placing your order…";
    $("#checkoutError").textContent = "";
    try {
      await saveQueue;
      if (!cart.length) throw Error("Your bag is empty.");
      if (!(await renderCart())) throw Error(quoteError || "Please check your bag.");
      const data = Object.fromEntries(new FormData(e.target));
      data.items = cart;
      data.code = code || null;
      data.use_wallet = data.use_wallet ? 1 : 0;
      const fingerprint = JSON.stringify({ cart, code, use_wallet: Boolean(data.use_wallet) });
      let checkout;
      try {
        checkout = JSON.parse(sessionStorage.getItem("avelle-checkout-key"));
      } catch {}
      if (!checkout || checkout.fingerprint !== fingerprint) {
        checkout = { fingerprint, key: crypto.randomUUID() };
        sessionStorage.setItem("avelle-checkout-key", JSON.stringify(checkout));
      }
      data.checkout_key = checkout.key;
      button.disabled = true;
      const result = await request("/api/orders", data);
      cart = [];
      code = "";
      localSave();
      sessionStorage.removeItem("avelle-checkout-key");
      location.href = url("/account/orders/" + encodeURIComponent(result.reference) + "?new=1");
    } catch (error) {
      if (error.loginUrl) {
        location.href = error.loginUrl;
        return;
      }
      $("#checkoutError").textContent = error.message;
      button.disabled = false;
      button.textContent = "Place order →";
    }
  }
});
$$("[data-submit-on-change]").forEach((el) => el.addEventListener("change", () => el.form.submit()));
$("#productColor")?.addEventListener("change", () => {
  selectedSize = "";
  productSelection();
});
$$("[data-info-tab]").forEach((b) =>
  b.addEventListener("keydown", (e) => {
    if (!["ArrowLeft", "ArrowRight", "Home", "End"].includes(e.key)) return;
    e.preventDefault();
    const buttons = $$("[data-info-tab]"),
      i = buttons.indexOf(b),
      n =
        e.key === "Home"
          ? 0
          : e.key === "End"
            ? buttons.length - 1
            : (i + (e.key === "ArrowRight" ? 1 : -1) + buttons.length) % buttons.length;
    showInfo(buttons[n].dataset.infoTab);
    buttons[n].focus();
  }),
);
window.addEventListener("hashchange", () => {
  if (location.hash === "#reviews") showInfo("reviews");
});
if ($("#filtersPanel") && innerWidth < 950) $("#filtersPanel").open = false;
if ($("#productDetail")) {
  const p = productMap.get(Number($("#productDetail").dataset.product));
  if (p.sizes.length === 1) selectedSize = p.sizes[0];
  showInfo(location.hash === "#reviews" ? "reviews" : "description");
  productSelection();
}
async function initialize() {
  const owner = localStorage.getItem("avelle-cart-owner");
  if (!ctx.user && owner && owner !== "guest") {
    cart = [];
    wishes = [];
    code = "";
  }
  if (ctx.user && owner && owner !== "guest" && owner !== String(userId)) {
    cart = [];
    wishes = [];
    code = "";
  }
  if (ctx.user) {
    try {
      const remote = await request("/api/cart", null, "GET"),
        remoteWish = await request("/api/wishlist", null, "GET");
      if (!owner || owner === "guest") {
        cart = normalize([...remote.items, ...cart]);
        wishes = [...new Set([...remoteWish.map(Number), ...wishes])];
        await request("/api/cart", { items: cart }, "PUT");
        for (const id of wishes.filter((id) => !remoteWish.map(Number).includes(id)))
          await request("/api/wishlist", { product_id: id, saved: true });
      } else {
        cart = normalize(remote.items);
        wishes = remoteWish.map(Number);
      }
    } catch (error) {
      toast(error.message);
    }
  }
  localSave();
  if ($("#wishlistGrid")) await renderWishlist();
  if ($("#cartPage") || $("#checkoutForm")) await renderCart();
  renderOffers();
  refreshOffers();
}
const ready = initialize().catch((error) => {
  toast(error.message);
  if ($("#placeOrderButton")) $("#placeOrderButton").disabled = true;
  return null;
});
setInterval(() => {
  const n = offers.length;
  offers = activeOffers();
  if (offers.length !== n) renderOffers();
}, 1000);
setInterval(() => {
  if (!document.hidden) refreshOffers();
}, 30000);
document.addEventListener("visibilitychange", () => {
  if (!document.hidden) refreshOffers();
});

document.getElementById('useWallet')?.addEventListener('change', () => renderCart());
