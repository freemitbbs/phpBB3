(function () {
  const config = window.freemitbbsCardGames?.sentry || {};
  const sentry = window.Sentry;
  if (!config.enabled || !config.dsn || !sentry?.init) {
    return;
  }

  const options = {
    dsn: config.dsn,
    environment: config.environment || undefined,
    release: config.release || undefined,
    sampleRate: boundedRate(config.sampleRate, 1),
    sendDefaultPii: false,
    maxBreadcrumbs: 80,
    allowUrls: [window.location.origin],
    tracePropagationTargets: [],
    beforeSend: (event) => scrubEvent(event)
  };
  const tracesSampleRate = boundedRate(config.tracesSampleRate, 0);
  if (tracesSampleRate > 0) {
    options.tracesSampleRate = tracesSampleRate;
    options.tracePropagationTargets = [window.location.origin, /^wss?:\/\/[^/]+\/card-games\/ws/];
  }

  sentry.init(options);
  if (sentry.setTag) {
    sentry.setTag("component", "cardgames_client");
    sentry.setTag("game", "tractor");
  }
  setSentryUser(config.user);
  window.freemitbbsCardGamesSentryReady = true;

  function boundedRate(value, fallback) {
    const rate = Number(value);
    if (!Number.isFinite(rate)) {
      return fallback;
    }
    return Math.max(0, Math.min(1, rate));
  }

  function setSentryUser(user) {
    if (!sentry.setUser || !user) {
      return;
    }
    const userId = Number(user.user_id ?? user.userId);
    if (Number.isInteger(userId) && userId > 0) {
      sentry.setUser({ id: String(userId) });
    }
  }

  function scrubEvent(event) {
    if (event.request?.url) {
      event.request.url = scrubUrl(event.request.url);
    }
    if (Array.isArray(event.breadcrumbs)) {
      event.breadcrumbs = event.breadcrumbs.map((breadcrumb) => {
        if (breadcrumb.data?.url) {
          breadcrumb.data.url = scrubUrl(breadcrumb.data.url);
        }
        if (breadcrumb.message) {
          breadcrumb.message = scrubText(breadcrumb.message);
        }
        return breadcrumb;
      });
    }
    return event;
  }

  function scrubUrl(value) {
    if (typeof value !== "string") {
      return value;
    }
    try {
      const url = new URL(value, window.location.href);
      Array.from(url.searchParams.keys()).forEach((key) => {
        if (isSensitiveKey(key)) {
          url.searchParams.set(key, "[redacted]");
        }
      });
      return url.toString();
    } catch {
      return scrubText(value);
    }
  }

  function scrubText(value) {
    return typeof value === "string"
      ? value.replace(/([?&][^=]*(?:token|secret|password|authorization|hash)[^=]*=)[^&#]*/gi, "$1[redacted]")
      : value;
  }

  function isSensitiveKey(key) {
    return /token|secret|password|authorization|hash/i.test(String(key || ""));
  }
})();
