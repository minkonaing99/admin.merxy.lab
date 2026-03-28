"use strict";

/**
 * Module: CSRF fetch patch.
 * Purpose: Reads the CSRF token from the page meta tag and automatically
 * attaches it as an `X-CSRF-Token` header on every state-changing fetch
 * request, so individual call sites don't need to manage it manually.
 */
(() => {
  const token =
    document.querySelector('meta[name="csrf-token"]')?.content ?? "";
  if (!token) return;

  const _fetch = window.fetch;
  window.fetch = function (resource, init) {
    init = init ?? {};
    const method = (init.method || "GET").toUpperCase();
    if (
      method === "POST" ||
      method === "PUT" ||
      method === "PATCH" ||
      method === "DELETE"
    ) {
      const headers = new Headers(init.headers ?? {});
      if (!headers.has("X-CSRF-Token")) {
        headers.set("X-CSRF-Token", token);
      }
      return _fetch.call(this, resource, { ...init, headers });
    }
    return _fetch.call(this, resource, init);
  };
})();
