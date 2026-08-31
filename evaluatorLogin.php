<?php
session_start();
require_once __DIR__ . "/db.php";

$loginError = "";
$roleOptions = isgEvaluatorRoles();
$selectedRole = isgNormalizeEvaluatorRole((string)($_POST["evaluator_role"] ?? "department_head"));
if ($selectedRole === "") {
  $selectedRole = "department_head";
}

$sessionRole = isgNormalizeEvaluatorRole((string)($_SESSION["evaluator_role"] ?? ""));
if (trim((string)($_SESSION["head_username"] ?? "")) !== "" && $sessionRole !== "") {
  header("Location: " . isgEvaluatorDashboardPath($sessionRole));
  exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $username = trim((string)($_POST["evaluator_username"] ?? ""));
  $password = (string)($_POST["evaluator_password"] ?? "");

  if ($username === "" || $password === "" || $selectedRole === "") {
    $loginError = "Please enter your username, password, and evaluator role.";
  } else {
    $user = isgLoadEvaluatorUser($conn, $username, $selectedRole);
    if (!$user || (string)($user["status"] ?? "") !== "active") {
      $loginError = "Invalid username, password, or role.";
    } elseif (isgVerifyEvaluatorPassword($password, (string)($user["password_hash"] ?? ""))) {
      isgSetEvaluatorSession($user);
      header("Location: " . isgEvaluatorDashboardPath($selectedRole));
      exit();
    } else {
      $loginError = "Invalid username, password, or role.";
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1" name="viewport"/>
  <title>Evaluator Login &bull; SMCC ISG</title>
  <link rel="icon" type="image/x-icon" href="img/SMCCNEWLOGO.png" />
  <link rel="stylesheet" href="assets/css/tailwind.css">
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
    #admin {
      background: radial-gradient(circle at top, #0d4faa 0, #052c6a 45%, #02193f 100%);
      position: relative;
      z-index: 10;
      border-radius: 1.75rem;
      box-shadow: 0 18px 40px rgba(0, 0, 0, 0.6);
      overflow: hidden;
    }
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
    @keyframes float {
      0% { transform: translateY(0); }
      50% { transform: translateY(-6px); }
      100% { transform: translateY(0); }
    }
    .logo-float { animation: float 3s ease-in-out infinite; }
    @keyframes fadeInUp {
      0% { opacity: 0; transform: translateY(20px); }
      100% { opacity: 1; transform: translateY(0); }
    }
    .animate-card { animation: fadeInUp 0.6s ease-out forwards; }
  </style>
</head>
<body>
  <div id="background-cover">
    <img src="img/smccbackground.png" alt="">
  </div>

  <div class="max-w-sm w-full animate-card" id="admin">
    <div class="relative p-8">
      <div class="flex justify-center mb-5">
        <img src="img/SMCCNEWLOGO.png" alt="SMCC logo" class="w-24 h-24 logo-float rounded-full bg-white shadow-xl border-4 border-white">
      </div>

      <h1 class="text-white text-2xl font-semibold text-center mb-5">Evaluator Login</h1>

      <form method="POST" class="space-y-5">
        <?php if ($loginError !== ""): ?>
          <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <?= htmlspecialchars($loginError) ?>
          </div>
        <?php endif; ?>

        <div>
          <label class="block text-yellow-300 font-semibold mb-2" for="role">Evaluator Role</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-3 flex items-center text-yellow-300/80">
              <i class="fas fa-user-tag text-sm"></i>
            </span>
            <select
              id="role"
              name="evaluator_role"
              class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-yellow-300/70 bg-white text-gray-900 focus:ring-2 focus:ring-yellow-300 focus:border-yellow-300"
              required
            >
              <?php foreach ($roleOptions as $role => $config): ?>
                <option value="<?= htmlspecialchars($role) ?>" <?= $selectedRole === $role ? "selected" : "" ?>>
                  <?= htmlspecialchars((string)$config["label"]) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div>
          <label class="block text-yellow-300 font-semibold mb-2" for="username">Username</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-3 flex items-center text-yellow-300/80">
              <i class="fas fa-user-shield text-sm"></i>
            </span>
            <input
              id="username"
              name="evaluator_username"
              type="text"
              placeholder="Enter username"
              class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-yellow-300/70 bg-white text-gray-900 focus:ring-2 focus:ring-yellow-300 focus:border-yellow-300"
              required
            />
          </div>
        </div>

        <div>
          <label class="block text-yellow-300 font-semibold mb-2" for="password">Password</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-3 flex items-center text-yellow-300/80">
              <i class="fas fa-lock text-sm"></i>
            </span>
            <input
              id="password"
              name="evaluator_password"
              type="password"
              placeholder="Enter password"
              class="w-full pl-10 pr-10 py-2.5 rounded-lg border border-yellow-300/70 bg-white text-gray-900 focus:ring-2 focus:ring-yellow-300 focus:border-yellow-300"
              required
            />
            <button type="button" id="togglePassword" class="absolute inset-y-0 right-3 flex items-center text-gray-600 hover:text-gray-800">
              <i id="eyeIcon" class="fas fa-eye-slash text-sm"></i>
            </button>
          </div>
        </div>

        <button class="w-full bg-yellow-400 hover:bg-yellow-300 text-blue-900 font-bold py-2.5 rounded-lg shadow-md transition" type="submit">
          Log In
        </button>

        <a href="index.php" class="block w-full text-center border border-white/45 text-white hover:bg-white/15 font-semibold py-2.5 rounded-lg transition">
          <i class="fas fa-arrow-left mr-2"></i>Back to Home
        </a>
      </form>
    </div>

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
