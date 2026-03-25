<?php
session_start();
$_SESSION["from_index"] = true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Institutional Scholarship Application</title>
  <base href="/isg-system/" />
  <link rel="icon" type="image/x-icon" href="./img/SMCCNEWLOGO.png" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <style>
    body { font-family: "Poppins", sans-serif; }

    /* ── Base keyframes ───────────────────────────── */
    @keyframes fadeInUp    { 0%{opacity:0;transform:translateY(28px)}100%{opacity:1;transform:translateY(0)} }
    @keyframes zoomIn      { 0%{opacity:0;transform:scale(0.7) rotate(-4deg)}70%{transform:scale(1.06) rotate(1deg)}100%{opacity:1;transform:scale(1) rotate(0)} }
    @keyframes float       { 0%,100%{transform:translateY(0)}50%{transform:translateY(-7px)} }
    @keyframes shimmer     { 0%{background-position:-400px 0}100%{background-position:400px 0} }
    @keyframes cardPop     { 0%{opacity:0;transform:translateY(40px) scale(0.92)}60%{transform:translateY(-6px) scale(1.02)}100%{opacity:1;transform:translateY(0) scale(1)} }
    @keyframes contactUp   { 0%{opacity:0;transform:translateY(14px)}100%{opacity:1;transform:translateY(0)} }
    @keyframes overlayFade { 0%{opacity:0}100%{opacity:1} }
    @keyframes waveGrid    { 0%{background-position:0 0}100%{background-position:60px 60px} }
    @keyframes rippleAnim  { to{transform:scale(3.5);opacity:0} }
    @keyframes pulseRing   { 0%{box-shadow:0 0 0 0 var(--ring-color,rgba(13,141,219,.4))}70%{box-shadow:0 0 0 16px transparent}100%{box-shadow:0 0 0 0 transparent} }

    /* ── Shared UI ────────────────────────────────── */
    .animate-fade-in-up { opacity:0; animation:fadeInUp .8s ease-out forwards; }
    .animate-float      { animation:float 3s ease-in-out infinite; }
    .animate-zoom-in    { opacity:0; animation:zoomIn .7s cubic-bezier(.34,1.56,.64,1) forwards; }
    .delay-0{ animation-delay:0s   }.delay-1{ animation-delay:.15s }
    .delay-2{ animation-delay:.3s  }.delay-3{ animation-delay:.5s  }
    .delay-4{ animation-delay:.65s }.delay-5{ animation-delay:.8s  }

    .login-card {
      opacity:0; animation:cardPop .7s cubic-bezier(.34,1.56,.64,1) forwards;
      transition:transform .25s ease,box-shadow .25s ease,border-color .25s ease;
    }
    .login-card:nth-child(1){ animation-delay:.15s }
    .login-card:nth-child(2){ animation-delay:.32s }
    .login-card:nth-child(3){ animation-delay:.49s }
    .login-card:hover{ transform:translateY(-8px) scale(1.02); }

    .icon-circle{
      --ring-color:rgba(13,141,219,.4);
      animation:pulseRing 2.8s ease-in-out infinite;
      transition:transform .4s cubic-bezier(.34,1.56,.64,1);
    }
    .login-card:nth-child(2) .icon-circle{ animation-delay:.9s }
    .login-card:nth-child(3) .icon-circle{ animation-delay:1.8s }

    .login-btn{
      position:relative; overflow:hidden;
      transition:transform .2s ease,box-shadow .2s ease;
    }
    .login-btn:hover{ transform:scale(1.05); }
    .login-btn:active{ transform:scale(.97); }

    .contact-item{ opacity:0; animation:contactUp .6s ease-out forwards; }

    .reveal{
      opacity:0; transform:translateY(28px);
      transition:opacity .7s ease,transform .7s ease;
    }
    .reveal.visible{ opacity:1; transform:translateY(0); }

    .portal-main{
      position:relative; isolation:isolate; overflow:hidden;
      background:
        radial-gradient(circle at 8% 18%,rgba(13,141,219,.16),transparent 34%),
        radial-gradient(circle at 92% 16%,rgba(252,220,47,.2),transparent 36%),
        linear-gradient(180deg,#f8fbff 0%,#edf4ff 45%,#e6eef9 100%);
      border-top:1px solid rgba(13,141,219,.2);
    }
    .portal-main::before{
      content:""; position:absolute; inset:0; z-index:-1;
      background-image:
        linear-gradient(rgba(13,141,219,.07) 1px,transparent 1px),
        linear-gradient(90deg,rgba(13,141,219,.07) 1px,transparent 1px);
      background-size:38px 38px; opacity:.35;
      mask-image:linear-gradient(to bottom,rgba(0,0,0,.28),transparent 80%);
      pointer-events:none; animation:waveGrid 8s linear infinite;
    }
    .portal-main-inner{ max-width:80rem; margin:0 auto; }

    #applyBtn{
      position:relative; overflow:hidden;
      transition:transform .2s ease;
      opacity:0; animation:fadeInUp .8s .65s forwards;
    }
    #applyBtn::after{
      content:''; position:absolute; inset:0;
      background:linear-gradient(120deg,transparent 30%,rgba(255,255,255,.45) 50%,transparent 70%);
      background-size:200% 100%; animation:shimmer 2.6s linear infinite;
    }
    #applyBtn:hover{ transform:translateY(-3px) scale(1.04); }
    #applyBtn:active{ transform:scale(.97); }

    #season-badge{
      position:fixed; top:14px; right:16px; z-index:999;
      padding:5px 13px; border-radius:9999px;
      font-size:.7rem; font-weight:700; letter-spacing:.06em;
      backdrop-filter:blur(10px); border:1px solid rgba(255,255,255,.3);
      opacity:0; animation:fadeInUp .7s .8s forwards;
      box-shadow:0 2px 12px rgba(0,0,0,.15);
      cursor:default; user-select:none;
    }

    .footer{ opacity:0; animation:fadeInUp .8s 1.2s forwards; }

    #hero-canvas{
      position:absolute; inset:0; pointer-events:none; z-index:1;
    }
    #deco-layer{
      pointer-events:none; position:absolute; inset:0; overflow:hidden; z-index:0;
    }
    /* Full-page snow canvas — fixed so it covers everything including scroll */
    #snow-canvas{
      position:fixed; inset:0; width:100%; height:100%;
      pointer-events:none; z-index:9998;
      display:none; /* shown only in ber season via JS */
    }
  </style>
</head>
<body class="bg-gradient-to-b from-[#e8f3ff] via-white to-[#e8f3ff] text-[#052c6a] overflow-x-hidden">

<canvas id="snow-canvas"></canvas>
<div id="season-badge"></div>

<!-- ══════════════════════════════════════
     HERO
══════════════════════════════════════ -->
<header id="hero-header" class="relative flex flex-col items-center text-center px-4 overflow-hidden pb-20 md:pb-16 pt-10">
  <canvas id="hero-canvas"></canvas>
  <div id="deco-layer"></div>

  <div class="pointer-events-none absolute inset-0 -z-10">
    <div class="absolute -top-24 -left-10 h-56 w-56 bg-blue-300/30 blur-3xl rounded-full"></div>
    <div class="absolute -bottom-28 right-0 h-72 w-72 bg-yellow-300/40 blur-3xl rounded-full"></div>
    <div class="absolute top-20 left-1/2 -translate-x-1/2 h-64 w-64 bg-blue-200/25 blur-3xl rounded-full"></div>
  </div>

  <div class="absolute inset-0 -z-20 opacity-30">
    <img src="img/smccbackandlogo%20(2).png" alt="Background" class="w-full h-full object-cover"/>
  </div>
  <div id="hero-overlay" class="absolute inset-0 -z-10"
       style="opacity:0;animation:overlayFade 1.2s .1s forwards;"></div>

  <div class="relative z-10 max-w-3xl mx-auto">
    <img class="animate-zoom-in animate-float w-20 h-20 mx-auto rounded-full bg-white shadow-xl border-4 border-white"
         src="img/admission-logo.jpg" alt="SMCC Logo"/>
    <h1 class="animate-fade-in-up delay-2 text-white font-extrabold text-3xl md:text-4xl leading-tight mt-5 drop-shadow-sm">
      Institutional Scholarship Management System
    </h1>
    <p class="animate-fade-in-up delay-3 text-white mt-3 text-sm md:text-base max-w-md mx-auto">
      SMCC Admission and Scholarship Office
    </p>
    <button id="applyBtn"
      class="mt-3 md:mt-4 bg-[#fcdc2f] text-[#052c6a] font-semibold rounded-full px-6 py-2.5 md:px-7 md:py-3 text-sm md:text-base shadow-md hover:bg-[#ffe45c]"
      type="button">
      Click Here to Apply <i class="fas fa-arrow-right ml-2 text-xs"></i>
    </button>
  </div>

  <div class="absolute bottom-0 left-0 w-full z-10">
    <div class="flex flex-col md:flex-row flex-wrap justify-center items-center gap-2 md:gap-4 lg:gap-6 px-3 py-2
                text-white text-xs sm:text-sm bg-[#003b7d]/80 backdrop-blur-md">
      <div class="contact-item flex items-center gap-2" style="animation-delay:.7s">
        <i class="fas fa-phone-alt"></i>
        <a href="tel:+63853433251" class="hover:underline">(085) 343-3251</a>
      </div>
      <div class="hidden md:inline-block opacity-30 contact-item" style="animation-delay:.85s">|</div>
      <div class="contact-item flex items-center gap-2" style="animation-delay:.9s">
        <i class="fas fa-envelope"></i>
        <a href="mailto:scholarship@smccnasipit.edu.ph" class="hover:underline">scholarship@smccnasipit.edu.ph</a>
      </div>
      <div class="hidden md:inline-block opacity-30 contact-item" style="animation-delay:1s">|</div>
      <div class="contact-item flex items-center gap-2 text-center" style="animation-delay:1.05s">
        <i class="fas fa-map-marker-alt"></i>
        <span>Brgy. 4, Nasipit, Agusan del Norte</span>
      </div>
    </div>
  </div>
</header>

<!-- ══════════════════════════════════════
     MAIN
══════════════════════════════════════ -->
<main class="portal-main py-16 px-4">
  <div class="portal-main-inner">
    <section class="text-center max-w-xl mx-auto mb-10 reveal">
      <h2 class="text-[#003b7d] font-extrabold text-2xl mb-2 uppercase tracking-wide">Login Portal</h2>
      <p class="text-sm text-[#052c6a]/80">Choose your login role to proceed:</p>
    </section>

    <section class="grid grid-cols-1 sm:grid-cols-3 gap-6">
      <div class="login-card bg-white/80 rounded-2xl shadow-md p-6 flex flex-col items-center border border-[#dbe6ff] backdrop-blur-xl hover:shadow-xl">
        <div class="icon-circle bg-gradient-to-br from-[#0d8ddb] to-[#003b7d] rounded-full p-5 mb-4 shadow-md text-white animate-float">
          <i class="fas fa-user-cog text-2xl"></i>
        </div>
        <h3 class="font-extrabold text-lg mb-2 text-[#222222]">Admin</h3>
        <button class="login-btn bg-[#fcdc2f] text-[#052c6a] font-semibold rounded-full w-full py-2 text-sm hover:bg-[#ffe45c] shadow transition"
                type="button" onclick="window.location.href='Admin/adminLogin.php'">Login</button>
      </div>

      <div class="login-card bg-white/80 rounded-2xl shadow-md p-6 flex flex-col items-center border border-[#dbe6ff] backdrop-blur-xl hover:shadow-xl">
        <div class="icon-circle bg-gradient-to-br from-[#0d8ddb] to-[#003b7d] rounded-full p-5 mb-4 shadow-md text-white animate-float">
          <i class="fas fa-users text-2xl"></i>
        </div>
        <h3 class="font-extrabold text-lg mb-2 text-[#222222]">Panel</h3>
        <button class="login-btn bg-[#fcdc2f] text-[#052c6a] font-semibold rounded-full w-full py-2 text-sm hover:bg-[#ffe45c] shadow transition"
                type="button" onclick="window.location.href='Panelist/panelLogin.php'">Login</button>
      </div>

      <div class="login-card bg-white/80 rounded-2xl shadow-md p-6 flex flex-col items-center border border-[#dbe6ff] backdrop-blur-xl hover:shadow-xl">
        <div class="icon-circle bg-gradient-to-br from-[#0d8ddb] to-[#003b7d] rounded-full p-5 mb-4 shadow-md text-white animate-float">
          <i class="fas fa-id-card text-2xl"></i>
        </div>
        <h3 class="font-extrabold text-lg mb-2 text-[#222222]">Head of Office</h3>
        <button class="login-btn bg-[#fcdc2f] text-[#052c6a] font-semibold rounded-full w-full py-2 text-sm hover:bg-[#ffe45c] shadow transition"
                type="button" onclick="window.location.href='DepartmentHead/headLogin.php'">Login</button>
      </div>
    </section>
  </div>
</main>

<div class="footer text-center text-xs text-gray-400 mt-12 mb-6">
  <p>&copy; 2026 Saint Michael College of Caraga | All Rights Reserved</p>
  <p>Tabanao, Jhon Ivan</p>
  <p>Adviser: Rea Mie A. Omas-as</p>
  <p>CCIS</p>
</div>

<script>
/* ── Apply button (unchanged) ─────────────────── */
document.getElementById("applyBtn").addEventListener("click", () => {
  Swal.fire({
    html:`<div class="flex flex-col items-center">
            <img src="img/SMCCNEWLOGO.png" alt="" class="w-20 h-20 animate-pulse mb-4"/>
            <p class="text-sm text-gray-600">Loading application ...</p>
          </div>`,
    showConfirmButton:false,allowOutsideClick:false,allowEscapeKey:false,
    background:"#ffffff",customClass:{popup:"rounded-2xl"},
    didOpen:()=>Swal.showLoading()
  });
  setTimeout(()=>{ window.location.href="Applicant/applicationReq.php"; },700);
});
window.addEventListener("pageshow",e=>{ if(e.persisted) Swal.close(); });

/* ── Scroll reveal ────────────────────────────── */
const ro = new IntersectionObserver(es=>{
  es.forEach(e=>{ if(e.isIntersecting) e.target.classList.add('visible'); });
},{threshold:.15});
document.querySelectorAll('.reveal').forEach(el=>ro.observe(el));

/* ── Ripple on login buttons ──────────────────── */
document.querySelectorAll('.login-btn').forEach(btn=>{
  btn.addEventListener('click',function(e){
    const r=document.createElement('span'), rect=this.getBoundingClientRect();
    const sz=Math.max(rect.width,rect.height);
    r.style.cssText=`position:absolute;border-radius:50%;width:${sz}px;height:${sz}px;
      left:${e.clientX-rect.left-sz/2}px;top:${e.clientY-rect.top-sz/2}px;
      background:rgba(255,255,255,.55);transform:scale(0);
      animation:rippleAnim .55s linear;pointer-events:none;`;
    this.appendChild(r);
    r.addEventListener('animationend',()=>r.remove());
  });
});

/* ══════════════════════════════════════════════
   SEASON DETECTION (Philippine calendar)
   ─────────────────────────────────────────────
   summer  Mar–May   ☀️  Tag-Init / warm & dry
   rainy   Jun–Oct   🌧️  Tag-Ulan / typhoon
   ber     Nov–Dec   🎄  Ber months / Christmas
   amihan  Jan–Feb   🍃  Cool & dry / northeast monsoon
══════════════════════════════════════════════ */
const MONTH = new Date().getMonth()+1;
let SEASON =
  MONTH>=3  && MONTH<=5  ? 'summer' :
  MONTH>=6  && MONTH<=10 ? 'rainy'  :
  MONTH>=11 && MONTH<=12 ? 'ber'    : 'amihan';

const SEASONS = {
  summer:{
    badge:'☀️ Summer Season',
    badgeBg:'linear-gradient(135deg,#ff8c00,#ffd700)',
    badgeColor:'#3b1a00',
    overlay:'linear-gradient(180deg,rgba(110,40,0,.72) 0%,rgba(170,70,0,.42) 50%,transparent 100%)',
    btnGlowName:'summerGlow',
    btnGlowCss:'0%,100%{box-shadow:0 4px 28px rgba(255,180,0,.38)}50%{box-shadow:0 6px 44px rgba(255,120,0,.7)}',
    cardShadow:'0 20px 48px rgba(255,130,0,.22)',
    cardBorder:'rgba(255,160,0,.7)',
  },
  rainy:{
    badge:'🌧️ Rainy Season',
    badgeBg:'linear-gradient(135deg,#1865a0,#4ecdc4)',
    badgeColor:'#fff',
    overlay:'linear-gradient(180deg,rgba(8,25,70,.78) 0%,rgba(10,45,95,.45) 50%,transparent 100%)',
    btnGlowName:'rainyGlow',
    btnGlowCss:'0%,100%{box-shadow:0 4px 24px rgba(80,160,255,.32)}50%{box-shadow:0 6px 40px rgba(80,210,255,.65)}',
    cardShadow:'0 20px 48px rgba(30,120,240,.2)',
    cardBorder:'rgba(80,170,255,.7)',
  },
  ber:{
    badge:'🎄 Christmas Season',
    badgeBg:'linear-gradient(135deg,#b5192b,#e74c3c)',
    badgeColor:'#fff7e6',
    overlay:'linear-gradient(180deg,rgba(28,8,8,.8) 0%,rgba(55,10,10,.48) 50%,transparent 100%)',
    btnGlowName:'berGlow',
    btnGlowCss:'0%,100%{box-shadow:0 4px 24px rgba(220,50,50,.38)}50%{box-shadow:0 6px 44px rgba(255,110,0,.68)}',
    cardShadow:'0 20px 48px rgba(220,50,50,.25)',
    cardBorder:'rgba(255,100,0,.8)',
  },
  amihan:{
    badge:'🍃 Cool & Dry Season',
    badgeBg:'linear-gradient(135deg,#22a05a,#1abc9c)',
    badgeColor:'#002912',
    overlay:'linear-gradient(180deg,rgba(0,36,18,.75) 0%,rgba(0,55,28,.42) 50%,transparent 100%)',
    btnGlowName:'amihanGlow',
    btnGlowCss:'0%,100%{box-shadow:0 4px 24px rgba(60,200,120,.3)}50%{box-shadow:0 6px 40px rgba(40,180,100,.62)}',
    cardShadow:'0 20px 48px rgba(50,200,120,.2)',
    cardBorder:'rgba(80,220,150,.7)',
  }
};

const cfg = SEASONS[SEASON];

/* Badge */
const badge = document.getElementById('season-badge');
badge.textContent = cfg.badge;
badge.style.background = cfg.badgeBg;
badge.style.color       = cfg.badgeColor;

/* Overlay */
document.getElementById('hero-overlay').style.background = cfg.overlay;

/* Inject season-specific keyframes + apply button glow + card hover */
const styleEl = document.createElement('style');
styleEl.textContent = `
  @keyframes ${cfg.btnGlowName} { ${cfg.btnGlowCss} }
  #applyBtn { animation: ${cfg.btnGlowName} 2.4s ease-in-out infinite, shimmer 2.6s linear infinite, fadeInUp .8s .65s forwards !important; }
  .login-card:hover { box-shadow:${cfg.cardShadow} !important; border-color:${cfg.cardBorder} !important; }
  @keyframes rippleAnim { to { transform:scale(3.5); opacity:0; } }
`;
document.head.appendChild(styleEl);


/* ══════════════════════════════════════════════
   CANVAS PARTICLES
══════════════════════════════════════════════ */
(function(){
  const cv  = document.getElementById('hero-canvas');
  const ctx = cv.getContext('2d');
  let W, H, parts=[];

  function resize(){
    W=cv.width=cv.parentElement.offsetWidth;
    H=cv.height=cv.parentElement.offsetHeight;
  }

  const rand=(a,b)=>Math.random()*(b-a)+a;
  const randi=(a,b)=>~~rand(a,b);

  /* --- particle factories --- */
  function mkFlower(){
    const isSunflower = Math.random() > 0.45;
    return{
      x      : rand(0,W),
      y      : rand(-30, 0),
      r      : rand(isSunflower ? 10 : 7, isSunflower ? 20 : 14),
      vx     : rand(-.6, .6),
      vy     : rand(.55, 1.3),
      rot    : rand(0, Math.PI*2),
      rotV   : rand(-.025, .025),
      swayT  : rand(0, Math.PI*2),
      swayAmp: rand(.3, .8),
      alpha  : rand(.7, 1),
      life   : rand(280, 500),
      age    : 0,
      type   : isSunflower ? 'sunflower' : 'flower',
      petalColor: isSunflower
        ? `hsl(${randi(38,50)},100%,${randi(50,65)}%)`
        : `hsl(${randi(0,360)},80%,${randi(55,75)}%)`
    };
  }
  function mkRaindrop(){
    return{ x:rand(0,W), y:rand(-40,0), len:rand(12,26),
            vx:rand(1,2.5), vy:rand(14,24), alpha:rand(.3,.65),
            life:Math.ceil((H+40)/20), age:0 };
  }
  function mkSnowflake(){
    return{ x:rand(0,W), y:rand(-10,0), r:rand(2,5.5),
            vx:rand(-.6,.6), vy:rand(.5,1.5),
            swayT:rand(0,Math.PI*2), swayAmp:rand(.3,.9),
            alpha:rand(.5,.9), life:rand(250,450), age:0 };
  }
  function mkLeaf(){
    const cols=['80,160,60','60,140,50','110,185,65','205,160,45','225,120,30'];
    return{ x:rand(0,W), y:rand(-10,0), r:rand(4,9),
            vx:rand(.4,1.5), vy:rand(.8,2),
            rot:rand(0,360), rotV:rand(-3,3),
            alpha:rand(.55,.85), color:cols[randi(0,cols.length)],
            life:rand(200,380), age:0 };
  }

  const FACTORY = { summer:mkFlower,  rainy:mkRaindrop, ber:mkSnowflake, amihan:mkLeaf };
  const COUNT   = { summer:35,         rainy:90,         ber:65,           amihan:45 };

  function init(){
    resize();
    const cnt=COUNT[SEASON], mk=FACTORY[SEASON];
    parts=Array.from({length:cnt},()=>{
      const p=mk();
      // distribute vertically from the start
      p.y=rand(-H,H);
      return p;
    });
  }

  /* --- draw/update --- */
  function drawFlower(p){
    const prog = p.age/p.life;
    const a    = p.alpha * (1 - prog**2);
    const r    = p.r * (1 - prog*.3);
    ctx.save();
    ctx.globalAlpha = a;
    ctx.translate(p.x, p.y);
    ctx.rotate(p.rot);

    if(p.type === 'sunflower'){
      const petals = 13, pr = r*.72, ph = r*.38;
      /* petals */
      for(let i=0;i<petals;i++){
        ctx.save();
        ctx.rotate((Math.PI*2/petals)*i);
        ctx.beginPath();
        ctx.ellipse(0, -r*.62, ph, pr, 0, 0, Math.PI*2);
        ctx.fillStyle = p.petalColor;
        ctx.shadowColor = 'rgba(255,200,0,.4)';
        ctx.shadowBlur  = 6;
        ctx.fill();
        ctx.restore();
      }
      /* dark brown centre disk */
      ctx.beginPath();
      ctx.arc(0,0,r*.38,0,Math.PI*2);
      ctx.fillStyle='rgba(80,40,5,.9)';
      ctx.shadowColor='rgba(0,0,0,.3)';
      ctx.shadowBlur=4;
      ctx.fill();
      /* seed dots */
      ctx.fillStyle='rgba(255,200,100,.6)';
      ctx.shadowBlur=0;
      for(let d=0;d<6;d++){
        const a2=(Math.PI*2/6)*d;
        ctx.beginPath();
        ctx.arc(Math.cos(a2)*r*.18, Math.sin(a2)*r*.18, r*.07, 0, Math.PI*2);
        ctx.fill();
      }
    } else {
      /* generic 5-petal flower */
      const petals=5, pr=r*.55, ph=r*.3;
      for(let i=0;i<petals;i++){
        ctx.save();
        ctx.rotate((Math.PI*2/petals)*i);
        ctx.beginPath();
        ctx.ellipse(0,-r*.55,ph,pr,0,0,Math.PI*2);
        ctx.fillStyle=p.petalColor;
        ctx.shadowColor=p.petalColor;
        ctx.shadowBlur=5;
        ctx.fill();
        ctx.restore();
      }
      /* yellow centre */
      ctx.beginPath();
      ctx.arc(0,0,r*.28,0,Math.PI*2);
      ctx.fillStyle='rgba(255,230,50,.95)';
      ctx.shadowColor='rgba(255,200,0,.5)';
      ctx.shadowBlur=4;
      ctx.fill();
    }

    ctx.restore();
    ctx.globalAlpha = 1;
  }
  function moveFlower(p){
    p.swayT += .018;
    p.x     += p.vx + Math.sin(p.swayT)*p.swayAmp;
    p.y     += p.vy;
    p.rot   += p.rotV;
    p.age++;
  }

  function drawRaindrop(p){
    const a=p.alpha*(1-(p.age/p.life)**2);
    ctx.strokeStyle=`rgba(160,210,255,${a})`; ctx.lineWidth=.9;
    ctx.beginPath(); ctx.moveTo(p.x,p.y); ctx.lineTo(p.x-p.len*p.vx/p.vy,p.y-p.len); ctx.stroke();
  }
  function moveRaindrop(p){ p.x+=p.vx; p.y+=p.vy; p.age++; }

  function drawSnowflake(p){
    const prog=p.age/p.life, a=p.alpha*(1-prog**2);
    ctx.beginPath(); ctx.arc(p.x,p.y,p.r,0,Math.PI*2);
    ctx.fillStyle=`rgba(220,240,255,${a})`; ctx.fill();
    if(p.r>4){
      ctx.strokeStyle=`rgba(255,255,255,${a*.6})`; ctx.lineWidth=.7;
      ctx.beginPath(); ctx.moveTo(p.x-p.r*1.5,p.y); ctx.lineTo(p.x+p.r*1.5,p.y); ctx.stroke();
      ctx.beginPath(); ctx.moveTo(p.x,p.y-p.r*1.5); ctx.lineTo(p.x,p.y+p.r*1.5); ctx.stroke();
    }
  }
  function moveSnowflake(p){ p.swayT+=.022; p.x+=p.vx+Math.sin(p.swayT)*p.swayAmp; p.y+=p.vy; p.age++; }

  function drawLeaf(p){
    const prog=p.age/p.life, a=p.alpha*(1-prog**1.4);
    ctx.save(); ctx.translate(p.x,p.y); ctx.rotate(p.rot*Math.PI/180);
    ctx.beginPath(); ctx.ellipse(0,0,p.r,p.r*.55,0,0,Math.PI*2);
    ctx.fillStyle=`rgba(${p.color},${a})`; ctx.fill(); ctx.restore();
  }
  function moveLeaf(p){ p.x+=p.vx+Math.sin(p.age*.04)*.7; p.y+=p.vy; p.rot+=p.rotV; p.age++; }

  const DRAW = { summer:drawFlower,   rainy:drawRaindrop, ber:drawSnowflake, amihan:drawLeaf };
  const MOVE = { summer:moveFlower,   rainy:moveRaindrop, ber:moveSnowflake, amihan:moveLeaf };
  const draw = DRAW[SEASON], move = MOVE[SEASON];

  function loop(){
    ctx.clearRect(0,0,W,H);
    parts.forEach((p,i)=>{
      draw(p); move(p);
      if(p.age>=p.life || p.y>H+30 || p.x>W+30) parts[i]=FACTORY[SEASON]();
    });
    requestAnimationFrame(loop);
  }

  window.addEventListener('resize',resize);
  init(); loop();
})();


/* ══════════════════════════════════════════════
   DECORATIVE LAYER  (large seasonal elements)
══════════════════════════════════════════════ */
(function(){
  const deco = document.getElementById('deco-layer');
  const ks   = document.createElement('style');

  if(SEASON==='summer'){
    /* warm glowing sun + pollen haze band */
    ks.textContent=`
      @keyframes sunSpin   {0%{transform:rotate(0)  scale(1)   }50%{transform:rotate(180deg) scale(1.07)}100%{transform:rotate(360deg) scale(1)}}
      @keyframes pollenHaze{0%,100%{opacity:.12;transform:scaleX(1)}50%{opacity:.22;transform:scaleX(1.04)}}
      @keyframes petalWind {0%{transform:translateX(-120px);opacity:0}10%{opacity:.18}90%{opacity:.14}100%{transform:translateX(calc(100vw+120px));opacity:0}}
    `;
    deco.innerHTML=`
      <!-- glowing sun top-right -->
      <div style="position:absolute;top:-60px;right:-60px;width:220px;height:220px;border-radius:50%;
                  background:radial-gradient(circle,rgba(255,220,0,.65),rgba(255,150,0,.28),transparent 68%);
                  animation:sunSpin 24s linear infinite;"></div>
      <div style="position:absolute;top:2px;right:2px;width:105px;height:105px;border-radius:50%;
                  background:radial-gradient(circle,rgba(255,245,130,.6),rgba(255,190,0,.12),transparent 68%);
                  animation:sunSpin 13s linear infinite reverse;"></div>
      <!-- pollen / golden haze near bottom -->
      <div style="position:absolute;bottom:0;left:0;right:0;height:90px;
                  background:linear-gradient(to top,rgba(255,220,60,.13),transparent);
                  animation:pollenHaze 4s ease-in-out infinite;"></div>
      <!-- drifting petal wisps -->
      <div style="position:absolute;top:35%;width:200px;height:30px;border-radius:50%;
                  background:rgba(255,180,100,.12);filter:blur(8px);
                  animation:petalWind 18s linear infinite;"></div>
      <div style="position:absolute;top:55%;width:140px;height:22px;border-radius:50%;
                  background:rgba(255,200,80,.1);filter:blur(6px);
                  animation:petalWind 24s 7s linear infinite;"></div>
    `;
  }

  else if(SEASON==='rainy'){
    /* Canvas-drawn forked lightning bolts + cloud bands + thunder screen flash */
    ks.textContent=`
      @keyframes cloudMove{
        0%  {transform:translateX(-320px);opacity:0}
        8%  {opacity:.24}
        92% {opacity:.2}
        100%{transform:translateX(calc(100vw + 320px));opacity:0}
      }
      @keyframes screenFlash{
        0%,100%{opacity:0}
        2%,6%  {opacity:.13}
        4%     {opacity:.04}
      }
    `;
    deco.innerHTML=`
      <!-- screen flash on lightning strike -->
      <div id="thunder-flash" style="position:absolute;inset:0;background:#d0eaff;pointer-events:none;opacity:0;"></div>
      <!-- drifting cloud silhouettes -->
      <div style="position:absolute;top:10px;width:260px;height:70px;border-radius:35px;
                  background:rgba(150,190,240,.22);filter:blur(11px);
                  animation:cloudMove 26s linear infinite;"></div>
      <div style="position:absolute;top:38px;width:340px;height:55px;border-radius:28px;
                  background:rgba(130,175,230,.16);filter:blur(13px);
                  animation:cloudMove 36s 11s linear infinite;"></div>
      <div style="position:absolute;top:20px;width:180px;height:45px;border-radius:22px;
                  background:rgba(170,205,245,.18);filter:blur(9px);
                  animation:cloudMove 20s 5s linear infinite;"></div>
      <!-- lightning canvas -->
      <canvas id="lightning-canvas" style="position:absolute;inset:0;pointer-events:none;"></canvas>
    `;

    /* ── Lightning canvas engine ── */
    const lc  = document.getElementById('lightning-canvas');
    const lx  = lc.getContext('2d');
    const tf  = document.getElementById('thunder-flash');

    function resizeLc(){
      lc.width  = deco.offsetWidth;
      lc.height = deco.offsetHeight;
    }
    resizeLc();
    window.addEventListener('resize', resizeLc);

    const lrand = (a,b)=>Math.random()*(b-a)+a;

    /* Recursive forked bolt */
    function boltSegment(x1,y1,x2,y2,depth,alpha){
      if(depth<=0 || alpha<.05) return;
      const mx  = (x1+x2)/2 + lrand(-28,28)*(depth/5);
      const my  = (y1+y2)/2 + lrand(-10,10);

      lx.beginPath();
      lx.moveTo(x1,y1);
      lx.lineTo(mx,my);
      lx.lineTo(x2,y2);
      lx.strokeStyle=`rgba(200,230,255,${alpha})`;
      lx.lineWidth   = depth*.8;
      lx.shadowColor ='rgba(160,210,255,.9)';
      lx.shadowBlur  = 14;
      lx.stroke();

      boltSegment(x1,y1,mx,my,depth-1,alpha*.85);
      boltSegment(mx,my,x2,y2,depth-1,alpha*.85);

      /* random fork */
      if(depth>2 && Math.random()>.52){
        const fx = mx + lrand(-50,50);
        const fy = my + lrand(20,60);
        boltSegment(mx,my,fx,fy,depth-2,alpha*.5);
      }
    }

    function strikeAt(xStart){
      lx.clearRect(0,0,lc.width,lc.height);
      const xEnd = xStart + lrand(-40,40);
      boltSegment(xStart, 0, xEnd, lc.height*.85, 5, .95);

      /* flash the screen */
      tf.style.transition='opacity .04s';
      tf.style.opacity='.18';
      setTimeout(()=>{ tf.style.transition='opacity .18s'; tf.style.opacity='0'; },60);
      setTimeout(()=>{ tf.style.opacity='.08'; },120);
      setTimeout(()=>{ tf.style.opacity='0'; },200);

      /* fade bolt out */
      let fadeAlpha=1;
      const fade=setInterval(()=>{
        fadeAlpha-=.12;
        if(fadeAlpha<=0){ lx.clearRect(0,0,lc.width,lc.height); clearInterval(fade); return; }
        lx.globalAlpha=fadeAlpha;
        // redraw last frame is complex — just clear after short delay
      },40);
      setTimeout(()=>{ lx.clearRect(0,0,lc.width,lc.height); lx.globalAlpha=1; },350);
    }

    /* Schedule random strikes */
    function scheduleStrike(){
      const delay = lrand(2500,8000);
      setTimeout(()=>{
        const x = lrand(lc.width*.15, lc.width*.85);
        strikeAt(x);
        /* double-strike occasionally */
        if(Math.random()>.6){
          setTimeout(()=>strikeAt(x+lrand(-30,30)), lrand(100,260));
        }
        scheduleStrike();
      }, delay);
    }
    scheduleStrike();
  }

  else if(SEASON==='ber'){
    /* Christmas light string + star */
    ks.textContent=`
      @keyframes xmasLight{0%,100%{opacity:1;filter:brightness(1)}50%{opacity:.3;filter:brightness(.4)}}
      @keyframes starPulse{0%,100%{opacity:.35;transform:translateX(-50%) scale(.75)}50%{opacity:1;transform:translateX(-50%) scale(1.5)}}
    `;
    const colors=['#ff1a1a','#00cc44','#ffd700','#0099ff','#ff6600','#cc00cc'];
    let html='<div style="position:absolute;top:0;left:0;right:0;height:18px;white-space:nowrap;overflow:hidden;">';
    const spacing = Math.max(22, ~~(window.innerWidth / 38));
    const count   = ~~(window.innerWidth / spacing) + 2;
    for(let i=0;i<count;i++){
      const c=colors[i%colors.length], delay=(i*.17)%2.8;
      html+=`<span style="display:inline-block;width:10px;height:14px;border-radius:50% 50% 38% 38%;
                          background:${c};margin:0 ${spacing/2-5}px;vertical-align:bottom;
                          box-shadow:0 0 7px ${c};
                          animation:xmasLight ${.55+i*.06}s ${delay}s ease-in-out infinite;"></span>`;
    }
    html+=`</div>
      <div style="position:absolute;top:6px;left:50%;transform:translateX(-50%);font-size:1.9rem;line-height:1;
                  animation:starPulse 1.5s .2s ease-in-out infinite;">⭐</div>`;
    deco.innerHTML=html;
  }

  else if(SEASON==='amihan'){
    /* gentle mist bands */
    ks.textContent=`
      @keyframes mist{0%{opacity:0;transform:translateX(-5%)}45%{opacity:.9}100%{opacity:0;transform:translateX(6%)}}
    `;
    deco.innerHTML=`
      <div style="position:absolute;top:30%;left:0;right:0;height:55px;
                  background:linear-gradient(90deg,transparent,rgba(170,230,200,.18),transparent);
                  animation:mist 9s ease-in-out infinite;"></div>
      <div style="position:absolute;top:62%;left:0;right:0;height:38px;
                  background:linear-gradient(90deg,transparent,rgba(150,215,180,.13),transparent);
                  animation:mist 13s 5s ease-in-out infinite;"></div>
    `;
  }

  document.head.appendChild(ks);
})();

/* ══════════════════════════════════════════════
   FULL-PAGE SNOWFALL  (ber season only)
   Fixed canvas sits above everything, covers
   header + main + footer as you scroll.
══════════════════════════════════════════════ */
if(SEASON === 'ber'){
  (function(){
    const cv  = document.getElementById('snow-canvas');
    cv.style.display = 'block';
    const ctx = cv.getContext('2d');
    let W, H, flakes = [];

    function resize(){
      W = cv.width  = window.innerWidth;
      H = cv.height = window.innerHeight;
    }

    const rand = (a,b) => Math.random()*(b-a)+a;

    function mkFlake(){
      /* three visual styles: circle dot, sparkle cross, six-point star */
      const style = ['dot','cross','star'][~~rand(0,3)];
      return {
        x      : rand(0, W),
        y      : rand(-20, 0),
        r      : rand(2, 6),
        vx     : rand(-.5, .5),
        vy     : rand(.4, 1.4),
        alpha  : rand(.4, .9),
        swayT  : rand(0, Math.PI*2),
        swayAmp: rand(.25, .85),
        rot    : rand(0, Math.PI*2),
        rotV   : rand(-.015, .015),
        style,
        life   : rand(300, 600),
        age    : 0
      };
    }

    function drawDot(p, a){
      ctx.beginPath();
      ctx.arc(p.x, p.y, p.r, 0, Math.PI*2);
      ctx.fillStyle = `rgba(220,240,255,${a})`;
      ctx.fill();
    }

    function drawCross(p, a){
      const arm = p.r * 1.6;
      ctx.save();
      ctx.translate(p.x, p.y);
      ctx.rotate(p.rot);
      ctx.strokeStyle = `rgba(210,235,255,${a})`;
      ctx.lineWidth   = p.r * .55;
      ctx.lineCap     = 'round';
      ctx.beginPath(); ctx.moveTo(-arm,0); ctx.lineTo(arm,0); ctx.stroke();
      ctx.beginPath(); ctx.moveTo(0,-arm); ctx.lineTo(0,arm); ctx.stroke();
      ctx.restore();
    }

    function drawStar(p, a){
      const spikes = 6, outer = p.r * 1.4, inner = p.r * .55;
      ctx.save();
      ctx.translate(p.x, p.y);
      ctx.rotate(p.rot);
      ctx.fillStyle = `rgba(230,245,255,${a})`;
      ctx.beginPath();
      for(let i=0; i<spikes*2; i++){
        const rad = i%2===0 ? outer : inner;
        const angle = (Math.PI/spikes)*i - Math.PI/2;
        i===0 ? ctx.moveTo(Math.cos(angle)*rad, Math.sin(angle)*rad)
              : ctx.lineTo(Math.cos(angle)*rad, Math.sin(angle)*rad);
      }
      ctx.closePath();
      ctx.fill();
      /* subtle glow */
      ctx.shadowColor = 'rgba(200,230,255,.6)';
      ctx.shadowBlur  = p.r * 2;
      ctx.fill();
      ctx.shadowBlur  = 0;
      ctx.restore();
    }

    const DRAWERS = { dot:drawDot, cross:drawCross, star:drawStar };

    function init(){
      resize();
      flakes = Array.from({length:120}, ()=>{
        const f = mkFlake();
        f.y = rand(-H, H); // pre-scatter vertically
        return f;
      });
    }

    function loop(){
      ctx.clearRect(0,0,W,H);
      flakes.forEach((p,i)=>{
        const prog = p.age/p.life;
        const a    = p.alpha * (1 - prog**2);

        DRAWERS[p.style](p, a);

        // move
        p.swayT += .018;
        p.x     += p.vx + Math.sin(p.swayT)*p.swayAmp;
        p.y     += p.vy;
        p.rot   += p.rotV;
        p.age++;

        if(p.age >= p.life || p.y > H+20) flakes[i] = mkFlake();
      });
      requestAnimationFrame(loop);
    }

    window.addEventListener('resize', resize);
    init();
    loop();
  })();
}
</script>
</body>
</html>