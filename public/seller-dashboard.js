(() => {
  "use strict";
  const pages = {
    "seller-overview": ["Overview", "A clear view of your products, orders and next steps."],
    products: ["Products", "A collection worth discovering, down to the last detail."],
    "seller-orders": ["Orders", "Your shipments, organised and ready for the next step."],
    "seller-offers": ["Offers & coupons", "Thoughtful offers for your own collection."],
    "seller-earnings": ["Earnings", "Your estimated earnings and recorded external payouts."],
    "seller-business": ["Business details", "The details behind your shop."],
    "seller-account": ["Account & security", "Your seller login, profile and password settings."],
  };
  document.querySelectorAll("i.bi").forEach((i) => i.setAttribute("aria-hidden", "true"));
  const search = document.getElementById("sellerSearch");
  function show() {
    let key = location.hash.slice(1) || document.body.dataset.initialPage || "seller-overview";
    if (!pages[key]) key = "seller-overview";
    document
      .querySelectorAll(".seller-page")
      .forEach((p) => p.classList.toggle("is-active", (p.dataset.page || p.id) === key));
    document.querySelectorAll("[data-seller-nav]").forEach((a) => {
      const active = a.dataset.sellerNav === key;
      a.classList.toggle("active", active);
      if (active) a.setAttribute("aria-current", "page");
      else a.removeAttribute("aria-current");
    });
    document.getElementById("sellerPageTitle").textContent = pages[key][0];
    document.getElementById("sellerBreadcrumb").textContent = pages[key][0];
    document.getElementById("sellerPageDescription").textContent = pages[key][1];
    search.value = "";
    document.querySelectorAll("[data-search-row]").forEach((r) => (r.hidden = false));
    document.getElementById("sellerSearchEmpty").hidden = true;
  }
  document.addEventListener("click", (event) => {
    const link = event.target.closest('a[href^="#"]');
    if (!link) return;
    const key = link.getAttribute("href").slice(1);
    if (!pages[key]) return;
    event.preventDefault();
    location.hash = key;
    show();
    if (matchMedia("(max-width:991px)").matches) {
      document.body.classList.remove("sidebar-open");
      document.body.classList.add("sidebar-collapse");
    }
    const heading = document.getElementById("sellerPageTitle");
    heading.setAttribute("tabindex", "-1");
    heading.focus({ preventScroll: true });
  });
  window.addEventListener("hashchange", show);
  show();
  search.addEventListener("input", () => {
    const query = search.value.trim().toLowerCase(),
      rows = [...document.querySelectorAll(".seller-page.is-active [data-search-row]")];
    rows.forEach((r) => (r.hidden = query && !r.textContent.toLowerCase().includes(query)));
    document.getElementById("sellerSearchEmpty").hidden =
      !query || !rows.length || rows.some((r) => !r.hidden);
  });
  document.addEventListener("click", (e) => {
    const trigger = e.target.closest("[data-open-editor]");
    if (!trigger) return;
    const target = document.getElementById(trigger.dataset.openEditor);
    if (!target) return;
    const page = target.closest(".seller-page");
    if (page) {
      location.hash = page.dataset.page || page.id;
      show();
    }
    target.open = true;
    requestAnimationFrame(() => {
      target.scrollIntoView({ behavior: "smooth", block: "start" });
      target.querySelector("input:not([type=hidden])")?.focus({ preventScroll: true });
    });
  });
  document.querySelectorAll("[data-seller-offer-form]").forEach((form) => {
    const scope = form.querySelector("[data-offer-scope]");
    function sync() {
      form
        .querySelectorAll("[data-offer-targets]")
        .forEach((group) => (group.hidden = group.dataset.offerTargets !== scope.value));
      const values =
        scope.value === "product"
          ? [...form.querySelector("[data-offer-products]").selectedOptions].map((o) => o.value)
          : scope.value === "category"
            ? [...form.querySelector("[data-offer-categories]").selectedOptions].map((o) => o.value)
            : [];
      form.querySelector("[name=targets]").value = values.join(",");
    }
    form.addEventListener("change", sync);
    form.addEventListener("submit", sync);
    sync();
  });
})();
