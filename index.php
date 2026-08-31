<?php
session_start();
$_SESSION["from_index"] = true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>ISG Management System</title>
  <link rel="icon" type="image/x-icon" href="img/SMCCNEWLOGO.png" />
  <base href="/isg-system/" />
  <link rel="stylesheet" href="assets/css/tailwind.css">
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"
  />
  <link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
    rel="stylesheet"
  />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>

  <style>
    body {
      font-family: "Poppins", sans-serif;
    }

    /* Particles Container */
    #particles-js {
      position: fixed;
      width: 100%;
      height: 100%;
      top: 0;
      left: 0;
      z-index: 1;
      pointer-events: none;
    }

    /* Simple animations */
    @keyframes fadeInUp {
      0% {
        opacity: 0;
        transform: translateY(20px);
      }
      100% {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes float {
      0% { transform: translateY(0); }
      50% { transform: translateY(-6px); }
      100% { transform: translateY(0); }
    }

    /* ✨ NEW: Burst ripple on particle click */
    @keyframes rippleBurst {
      0%   { transform: scale(0); opacity: 0.7; }
      100% { transform: scale(3.5); opacity: 0; }
    }

    .ripple-burst {
      position: fixed;
      border-radius: 50%;
      width: 60px;
      height: 60px;
      pointer-events: none;
      z-index: 9999;
      animation: rippleBurst 0.6s ease-out forwards;
    }

    /* ✨ NEW: Star/sparkle pop */
    @keyframes starPop {
      0%   { opacity: 1; transform: translate(-50%, -50%) scale(0) rotate(0deg); }
      60%  { opacity: 1; transform: translate(-50%, -50%) scale(1.4) rotate(180deg); }
      100% { opacity: 0; transform: translate(-50%, -50%) scale(1) rotate(360deg); }
    }

    .star-pop {
      position: fixed;
      font-size: 20px;
      pointer-events: none;
      z-index: 9999;
      animation: starPop 0.7s ease-out forwards;
    }

    /* ✨ NEW: Trailing glow particles on mouse move */
    @keyframes trailFade {
      0%   { opacity: 0.85; transform: scale(1); }
      100% { opacity: 0; transform: scale(0); }
    }

    .trail-dot {
      position: fixed;
      border-radius: 50%;
      pointer-events: none;
      z-index: 9998;
      animation: trailFade 0.8s ease-out forwards;
    }

    /* ✨ NEW: Floating emoji burst */
    @keyframes emojiFly {
      0%   { opacity: 1; transform: translateY(0) scale(1); }
      100% { opacity: 0; transform: translateY(-80px) scale(1.5); }
    }

    .emoji-fly {
      position: fixed;
      pointer-events: none;
      z-index: 9999;
      font-size: 18px;
      animation: emojiFly 1s ease-out forwards;
    }

    .animate-fade-in-up {
      opacity: 0;
      animation: fadeInUp 0.8s ease-out forwards;
    }

    .animate-float {
      animation: float 3s ease-in-out infinite;
    }

    .delay-1 { animation-delay: 0.15s; }
    .delay-2 { animation-delay: 0.3s; }
    .delay-3 { animation-delay: 0.45s; }

    .portal-main {
      position: relative;
      isolation: isolate;
      overflow: hidden;
      background:
        radial-gradient(circle at 8% 18%, rgba(13, 141, 219, 0.16), transparent 34%),
        radial-gradient(circle at 92% 16%, rgba(252, 220, 47, 0.2), transparent 36%),
        linear-gradient(180deg, #f8fbff 0%, #edf4ff 45%, #e6eef9 100%);
      border-top: 1px solid rgba(13, 141, 219, 0.2);
    }

    .portal-main::before {
      content: "";
      position: absolute;
      inset: 0;
      z-index: -1;
      background-image:
        linear-gradient(rgba(13, 141, 219, 0.07) 1px, transparent 1px),
        linear-gradient(90deg, rgba(13, 141, 219, 0.07) 1px, transparent 1px);
      background-size: 38px 38px;
      opacity: 0.35;
      mask-image: linear-gradient(to bottom, rgba(0, 0, 0, 0.28), transparent 80%);
      pointer-events: none;
    }

    .portal-main::after {
      content: "";
      position: absolute;
      width: 22rem;
      height: 22rem;
      left: -7rem;
      bottom: -10rem;
      z-index: -1;
      border-radius: 9999px;
      background: radial-gradient(circle, rgba(13, 141, 219, 0.2), rgba(13, 141, 219, 0));
      pointer-events: none;
    }

    .portal-main-inner {
      max-width: 80rem;
      margin: 0 auto;
      position: relative;
      z-index: 10;
    }

    @keyframes heroSilverSweep {
      0% {
        background-position: 180% 50%;
      }
      100% {
        background-position: -40% 50%;
      }
    }

    .hero-title-shine {
      background-image: linear-gradient(
        102deg,
        #f4f7fb 0%,
        #ffffff 14%,
        #b8c4d1 27%,
        #ffffff 39%,
        #d7dfe8 53%,
        #ffffff 66%,
        #aab6c3 82%,
        #f7fbff 100%
      );
      background-size: 240% 100%;
      background-position: 180% 50%;
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      -webkit-text-fill-color: transparent;
      animation: heroSilverSweep 6.2s linear infinite;
      filter: drop-shadow(0 3px 12px rgba(5, 44, 106, 0.24));
    }

    .hero-subtitle-shine {
      background-image: linear-gradient(
        100deg,
        #dfe6ee 0%,
        #ffffff 20%,
        #c2ccd7 36%,
        #ffffff 52%,
        #b7c3cf 72%,
        #eef3f8 100%
      );
      background-size: 220% 100%;
      background-position: 180% 50%;
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      -webkit-text-fill-color: transparent;
      animation: heroSilverSweep 7.2s linear infinite;
      filter: drop-shadow(0 2px 10px rgba(5, 44, 106, 0.18));
    }

    @keyframes ctaSilverSweep {
      0% {
        transform: translateX(-165%) skewX(-24deg);
        opacity: 0;
      }
      12% {
        opacity: 0.15;
      }
      48% {
        opacity: 0.85;
      }
      100% {
        transform: translateX(165%) skewX(-24deg);
        opacity: 0;
      }
    }

    .hero-cta-shine {
      position: relative;
      overflow: hidden;
      isolation: isolate;
      box-shadow:
        0 14px 30px -18px rgba(252, 220, 47, 0.85),
        0 10px 22px -16px rgba(5, 44, 106, 0.48);
    }

    .hero-cta-shine::after {
      content: "";
      position: absolute;
      inset: -35% auto -35% -22%;
      width: 38%;
      pointer-events: none;
      background: linear-gradient(
        90deg,
        rgba(255, 255, 255, 0) 0%,
        rgba(255, 255, 255, 0.14) 18%,
        rgba(255, 255, 255, 0.82) 50%,
        rgba(214, 220, 230, 0.46) 66%,
        rgba(255, 255, 255, 0) 100%
      );
      filter: blur(1px);
      animation: ctaSilverSweep 4.8s ease-in-out infinite;
    }
    
    header, main {
      position: relative;
      z-index: 10;
    }
  </style>
</head>
<body class="bg-gradient-to-b from-[#e8f3ff] via-white to-[#e8f3ff] text-[#052c6a] overflow-x-hidden">

<div id="particles-js"></div>

<header class="relative flex flex-col items-center text-center px-4 overflow-hidden pb-8 md:pb-16 pt-10">
  <div class="pointer-events-none absolute inset-0 -z-10">
    <div class="absolute -top-24 -left-10 h-56 w-56 bg-blue-300/30 blur-3xl rounded-full"></div>
    <div class="absolute -bottom-28 right-0 h-72 w-72 bg-yellow-300/40 blur-3xl rounded-full"></div>
    <div class="absolute top-20 left-1/2 -translate-x-1/2 h-64 w-64 bg-blue-200/25 blur-3xl rounded-full"></div>
  </div>

  <div class="absolute inset-0 -z-20 opacity-30">
    <img src="img/smccbackandlogo%20(2).png" alt="Background" class="w-full h-full object-cover" />
  </div>

  <div class="absolute inset-0 -z-10 bg-gradient-to-b from-[#003b7d]/70 via-[#003b7d]/40 to-transparent"></div>

  <div class="relative z-10 max-w-3xl mx-auto animate-fade-in-up">
    <img class="w-20 h-20 mx-auto rounded-full bg-white shadow-xl border-4 border-white animate-float" src="img/admission-logo.jpg" alt="SMCC Logo" />
    <h1 class="hero-title-shine font-extrabold text-3xl md:text-4xl leading-tight mt-5">
      Institutional Scholarship Management System
    </h1>
    <p class="hero-subtitle-shine mt-3 text-sm md:text-base max-w-md mx-auto font-medium">
      SMCC Admission and Scholarship Office
    </p>
    <button id="applyBtn" class="hero-cta-shine mt-6 md:mt-8 bg-[#fcdc2f] text-[#052c6a] font-semibold rounded-full px-6 py-2.5 md:px-7 md:py-3 text-sm md:text-base shadow-md hover:bg-[#ffe45c] hover:shadow-lg transition-transform duration-200 hover:-translate-y-1" type="button">
      Click Here to Apply
      <i class="fas fa-arrow-right ml-2 text-xs"></i>
    </button>
  </div>

  <div class="relative mt-8 w-full z-10 md:absolute md:bottom-0 md:left-0 md:mt-0">
    <div class="flex flex-col md:flex-row flex-wrap justify-center items-center gap-2 md:gap-4 lg:gap-6 px-3 py-2 text-white text-xs sm:text-sm bg-[#003b7d]/80 backdrop-blur-md">
      <div class="flex items-center gap-2">
        <i class="fas fa-phone-alt"></i>
        <a class="hover:underline">0966 947 7833</a>
      </div>
      <div class="hidden md:inline-block opacity-30">|</div>
      <div class="flex items-center gap-2">
        <i class="fas fa-envelope"></i>
        <a href="mailto:osas-scholarship@smccnasipit.edu.ph" class="hover:underline">osas-scholarship@smccnasipit.edu.ph</a>
      </div>
      <div class="hidden md:inline-block opacity-30">|</div>
      <div class="flex items-center gap-2">
        <i class="fab fa-facebook"></i>
        <a href="mailto:osas-scholarship@smccnasipit.edu.ph" class="hover:underline">SMCC Admission and Scholarship Office</a>
      </div>
      <div class="hidden md:inline-block opacity-30">|</div>
      <div class="flex items-center gap-2 text-center">
        <i class="fas fa-map-marker-alt"></i>
        <span>Brgy. 4, Atupan St. Nasipit, Agusan del Norte</span>
      </div>
    </div>
  </div>
</header>

<main class="portal-main py-16 px-4">
  <div class="portal-main-inner">
  <section class="text-center max-w-xl mx-auto mb-10 animate-fade-in-up delay-1">
    <h2 class="text-[#003b7d] font-extrabold text-2xl mb-2 uppercase tracking-wide">Login Portal</h2>
    <p class="text-sm text-[#052c6a]/80">Choose your login role to proceed:</p>
  </section>

  <section class="grid grid-cols-1 sm:grid-cols-3 gap-6 animate-fade-in-up delay-2">
    <div class="bg-white/80 rounded-2xl shadow-md p-6 flex flex-col items-center border border-[#dbe6ff] backdrop-blur-xl hover:shadow-xl hover:-translate-y-1 hover:border-[#0d8ddb]/70 transition-all duration-200">
      <div class="bg-gradient-to-br from-[#0d8ddb] to-[#003b7d] rounded-full p-5 mb-4 shadow-md text-white animate-float">
        <i class="fas fa-user-cog text-2xl"></i>
      </div>
      <h3 class="font-extrabold text-lg mb-2 text-[#222222]">Admin</h3>
      <button class="bg-[#fcdc2f] text-[#052c6a] font-semibold rounded-full w-full py-2 text-sm hover:bg-[#ffe45c] shadow transition" type="button" onclick="window.location.href='Admin/adminLogin.php'">
        Login
      </button>
    </div>

    <div class="bg-white/80 rounded-2xl shadow-md p-6 flex flex-col items-center border border-[#dbe6ff] backdrop-blur-xl hover:shadow-xl hover:-translate-y-1 hover:border-[#0d8ddb]/70 transition-all duration-200">
      <div class="bg-gradient-to-br from-[#0d8ddb] to-[#003b7d] rounded-full p-5 mb-4 shadow-md text-white animate-float">
        <i class="fas fa-users text-2xl"></i>
      </div>
      <h3 class="font-extrabold text-lg mb-2 text-[#222222]">Panel</h3>
      <button class="bg-[#fcdc2f] text-[#052c6a] font-semibold rounded-full w-full py-2 text-sm hover:bg-[#ffe45c] shadow transition" type="button" onclick="window.location.href='Panelist/panelLogin.php'">
        Login
      </button>
    </div>

    <div class="bg-white/80 rounded-2xl shadow-md p-6 flex flex-col items-center border border-[#dbe6ff] backdrop-blur-xl hover:shadow-xl hover:-translate-y-1 hover:border-[#0d8ddb]/70 transition-all duration-200">
      <div class="bg-gradient-to-br from-[#0d8ddb] to-[#003b7d] rounded-full p-5 mb-4 shadow-md text-white animate-float">
        <i class="fas fa-id-card text-2xl"></i>
      </div>
      <h3 class="font-extrabold text-lg mb-2 text-[#222222]">Evaluators</h3>
      <button class="bg-[#fcdc2f] text-[#052c6a] font-semibold rounded-full w-full py-2 text-sm hover:bg-[#ffe45c] shadow transition" type="button" onclick="window.location.href='evaluatorLogin.php'">
        Login
      </button>
    </div>
  </section>
  </div>
</main>

<footer class="mt-6 px-4 pb-6 md:mt-8">
  <div class="mx-auto max-w-4xl text-center text-[11px] leading-[1.35] text-[#6f6f6f] sm:text-[12px]">
    <p>&copy; 2026 Saint Michael College of Caraga | All Rights Reserved</p>
    <p>Tabanao, Jhon Ivan.</p>
    <p>Adviser: Rea Mie A. Omas-as</p>
    <p>CCIS</p>
  </div>
</footer>

<script>
  particlesJS("particles-js", {
    "particles": {
      "number": {
        "value": 120,
        "density": { "enable": true, "value_area": 900 }
      },
      "color": {
        // Blue, yellow, white, light cyan — brand-consistent
        "value": ["#0d8ddb", "#fcdc2f", "#ffffff", "#7dd3fc", "#fde68a", "#38bdf8"]
      },
      "shape": {
        // Mix of circles, triangles, and edge shapes for variety
        "type": ["circle", "triangle", "edge"],
        "stroke": { "width": 0, "color": "#000000" },
        "polygon": { "nb_sides": 5 }
      },
      "opacity": {
        "value": 0.65,
        "random": true,
        "anim": {
          "enable": true,
          "speed": 1.2,
          "opacity_min": 0.1,
          "sync": false
        }
      },
      "size": {
        "value": 5,
        "random": true,
        "anim": {
          // Particles breathe/pulse in size
          "enable": true,
          "speed": 3,
          "size_min": 0.5,
          "sync": false
        }
      },
      "line_linked": {
        "enable": true,
        "distance": 130,
        "color": "#0d8ddb",
        "opacity": 0.25,
        "width": 1.2
      },
      "move": {
        "enable": true,
        "speed": 2.8,
        "direction": "none",
        "random": true,         // More organic, varied movement
        "straight": false,
        "out_mode": "bounce",   // Bounce off edges instead of disappearing
        "bounce": true,
        "attract": {
          // Particles slightly attract each other — creates clustering
          "enable": true,
          "rotateX": 600,
          "rotateY": 1200
        }
      }
    },
    "interactivity": {
      "detect_on": "window",
      "events": {
        "onhover": {
          "enable": true,
          "mode": "bubble"        // Particles swell up on hover
        },
        "onclick": {
          "enable": true,
          "mode": "repulse"       // Satisfying repulse burst on click
        },
        "resize": true
      },
      "modes": {
        "bubble": {
          "distance": 180,
          "size": 10,             // Particles grow big near cursor
          "duration": 2,
          "opacity": 0.85,
          "speed": 3
        },
        "repulse": {
          "distance": 200,        // Wide repulse area on click
          "duration": 0.6
        },
        "push": {
          "particles_nb": 6
        },
        "grab": {
          "distance": 140,
          "line_linked": { "opacity": 0.8 }
        }
      }
    },
    "retina_detect": true
  });

  // ============================================================
  // ✨ MOUSE TRAIL — glowing dots follow the cursor
  // ============================================================
  const trailColors = ["#0d8ddb", "#fcdc2f", "#7dd3fc", "#fde68a", "#ffffff"];
  let lastTrail = 0;

  document.addEventListener("mousemove", (e) => {
    const now = Date.now();
    if (now - lastTrail < 40) return; // throttle: one dot every 40ms
    lastTrail = now;

    const dot = document.createElement("div");
    dot.className = "trail-dot";
    const size = Math.random() * 8 + 4;
    const color = trailColors[Math.floor(Math.random() * trailColors.length)];
    dot.style.cssText = `
      left: ${e.clientX - size / 2}px;
      top: ${e.clientY - size / 2}px;
      width: ${size}px;
      height: ${size}px;
      background: ${color};
      box-shadow: 0 0 ${size * 2}px ${color};
      animation-duration: ${Math.random() * 0.4 + 0.5}s;
    `;
    document.body.appendChild(dot);
    dot.addEventListener("animationend", () => dot.remove());
  });

  // ============================================================
  // ✨ CLICK BURST — ripple + emoji pop on every click
  // ============================================================
  const rippleColors = ["rgba(13,141,219,0.4)", "rgba(252,220,47,0.5)", "rgba(125,211,252,0.4)"];

  document.addEventListener("click", (e) => {
    // Skip if clicking on a button/link (don't interfere with navigation)
    if (e.target.closest("button") || e.target.closest("a")) {
      // Still show burst but don't prevent default
    }

    // Ripple ring
    const ripple = document.createElement("div");
    ripple.className = "ripple-burst";
    const rColor = rippleColors[Math.floor(Math.random() * rippleColors.length)];
    ripple.style.cssText = `
      left: ${e.clientX - 30}px;
      top: ${e.clientY - 30}px;
      background: ${rColor};
      box-shadow: 0 0 20px ${rColor};
    `;
    document.body.appendChild(ripple);
    ripple.addEventListener("animationend", () => ripple.remove());

    // Emoji stars flying out
    const count = 3;
    for (let i = 0; i < count; i++) {
      const emoji = document.createElement("div");
      emoji.className = "emoji-fly";
      emoji.textContent = burstEmojis[Math.floor(Math.random() * burstEmojis.length)];
      const offsetX = (Math.random() - 0.5) * 60;
      emoji.style.cssText = `
        left: ${e.clientX + offsetX}px;
        top: ${e.clientY}px;
        animation-delay: ${i * 0.08}s;
        animation-duration: ${Math.random() * 0.4 + 0.8}s;
      `;
      document.body.appendChild(emoji);
      emoji.addEventListener("animationend", () => emoji.remove());
    }
  });

  // ============================================================
  // Applicant portal entry
  // ============================================================
  document.getElementById("applyBtn").addEventListener("click", () => {
    Swal.fire({
      html: `
        <div class="flex flex-col items-center">
          <img src="img/SMCCNEWLOGO.png" alt="Loading Logo" class="w-20 h-20 animate-pulse mb-4" />
          <p class="text-sm text-gray-600">Loading applicant portal...</p>
        </div>
      `,
      showConfirmButton: false,
      allowOutsideClick: false,
      allowEscapeKey: false,
      background: "#ffffff",
      customClass: { popup: "rounded-2xl" },
      didOpen: () => { Swal.showLoading(); }
    });
    setTimeout(() => { window.location.href = "Applicant/applicant-portal.php"; }, 700);
  });

  window.addEventListener("pageshow", function (event) {
    if (event.persisted) { Swal.close(); }
  });
</script>
</body>
</html>
