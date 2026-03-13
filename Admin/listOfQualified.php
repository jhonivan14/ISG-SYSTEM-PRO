<?php
require_once __DIR__ . "/includes/admin-auth.php";
adminRequireLogin();
?>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>List of Qualified Applicants</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Times+New+Roman&display=swap" rel="stylesheet" />
    <style>
      @page {
        size: A4;
        margin: 20mm 18mm 20mm 18mm;
      }
      body { font-family: "Times New Roman", serif; }
      header { margin-bottom: 0.75rem; text-align: center; }
      .header-top { display: flex; justify-content: center; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 0.25rem; }
      .header-left { display: flex; align-items: center; gap: 0.5rem; }
      .header-left img { width: 80px; height: 80px; object-fit: contain; }
      .header-left-text { line-height: 1.1; text-align: left; }
      .header-left-text h1 { font-weight: 700; font-size: 16pt; margin: 0; }
      .header-left-text p { margin: 0; font-size: 10pt; }
      .header-right { display: flex; flex-direction: column; gap: 0.2rem; align-items: center; }
      .header-right img { width: 100px; height: 80px; object-fit: contain; }

      .title-line { font-weight: 700; letter-spacing: 0.02em; }
      .subtle { font-size: 10pt; }

      table { width: 100%; border-collapse: collapse; }
      th, td { border: 1px solid #000; font-size: 11pt; padding: 6px 8px; }
      thead th { background: #f1c40f; /* yellow-like */ font-weight: 700; text-align: center; }
      tbody td { vertical-align: top; }

      .signature-block label { font-weight: 600; }
      .sig-line { display: inline-block; min-width: 260px; border-bottom: 1px solid #000; height: 18px; }
      .sig-role { font-size: 10pt; margin-top: 2px; }
    </style>
  </head>
  <body class="bg-white text-black p-6">
    <div class="max-w-5xl mx-auto">
      <!-- Header copied to match applicationProcess.html style -->
      <header>
        <div class="header-top">
          <div class="header-left">
            <img src="../img/SMCCNEWLOGO.png" alt="Seal of Saint Michael College of Caraga" />
            <div class="header-left-text">
              <h1 class="text-center">Saint Michael College of Caraga</h1>
              <p class="text-center">Brgy. 4, Nasipit, Agusan del Norte, Philippines</p>
              <p class="text-center">Tel. Nos. +63 085 343-3251 / +63 085 283-3113</p>
              <p class="text-center"><a href="http://www.smccnasipit.edu.ph" style="color: blue; text-decoration: underline;">www.smccnasipit.edu.ph</a></p>
            </div>
          </div>
          <div class="header-right">
            <img src="../img/SOCO-PAB-1024x672.jpg" alt="SOCOTEC ISO 9001 logo" />
          </div>
        </div>
      </header>

      <div class="text-center mb-1">
        <div class="title-line">OFFICE OF THE ADMISSION &amp; SCHOLARSHIP</div>
      </div>
      <hr class="border-black mb-2" />

      <section class="text-center mb-4">
        <h2 class="font-bold text-base">List of Qualified Applicants for Student Assistance Scholarship Program</h2>
        <p class="font-semibold text-sm">1st Semester, S.Y. 2025-2026</p>
        <p class="font-semibold text-sm">Batch 1</p>
      </section>

      <section>
        <table>
          <thead>
            <tr>
              <th style="width: 32px;">#</th>
              <th>NAME</th>
              <th>ADDRESS</th>
              <th>CONTACT NUMBER</th>
              <th>PROGRAM ENROLLED</th>
              <th>YEAR LEVEL</th>
              <th>ASSIGNED OFFICE</th>
            </tr>
          </thead>
          <tbody>
            <tr><td>1</td><td>Maria L. Santos</td><td>Brgy. 1, Nasipit</td><td>0917 201 1123</td><td>BSIT</td><td>2</td><td>ICT Office</td></tr>
            <tr><td>2</td><td>John P. Dela Cruz</td><td>Brgy. 7, Nasipit</td><td>0908 334 5566</td><td>BSBA</td><td>3</td><td>Registrar</td></tr>
            <tr><td>3</td><td>Angela R. Gomez</td><td>Brgy. 4, Butuan City</td><td>0920 889 7733</td><td>BSHM</td><td>1</td><td>Hospitality Lab</td></tr>
            <tr><td>4</td><td>Kevin J. Ramos</td><td>Brgy. 3, Nasipit</td><td>0945 123 0044</td><td>BSED English</td><td>2</td><td>Library</td></tr>
            <tr><td>5</td><td>Ella Mae P. Rivera</td><td>Magallanes, Agusan del Norte</td><td>0910 555 8877</td><td>BEED</td><td>4</td><td>SAS Office</td></tr>
            <tr><td>6</td><td>Mark Adrian T. Uy</td><td>Brgy. 5, Nasipit</td><td>0936 778 9922</td><td>BSCRIM</td><td>3</td><td>Security</td></tr>
            <tr><td>7</td><td>Rose Ann G. Casing</td><td>Carmen, Agusan del Norte</td><td>0918 667 4411</td><td>BSN</td><td>1</td><td>Clinic</td></tr>
            <tr><td>8</td><td>Jasper K. Lim</td><td>Brgy. Triangulo, Nasipit</td><td>0921 903 2205</td><td>BSIT</td><td>3</td><td>ICT Office</td></tr>
            <tr><td>9</td><td>Patricia D. Villanueva</td><td>Buenavista, Agusan del Norte</td><td>0956 441 0033</td><td>BSAcc</td><td>2</td><td>Finance</td></tr>
            <tr><td>10</td><td>Vincent R. Alonzo</td><td>Brgy. 2, Nasipit</td><td>0995 812 6677</td><td>BSCE</td><td>2</td><td>Engineering</td></tr>
            <tr><td>11</td><td>Hazel Joy B. Ramos</td><td>Las Nieves, Agusan del Norte</td><td>0917 330 9911</td><td>BSHM</td><td>4</td><td>Guidance</td></tr>
            <tr><td>12</td><td>Ryan G. Mondejar</td><td>Butuan City</td><td>0906 421 5570</td><td>BSBA</td><td>1</td><td>HRMDO</td></tr>
            <tr><td>13</td><td>Shaira M. Quiazon</td><td>Brgy. 8, Nasipit</td><td>0927 665 3101</td><td>BSAIS</td><td>2</td><td>Accounting</td></tr>
            <tr><td>14</td><td>Louie C. Bayla</td><td>Jabonga, Agusan del Norte</td><td>0915 220 1188</td><td>BSIT</td><td>4</td><td>Research</td></tr>
            <tr><td>15</td><td>Kathleen S. Domingo</td><td>Kitcharao, Agusan del Norte</td><td>0991 702 4410</td><td>BSED Math</td><td>3</td><td>Basic Ed</td></tr>
            <tr><td>16</td><td>Alfredo T. Manlangit</td><td>RTR, Agusan del Norte</td><td>0938 770 5521</td><td>BSCS</td><td>1</td><td>NSTP</td></tr>
          </tbody>
        </table>
      </section>

      <div class="mt-8 grid grid-cols-1 gap-6 max-w-3xl">
        <div>
          <div class="subtle mb-1">Prepared by:</div>
          <div class="font-semibold">ARLYN B. TUYOGON, MMBM</div>
          <div class="sig-role">Head, Admission &amp; Scholarship</div>
        </div>

        <div>
          <div class="subtle mb-1">Noted by:</div>
          <div class="font-semibold">FELMARIE MANLUNAS, MACDDS</div>
          <div class="sig-role">Head, Student Affairs &amp; Services</div>
        </div>

        <div>
          <div class="subtle mb-1">Recommending Approval:</div>
          <div class="font-semibold">RICKY E. DESTACAMENTO, RGC, MAED</div>
          <div class="sig-role">Head, HRMDO</div>
        </div>

        <div>
          <div class="subtle mb-1">Approved by:</div>
          <div class="font-semibold">REV. FR. RONNIEL G. BABANO, STL</div>
          <div class="sig-role">School President</div>
        </div>
      </div>
    </div>
  </body>
</html>
