"use strict";

(() => {
  if (!document.body.classList.contains("admin-workspace")) return;

  const definitions = {
    products: { title: "Products", columns: { name: "Product", category: "Category", brand: "Brand", price: "Price", stock: "Stock", status: "Status" } },
    brands: { title: "Brands", columns: { name: "Brand", slug: "Slug", products: "Products", status: "Status" } },
    categories: { title: "Categories", columns: { name: "Category", parent: "Parent", images: "Images", status: "Status" } },
    orders: { title: "Orders", columns: { number: "Order", customer: "Customer", date: "Placed", amount: "Amount", status: "Status", payment: "Payment" } },
    customers: { title: "Users and customers", columns: { name: "Name", email: "Email", role: "Role", status: "Access", joined: "Joined" } },
    offers: { title: "Coupons and referrals", columns: { code: "Code", kind: "Type", value: "Discount", scope: "Scope", used: "Uses", status: "Status" } },
    reviews: { title: "Reviews", columns: { product: "Product", customer: "Customer", rating: "Rating", status: "Status", date: "Submitted" } },
    returns: { title: "Returns", columns: { order: "Order", customer: "Customer", reason: "Reason", refund: "Refund", status: "Status" } },
    pages: { title: "Content pages", columns: { title: "Page", slug: "Storefront path", status: "Status" } },
    support: { title: "Support requests", columns: { subject: "Subject", customer: "Customer", email: "Email", status: "Status", date: "Received" } },
    sellers: { title: "Seller applications", columns: { shop: "Shop", owner: "Owner", email: "Email", commission: "Commission", status: "Status" } },
    sellerProducts: { title: "Seller product approvals", columns: { name: "Product", shop: "Shop", category: "Category", price: "Price", status: "Status" } },
    shipments: { title: "Shipments and payouts", columns: { shipment: "Shipment", order: "Order", shop: "Shop", amount: "Seller amount", status: "Status", payout: "Payout" } },
  };

  const records = new Map();
  const lists = [];
  const dialog = document.createElement("dialog");
  dialog.className = "admin-record-dialog";
  dialog.setAttribute("aria-labelledby", "adminRecordTitle");
  dialog.innerHTML = '<header class="admin-record-dialog-header"><h2 id="adminRecordTitle"></h2><button type="button" class="admin-record-dialog-close" aria-label="Close details">×</button></header><div class="admin-record-dialog-body"></div>';
  document.body.append(dialog);

  const dialogTitle = dialog.querySelector("h2");
  const dialogBody = dialog.querySelector(".admin-record-dialog-body");
  let openedRecord = null;

  document.querySelectorAll("[data-admin-record]").forEach((source) => {
    const data = JSON.parse(source.dataset.adminRecord);
    records.set(source.id, { source, data, section: source.closest(".admin-page")?.id });
  });

  function restoreRecord() {
    if (!openedRecord) return;
    const { source, placeholder } = openedRecord;
    placeholder.replaceWith(source);
    if (source.tagName === "DETAILS") source.open = false;
    openedRecord = null;
  }

  function closeRecord() {
    restoreRecord();
    const url = new URL(location.href);
    url.searchParams.delete("record");
    history.replaceState(null, "", url);
  }

  function openRecord(id) {
    const record = records.get(id);
    if (!record) return;
    restoreRecord();
    const placeholder = document.createComment("Record position");
    record.source.before(placeholder);
    openedRecord = { source: record.source, placeholder };
    dialogBody.replaceChildren(record.source);
    record.source.hidden = false;
    if (record.source.tagName === "DETAILS") record.source.open = true;
    dialogTitle.textContent = record.data.title;
    const url = new URL(location.href);
    url.searchParams.set("record", id);
    url.hash = record.section;
    history.replaceState(null, "", url);
    if (!document.getElementById(record.section)?.classList.contains("is-active")) {
      window.showPage?.();
    }
    if (!dialog.open) dialog.showModal();
    dialogBody.scrollTop = 0;
    dialog.querySelector(".admin-record-dialog-close").focus();
  }

  dialog.querySelector(".admin-record-dialog-close").addEventListener("click", () => dialog.close());
  dialog.addEventListener("close", closeRecord);
  dialog.addEventListener("click", (event) => {
    if (event.target !== dialog) return;
    const bounds = dialog.getBoundingClientRect();
    if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) {
      dialog.close();
    }
  });

  document.addEventListener("click", (event) => {
    const trigger = event.target.closest("[data-open-record],[data-open-editor]");
    if (!trigger) return;
    const id = trigger.dataset.openRecord || trigger.dataset.openEditor;
    if (!records.has(id)) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    openRecord(id);
  }, true);

  function tone(value) {
    const status = String(value).toLowerCase();
    if (["approved", "published", "enabled", "delivered", "collected", "completed", "resolved", "recorded"].includes(status)) return "success";
    if (["pending", "placed", "confirmed", "shipped", "requested", "received", "open", "unpaid"].includes(status)) return "warning";
    if (["rejected", "cancelled", "disabled", "suspended", "locked"].includes(status)) return "danger";
    return "neutral";
  }

  function createList(slot, key) {
    const definition = definitions[key];
    const allRecords = [...records.entries()]
      .filter(([, record]) => record.data.group === key && !record.data.create);
    const card = document.createElement("section");
    card.className = "admin-record-card";
    card.dataset.adminTable = key;
    card.innerHTML = '<div class="admin-record-toolbar"><h3></h3><input type="search" placeholder="Search records"></div><div class="table-responsive"><table class="table table-hover"><caption class="visually-hidden"></caption><thead><tr></tr></thead><tbody></tbody></table></div><div class="admin-record-footer"><span class="admin-record-count"></span><label>Rows<select><option>10</option><option>25</option><option>50</option><option>100</option></select></label><div class="admin-record-pagination"><button type="button">Previous</button><span></span><button type="button">Next</button></div></div>';
    slot.replaceWith(card);
    const heading = card.querySelector("h3");
    heading.textContent = definition.title + " (" + allRecords.length + ")";
    card.querySelector("caption").textContent = definition.title;
    const search = card.querySelector('input[type="search"]');
    search.setAttribute("aria-label", "Search " + definition.title.toLowerCase());
    const head = card.querySelector("thead tr");
    for (const title of [...Object.values(definition.columns), "Action"]) {
      const cell = document.createElement("th");
      cell.scope = "col";
      cell.textContent = title;
      head.append(cell);
    }

    const body = card.querySelector("tbody");
    const count = card.querySelector(".admin-record-count");
    const sizeSelect = card.querySelector("select");
    sizeSelect.setAttribute("aria-label", "Rows per page for " + definition.title.toLowerCase());
    const [previous, next] = card.querySelectorAll(".admin-record-pagination button");
    const pageLabel = card.querySelector(".admin-record-pagination span");
    let query = "";
    let page = 1;
    let size = 10;

    function render() {
      const matches = allRecords.filter(([, record]) =>
        Object.values(record.data).join(" ").toLowerCase().includes(query));
      const pageCount = Math.max(1, Math.ceil(matches.length / size));
      page = Math.min(page, pageCount);
      body.replaceChildren();
      for (const [id, record] of matches.slice((page - 1) * size, page * size)) {
        const row = document.createElement("tr");
        row.dataset.searchRow = "";
        for (const field of Object.keys(definition.columns)) {
          const cell = document.createElement("td");
          const value = record.data[field] ?? "—";
          if (["status", "payment", "payout"].includes(field)) {
            const badge = document.createElement("span");
            badge.className = "admin-record-status";
            badge.dataset.tone = tone(value);
            badge.textContent = String(value);
            cell.append(badge);
          } else {
            cell.textContent = String(value);
          }
          row.append(cell);
        }
        const cell = document.createElement("td");
        const view = document.createElement("a");
        const url = new URL(location.href);
        url.searchParams.set("record", id);
        url.hash = record.section;
        view.href = url.href;
        view.dataset.openRecord = id;
        view.className = "admin-record-view";
        view.textContent = "View";
        view.setAttribute("aria-label", "View " + record.data.title);
        cell.append(view);
        row.append(cell);
        body.append(row);
      }

      if (!matches.length) {
        const row = document.createElement("tr");
        const cell = document.createElement("td");
        cell.colSpan = Object.keys(definition.columns).length + 1;
        cell.className = "admin-record-empty";
        cell.textContent = query ? "No records match your search." : "No records yet.";
        row.append(cell);
        body.append(row);
      }
      const start = matches.length ? (page - 1) * size + 1 : 0;
      count.textContent = "Showing " + start + "–" + Math.min(page * size, matches.length) + " of " + matches.length;
      pageLabel.textContent = page + " / " + pageCount;
      previous.disabled = page === 1;
      next.disabled = page === pageCount;
    }

    function filter(value) {
      query = value.toLowerCase().trim();
      search.value = value;
      page = 1;
      render();
    }

    search.addEventListener("input", () => filter(search.value));
    sizeSelect.addEventListener("change", () => {
      size = Number(sizeSelect.value);
      page = 1;
      render();
    });
    previous.addEventListener("click", () => { page -= 1; render(); });
    next.addEventListener("click", () => { page += 1; render(); });
    lists.push({ section: card.closest(".admin-page")?.id, filter, render });
    render();
  }

  document.querySelectorAll("[data-admin-list]").forEach((slot) => {
    if (definitions[slot.dataset.adminList]) createList(slot, slot.dataset.adminList);
  });
  records.forEach(({ source }) => source.classList.add("admin-record-source"));
  document.body.classList.add("admin-records-ready");
  document.querySelectorAll(".admin-page > p").forEach((element) => {
    if (/^No (applications|marketplace shipments|return requests|support requests)/.test(element.textContent.trim())) element.hidden = true;
  });
  document.querySelector("#reviews > .dashboard-empty")?.setAttribute("hidden", "");

  window.avelleAdminTables = {
    filter(section, value) {
      lists.filter((list) => list.section === section).forEach((list) => list.filter(value));
    },
  };
  document.addEventListener("avelle:admin-page", (event) => {
    lists.filter((list) => list.section === event.detail.active).forEach((list) => list.filter(""));
  });
  const initialRecord = new URL(location.href).searchParams.get("record");
  if (initialRecord) openRecord(initialRecord);
})();
