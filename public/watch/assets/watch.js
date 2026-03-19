function setupSwipe(container, onLeft, onRight) {
  let startX = 0;
  let startY = 0;

  container.addEventListener("touchstart", (e) => {
    const t = e.changedTouches[0];
    startX = t.clientX;
    startY = t.clientY;
  }, { passive: true });

  container.addEventListener("touchend", (e) => {
    const t = e.changedTouches[0];
    const dx = t.clientX - startX;
    const dy = t.clientY - startY;

    if (Math.abs(dx) < 35) return;
    if (Math.abs(dy) > Math.abs(dx) * 0.8) return;

    if (dx < 0 && typeof onLeft === "function") onLeft();
    if (dx > 0 && typeof onRight === "function") onRight();
  }, { passive: true });
}

function clamp(n, min, max) {
  return Math.max(min, Math.min(max, n));
}

function percent(n) {
  return `${clamp(n, 0, 100)}%`;
}
