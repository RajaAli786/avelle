(() => {
  const checkout = document.querySelector("#checkoutForm");
  const source = document.querySelector("#savedAddresses");
  if (!checkout || !source) return;

  const addresses = JSON.parse(source.textContent);
  const fields = ["name", "phone", "address", "city", "state", "pincode"];
  const choices = document.querySelector("#checkoutAddressChoices");
  const dialog = document.querySelector("#checkoutAddressDialog");
  const editor = document.querySelector("#checkoutAddressEditor");

  function selectAddress(address) {
    for (const field of fields) checkout.elements[field].value = address[field] || "";
    checkout.elements.saved_address_id.value = address.id;
    checkout.elements.address_label.value = address.label;
    document.querySelectorAll("[data-deliver-address]").forEach((button) => {
      const selected = Number(button.dataset.deliverAddress) === Number(address.id);
      button.textContent = selected ? "Selected for delivery" : "Deliver to this address";
      button.classList.toggle("is-selected", selected);
    });
    document.querySelectorAll('[name="delivery_address_choice"]').forEach((radio) => {
      radio.checked = Number(radio.value) === Number(address.id);
    });
  }

  function openEditor(address = null) {
    editor.reset();
    editor.elements.id.value = address?.id || "";
    editor.elements.label.value = address?.label || "Home";
    for (const field of fields) editor.elements[field].value = address?.[field] || "";
    if (!address) editor.elements.name.value = checkout.elements.name.value;
    editor.elements.is_default.checked = !!address?.is_default;
    document.querySelector("#addressEditorTitle").textContent = address
      ? "Edit delivery address"
      : "Add delivery address";
    document.querySelector("#addressEditorError").textContent = "";
    dialog.showModal();
  }

  if (!dialog || !editor) return;

  document.addEventListener("change", (event) => {
    if (event.target.name !== "delivery_address_choice") return;
    const address = addresses.find((item) => Number(item.id) === Number(event.target.value));
    if (address) selectAddress(address);
  });

  document.addEventListener("click", (event) => {
    const button = event.target.closest("button");
    if (!button) return;
    if (button.dataset.deliverAddress) {
      const address = addresses.find((item) => Number(item.id) === Number(button.dataset.deliverAddress));
      if (address) {
        selectAddress(address);
        document.querySelector("#addressSaveStatus").textContent = "Your delivery address is selected.";
      }
    }
    if (button.hasAttribute("data-new-address")) openEditor();
    if (button.hasAttribute("data-close-address")) dialog.close();
    if (button.dataset.editAddress) {
      const address = addresses.find((item) => Number(item.id) === Number(button.dataset.editAddress));
      if (address) openEditor(address);
    }
  });

  editor.addEventListener("submit", async (event) => {
    event.preventDefault();
    const button = editor.querySelector('[type="submit"]');
    button.disabled = true;
    document.querySelector("#addressEditorError").textContent = "";
    const data = Object.fromEntries(new FormData(editor));
    data.is_default = editor.elements.is_default.checked ? 1 : 0;
    try {
      const response = await fetch(editor.action, {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify(data),
      });
      const result = await response.json();
      if (!response.ok) {
        const errors = Object.values(result.errors || {}).flat();
        throw new Error(errors[0] || result.message || "Could not save the address. Please try again.");
      }
      const address = result.address;
      const existing = addresses.findIndex((item) => Number(item.id) === Number(address.id));
      if (address.is_default) addresses.forEach((item) => (item.is_default = false));
      if (existing >= 0) addresses[existing] = address;
      else addresses.push(address);

      let card = document.querySelector('[data-address-card="' + address.id + '"]');
      if (!card) {
        card = document.createElement("label");
        card.className = "checkout-address-card";
        card.dataset.addressCard = address.id;
        card.innerHTML = '<input type="radio" name="delivery_address_choice"><span class="checkout-address-text"><b></b><span></span><small></small></span><button type="button" class="edit-address-button">Edit</button><button type="button" class="deliver-address-button">Deliver to this address</button>';
        choices.append(card);
      }
      card.querySelector("input").value = address.id;
      card.querySelector("b").textContent = address.label + " · " + address.name;
      card.querySelector(".checkout-address-text > span").textContent =
        address.address + ", " + address.city + ", " + address.state + " " + address.pincode;
      card.querySelector("small").textContent = address.phone;
      card.querySelector("button").dataset.editAddress = address.id;
      card.querySelector(".deliver-address-button").dataset.deliverAddress = address.id;
      selectAddress(address);
      source.textContent = JSON.stringify(addresses);
      document.querySelector("#addressSaveStatus").textContent = "Address saved and selected for delivery.";
      dialog.close();
    } catch (error) {
      document.querySelector("#addressEditorError").textContent = error.message;
    } finally {
      button.disabled = false;
    }
  });
})();
