const togglePassword = document.getElementById("togglePassword");
const passwordInput = document.getElementById("password");
const orbLayer = document.getElementById("orbLayer");

if (togglePassword && passwordInput) {
  togglePassword.addEventListener("click", () => {
    const isPassword = passwordInput.type === "password";
    passwordInput.type = isPassword ? "text" : "password";
    togglePassword.textContent = isPassword ? "Ukryj" : "Pokaż";
  });
}

function createOrbs() {
  if (!orbLayer) return;

  const isMobile = window.innerWidth < 700;
  const orbCount = isMobile ? 8 : 14;

  orbLayer.innerHTML = "";

  for (let i = 0; i < orbCount; i++) {
    const orb = document.createElement("span");
    orb.className = "orb";

    const size = Math.random() * (isMobile ? 80 : 120) + (isMobile ? 30 : 40);
    const left = Math.random() * 100;
    const top = Math.random() * 100;
    const duration = Math.random() * 12 + 12;
    const delay = Math.random() * 8;
    const hueVariant = i % 3;

    let color;
    if (hueVariant === 0) color = "rgba(155, 92, 255, 0.18)";
    if (hueVariant === 1) color = "rgba(57, 189, 248, 0.16)";
    if (hueVariant === 2) color = "rgba(21, 200, 155, 0.14)";

    orb.style.width = `${size}px`;
    orb.style.height = `${size}px`;
    orb.style.left = `${left}%`;
    orb.style.top = `${top}%`;
    orb.style.background = `
      radial-gradient(circle at 30% 30%, rgba(255,255,255,0.18), ${color} 45%, transparent 75%)
    `;
    orb.style.animationDuration = `${duration}s`;
    orb.style.animationDelay = `${delay}s`;

    orbLayer.appendChild(orb);
  }
}

createOrbs();
window.addEventListener("resize", createOrbs);

document.addEventListener("mousemove", (e) => {
  const x = (e.clientX / window.innerWidth - 0.5) * 12;
  const y = (e.clientY / window.innerHeight - 0.5) * 12;

  document.querySelectorAll(".bg-gradient").forEach((el, index) => {
    const factor = (index + 1) * 0.6;
    el.style.transform = `translate(${x * factor}px, ${y * factor}px)`;
  });
});