<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Institutional Scholarship Application</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"
  />
  <link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
    rel="stylesheet"
  />

  <style>
    body {
      font-family: "Poppins", sans-serif;
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
      0% {
        transform: translateY(0);
      }
      50% {
        transform: translateY(-6px);
      }
      100% {
        transform: translateY(0);
      }
    }

    .animate-fade-in-up {
      opacity: 0;
      animation: fadeInUp 0.8s ease-out forwards;
    }

    .animate-float {
      animation: float 3s ease-in-out infinite;
    }

    .delay-1 {
      animation-delay: 0.15s;
    }
    .delay-2 {
      animation-delay: 0.3s;
    }
    .delay-3 {
      animation-delay: 0.45s;
    }
  </style>
</head>
<body class="bg-gradient-to-b from-[#e8f3ff] via-white to-[#e8f3ff] text-[#052c6a] overflow-x-hidden">

<!-- HERO / HEADER -->
<header class="relative flex flex-col items-center text-center px-4 overflow-hidden pb-20 md:pb-16 pt-10">
  <!-- Soft blobs / background shapes -->
  <div class="pointer-events-none absolute inset-0 -z-10">
    <div class="absolute -top-24 -left-10 h-56 w-56 bg-blue-300/30 blur-3xl rounded-full"></div>
    <div class="absolute -bottom-28 right-0 h-72 w-72 bg-yellow-300/40 blur-3xl rounded-full"></div>
    <div class="absolute top-20 left-1/2 -translate-x-1/2 h-64 w-64 bg-blue-200/25 blur-3xl rounded-full"></div>
  </div>

  <!-- Background image (subtle) -->
  <div class="absolute inset-0 -z-20 opacity-30">
    <img
      src="img/smccbackandlogo (2).png"
      alt="Background"
      class="w-full h-full object-cover"
    />
  </div>

  <!-- Semi-transparent overlay to blend image -->
  <div class="absolute inset-0 -z-10 bg-gradient-to-b from-[#003b7d]/70 via-[#003b7d]/40 to-transparent"></div>

  <!-- Content -->
  <div class="relative z-10 max-w-3xl mx-auto animate-fade-in-up">
    <img
      class="w-20 h-20 mx-auto rounded-full bg-white shadow-xl border-4 border-white animate-float"
      src="img/SMCCNEWLOGO.png"
      alt="SMCC Logo"
    />

    <h1 class="text-white font-extrabold text-3xl md:text-4xl leading-tight mt-5 drop-shadow-sm">
      Institutional Scholarship Management System
    </h1>

    <p class="text-white mt-3 text-sm md:text-base max-w-md mx-auto">
      SMCC Admission and Scholarship Office
    </p>

    <button
      class="mt-6 md:mt-8 bg-[#fcdc2f] text-[#052c6a] font-semibold rounded-full px-6 py-2.5 md:px-7 md:py-3 text-sm md:text-base shadow-md hover:bg-[#ffe45c] hover:shadow-lg transition-transform duration-200 hover:-translate-y-1"
      type="button"
      onclick="window.location.href='Applicant/applicationReq.php'"
    >
      Proceed to Application
      <i class="fas fa-arrow-right ml-2 text-xs"></i>
    </button>
  </div>

  <!-- Contact Details -->
  <div class="absolute bottom-0 left-0 w-full z-10">
    <div
      class="flex flex-col md:flex-row flex-wrap justify-center items-center gap-2 md:gap-4 lg:gap-6 px-3 py-2
             text-white text-xs sm:text-sm bg-[#003b7d]/80 backdrop-blur-md"
    >
      <div class="flex items-center gap-2">
        <i class="fas fa-phone-alt"></i>
        <a href="tel:+63853433251" class="hover:underline">(085) 343-3251</a>
      </div>
      <div class="hidden md:inline-block opacity-30">|</div>
      <div class="flex items-center gap-2">
        <i class="fas fa-envelope"></i>
        <a href="mailto:scholarship@smccnasipit.edu.ph" class="hover:underline">
          scholarship@smccnasipit.edu.ph
        </a>
      </div>
      <div class="hidden md:inline-block opacity-30">|</div>
      <div class="flex items-center gap-2 text-center">
        <i class="fas fa-map-marker-alt"></i>
        <span>Brgy. 4, Nasipit, Agusan del Norte</span>
      </div>
    </div>
  </div>
</header>

<!-- MAIN -->
<main class="py-16 px-4 max-w-7xl mx-auto">
  <!-- Login Portal Title -->
  <section class="text-center max-w-xl mx-auto mb-10 animate-fade-in-up delay-1">
    <h2 class="text-[#003b7d] font-extrabold text-2xl mb-2 uppercase tracking-wide">
      Login Portal
    </h2>
    <p class="text-sm text-[#052c6a]/80">
      Choose your login role to proceed:
    </p>
  </section>

  <!-- Login Cards -->
  <section
    class="grid grid-cols-1 sm:grid-cols-3 gap-6 animate-fade-in-up delay-2"
  >
    <!-- Admin -->
    <div
      class="bg-white/80 rounded-2xl shadow-md p-6 flex flex-col items-center border border-[#dbe6ff] backdrop-blur-xl
             hover:shadow-xl hover:-translate-y-1 hover:border-[#0d8ddb]/70 transition-all duration-200"
    >
      <div
        class="bg-gradient-to-br from-[#0d8ddb] to-[#003b7d] rounded-full p-5 mb-4 shadow-md text-white animate-float"
      >
        <i class="fas fa-user-cog text-2xl"></i>
      </div>
      <h3 class="font-extrabold text-lg mb-2 text-[#222222]">
        Admin
      </h3>
      <button
        class="bg-[#fcdc2f] text-[#052c6a] font-semibold rounded-full w-full py-2 text-sm hover:bg-[#ffe45c] shadow transition"
        type="button"
        onclick="window.location.href='Admin/adminLogin.php'"
      >
        Login
      </button>
    </div>

    <!-- Panel -->
    <div
      class="bg-white/80 rounded-2xl shadow-md p-6 flex flex-col items-center border border-[#dbe6ff] backdrop-blur-xl
             hover:shadow-xl hover:-translate-y-1 hover:border-[#0d8ddb]/70 transition-all duration-200"
    >
      <div
        class="bg-gradient-to-br from-[#0d8ddb] to-[#003b7d] rounded-full p-5 mb-4 shadow-md text-white animate-float"
      >
        <i class="fas fa-users text-2xl"></i>
      </div>
      <h3 class="font-extrabold text-lg mb-2 text-[#222222]">
        Panel
      </h3>
      <button
        class="bg-[#fcdc2f] text-[#052c6a] font-semibold rounded-full w-full py-2 text-sm hover:bg-[#ffe45c] shadow transition"
        type="button"
        onclick="window.location.href='panelLogin.html'"
      >
        Login
      </button>
    </div>

    <!-- Head of Office -->
    <div
      class="bg-white/80 rounded-2xl shadow-md p-6 flex flex-col items-center border border-[#dbe6ff] backdrop-blur-xl
             hover:shadow-xl hover:-translate-y-1 hover:border-[#0d8ddb]/70 transition-all duration-200"
    >
      <div
        class="bg-gradient-to-br from-[#0d8ddb] to-[#003b7d] rounded-full p-5 mb-4 shadow-md text-white animate-float"
      >
        <i class="fas fa-id-card text-2xl"></i>
      </div>
      <h3 class="font-extrabold text-lg mb-2 text-[#222222]">
        Head of Office
      </h3>
      <button
        class="bg-[#fcdc2f] text-[#052c6a] font-semibold rounded-full w-full py-2 text-sm hover:bg-[#ffe45c] shadow transition"
        type="button"
        onclick="window.location.href='headOfOffice.html'"
      >
        Login
      </button>
    </div>
  </section>
</main>

</body>
</html>
