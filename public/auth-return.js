(() => {
  const context = JSON.parse(document.querySelector("#shopContext")?.textContent || "{}");
  if (context.user) return;
  const base = new URL(context.base || location.origin);
  const basePath = base.pathname.replace(/\/$/, "");
  const authRoutes = [basePath + "/account", basePath + "/account/login", basePath + "/account/register"];
  if (authRoutes.slice(1).includes(location.pathname)) return;

  document.addEventListener("click", (event) => {
    const link = event.target.closest("a[href]");
    if (!link) return;
    const target = new URL(link.href, location.href);
    if (target.origin !== location.origin || !authRoutes.includes(target.pathname)) return;
    if (!target.searchParams.has("return_to")) {
      target.searchParams.set("return_to", location.href);
      link.href = target.href;
    }
  }, true);
})();
