<?php
require_once __DIR__ . "/panelist-auth.php";
require_once "../db.php";

$loginError = "";
if (panelistIsAuthenticated()) {
  header("Location: " . panelistPath("panelistDashboard.php"));
  exit();
}
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $panelistName = trim((string)($_POST["panelist_name"] ?? ""));
  $panelistPassword = trim((string)($_POST["panelist_password"] ?? ""));

  if ($panelistName === "" || $panelistPassword === "") {
    $loginError = "Please enter your username and password.";
  } else {
    $stmt = $conn->prepare("SELECT full_name, password_hash, status FROM panelists WHERE username = ? LIMIT 1");
    if ($stmt) {
      $stmt->bind_param("s", $panelistName);
      $stmt->execute();
      $result = $stmt->get_result();
      $row = $result ? $result->fetch_assoc() : null;
      $stmt->close();

      if (!$row || (string)($row["status"] ?? "") !== "active") {
        $loginError = "Invalid username or password.";
      } else {
        $storedHash = (string)($row["password_hash"] ?? "");
        $verified = false;
        if ($storedHash !== "") {
          if (strpos($storedHash, '$2y$') === 0 || strpos($storedHash, '$argon2') === 0) {
            $verified = password_verify($panelistPassword, $storedHash);
          } else {
            $verified = hash("sha256", $panelistPassword) === $storedHash;
          }
        }

      if ($verified) {
        $displayName = trim((string)($row["full_name"] ?? ""));
        if ($displayName === "") {
          $displayName = $panelistName;
        }
        $_SESSION["panelist_username"] = $panelistName;
        $_SESSION["panelist_name"] = $displayName;
        $_SESSION["panelist_login_success"] = true;
        header("Location: " . panelistConsumeRedirectTarget("panelistDashboard.php"));
        exit();
        }
        $loginError = "Invalid username or password.";
      }
    } else {
      $loginError = "Login error. Please try again.";
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1" name="viewport"/>
  <title>Panel Login</title>
  <link rel="icon" type="image/x-icon" href="../img/SMCCNEWLOGO.png" />
  <link rel="stylesheet" href="../assets/css/tailwind.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>

  <style>
    body {
      font-family: 'Inter', sans-serif;
      margin: 0;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1rem;
      background-color: #02193f;
      overflow: hidden;
    }

    /* background cover */
    #background-cover {
      position: fixed;
      inset: 0;
      z-index: 0;
      overflow: hidden;
      pointer-events: none;
      filter: brightness(0.35) saturate(1.1) blur(2px);
      transform: scale(1.05);
    }
    #background-cover img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    /* card */
    #admin {
      background: radial-gradient(circle at top, #0d4faa 0, #052c6a 45%, #02193f 100%);
      position: relative;
      z-index: 10;
      border-radius: 1.75rem;
      box-shadow: 0 18px 40px rgba(0, 0, 0, 0.6);
      overflow: hidden;
    }

    /* glow border */
    #admin::before {
      content: "";
      position: absolute;
      inset: -1px;
      border-radius: 1.8rem;
      padding: 1px;
      background:
    linear-gradient(#052c6a, #052c6a) padding-box,
    linear-gradient(135deg, #fcdc2f, #2dd4bf, #60a5fa) border-box;
      opacity: 0.45;
      pointer-events: none;
    }

    /* float animation */
    @keyframes float {
      0% { transform: translateY(0); }
      50% { transform: translateY(-6px); }
      100% { transform: translateY(0); }
    }
    .logo-float {
      animation: float 3s ease-in-out infinite;
    }

    /* fade */
    @keyframes fadeInUp {
      0% { opacity: 0; transform: translateY(20px); }
      100% { opacity: 1; transform: translateY(0); }
    }
    .animate-card {
      animation: fadeInUp 0.6s ease-out forwards;
    }

  </style>
</head>

<body>

  <!-- background -->
  <div id="background-cover">
    <img src="../img/smccbackground.png" alt="">
  </div>

  <!-- card -->
  <div class="max-w-sm w-full animate-card" id="admin">

    <div class="relative p-8">
      <div class="flex justify-center mb-5">
        <img src="../img/SMCCNEWLOGO.png" alt="SMCC logo" class="w-24 h-24 logo-float rounded-full bg-white shadow-xl border-4 border-white">
      </div>

      <h1 class="text-white text-2xl font-semibold text-center mb-5">
        Panel Login
      </h1>

      <form method="POST" class="space-y-5">
        <?php if ($loginError !== ""): ?>
          <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <?= htmlspecialchars($loginError) ?>
          </div>
        <?php endif; ?>

        <!-- username -->
        <div>
          <label class="block text-yellow-300 font-semibold mb-2" for="username">Username</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-3 flex items-center text-yellow-300/80">
              <i class="fas fa-user-shield text-sm"></i>
            </span>
            <input 
              id="username" 
              name="panelist_name"
              type="text"
              placeholder="Enter username"
              class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-yellow-300/70 bg-white text-gray-900
                     focus:ring-2 focus:ring-yellow-300 focus:border-yellow-300"
            />
          </div>
        </div>

        <!-- password -->
        <div>
          <label class="block text-yellow-300 font-semibold mb-2" for="password">Password</label>

          <div class="relative">
            <span class="absolute inset-y-0 left-3 flex items-center text-yellow-300/80">
              <i class="fas fa-lock text-sm"></i>
            </span>

            <input 
              id="password"
              name="panelist_password"
              type="password"
              placeholder="Enter password"
              class="w-full pl-10 pr-10 py-2.5 rounded-lg border border-yellow-300/70 bg-white text-gray-900
                     focus:ring-2 focus:ring-yellow-300 focus:border-yellow-300"
            />

            <!-- EYE TOGGLE BUTTON -->
            <button 
              type="button"
              id="togglePassword"
              class="absolute inset-y-0 right-3 flex items-center text-gray-600 hover:text-gray-800"
            >
              <i id="eyeIcon" class="fas fa-eye-slash text-sm"></i>
            </button>
          </div>
        </div>

        <!-- login button -->
        <button
          class="w-full bg-yellow-400 hover:bg-yellow-300 text-blue-900 font-bold py-2.5 rounded-lg shadow-md transition"
          type="submit"
        >
          Log In
        </button>

        <a
          href="../index.php"
          class="block w-full text-center border border-white/45 text-white hover:bg-white/15 font-semibold py-2.5 rounded-lg transition"
        >
          <i class="fas fa-arrow-left mr-2"></i>Back to Home 
        </a>

      </form>
    </div>

    <!-- footer -->
    <div class="bg-yellow-400 py-2 text-center text-blue-900 font-semibold text-sm rounded-b-3xl">
      <p>SMCC Institutional Scholarship Management System</p>
    </div>

  </div>

  <footer class="fixed bottom-4 left-0 right-0 z-10 px-4 text-center text-[11px] leading-[1.35] text-white/50 sm:text-[12px]">
    <p>&copy; 2026 Saint Michael College of Caraga | All Rights Reserved</p>
    <p>Tabanao, Jhon Ivan.</p>
    <p>Adviser: Rea Mie A. Omas-as</p>
    <p>CCIS</p>
  </footer>

<script>
  const toggleBtn = document.getElementById("togglePassword");
  const password = document.getElementById("password");
  const eyeIcon = document.getElementById("eyeIcon");

  toggleBtn.addEventListener("click", () => {
    const type = password.type === "password" ? "text" : "password";
    password.type = type;

    eyeIcon.classList.toggle("fa-eye");
    eyeIcon.classList.toggle("fa-eye-slash");
  });
</script>

</body>
</html>
