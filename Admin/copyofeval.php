<?php
// Guide: Static print template for student assistant evaluation results.
// Trace: auth check -> render printable evaluation layout.

require_once __DIR__ . "/includes/admin-auth.php";
adminRequireLogin();
?>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Student Assistants' Evaluation Result</title>
    <link rel="stylesheet" href="../assets/css/tailwind.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Times+New+Roman&display=swap" />
    <style>
      @page {
        size: A4;
        margin: 30mm 25mm 30mm 25mm;
      }
      body { font-family: "Times New Roman", serif; }
      header { margin-bottom: 1rem; text-align: center; }
      .header-top {
        display: flex; justify-content: center; align-items: center;
        flex-wrap: wrap; gap: 1rem; margin-bottom: 0.5rem;
      }
      .header-left { display: flex; align-items: center; gap: 0.5rem; }
      .header-left img { width: 80px; height: 80px; object-fit: contain; }
      .header-left-text { line-height: 1.1; text-align: left; }
      .header-left-text h1 { font-weight: 700; font-size: 16pt; margin: 0; }
      .header-left-text p { margin: 0; font-size: 10pt; }
      .header-right { display: flex; flex-direction: column; gap: 0.2rem; align-items: center; }
      .header-right img { width: 100px; height: 80px; }
      .print-table { border-collapse: collapse; width: 100%; }
      .print-table th, .print-table td { border: 1px solid #000; padding: 6px 8px; font-size: 12px; }
      .no-border { border: none !important; }
    </style>
  </head>
  <body class="bg-white text-black p-6">
    <div class="max-w-5xl mx-auto">
      <!-- Header copied from applicationProcess.html -->
      <header>
        <div class="header-top">
          <div class="header-left">
            <img src="../img/SMCCNEWLOGO.png" alt="Seal of Saint Michael College of Caraga" />
            <div class="header-left-text">
              <h1 class="text-center">Saint Michael College of Caraga</h1>
              <p class="text-center">
                Brgy. 4, Nasipit, Agusan del Norte, Philippines<br />
                District 8, Brgy. Triangulo, Nasipit, Agusan del Norte, Philippines
              </p>
              <p class="text-center">Tel. Nos. +63 085 343-3251 / +63 085 283-3113</p>
              <p class="text-center">
                <a href="http://www.smccnasipit.edu.ph" style="color: blue; text-decoration: underline;">www.smccnasipit.edu.ph</a>
              </p>
            </div>
          </div>
          <div class="header-right">
            <img src="../img/SOCO-PAB-1024x672.jpg" alt="SOCOTEC ISO 9001 logo" />
          </div>
        </div>
      </header>

      <!-- Title -->
      <section class="text-center mb-6">
        <h2 class="font-bold text-[13pt] tracking-wide">STUDENT ASSISTANTS' EVALUATION RESULT</h2>
        <p class="text-[11pt] mt-1">1st Semester, S.Y. 2024-2025</p>
      </section>

      <!-- Info block -->
      <div class="max-w-4xl mx-auto text-[12px] font-serif space-y-1 mb-4">
        <div class="flex items-center max-w-3xl">
          <span class="w-56">Name of Student Assistant</span>
          <span>:</span>
          <span class="border-b border-black ml-2 h-5 inline-block w-80"></span>
        </div>
        <div class="flex items-center max-w-3xl">
          <span class="w-56">Program/Year Level</span>
          <span>:</span>
          <span class="border-b border-black ml-2 h-5 inline-block w-80"></span>
        </div>
        <div class="flex items-center max-w-3xl">
          <span class="w-56">Assigned Office</span>
          <span>:</span>
          <span class="border-b border-black ml-2 h-5 inline-block w-80"></span>
        </div>
        <div class="flex items-center max-w-3xl">
          <span class="w-56">Head of Office</span>
          <span>:</span>
          <span class="border-b border-black ml-2 h-5 inline-block w-80"></span>
        </div>
      </div>

      <!-- Evaluation Table -->
      <div class="max-w-3xl mx-auto">
        <table class="print-table">
          <thead>
            <tr>
              <th class="w-1/2">Criteria</th>
              <th class="w-24">Rating</th>
              <th>Verbal Description</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>A. Quality and Quantity of Work</td>
              <td></td>
              <td></td>
            </tr>
            <tr>
              <td>B. Interpersonal Skills</td>
              <td></td>
              <td></td>
            </tr>
            <tr>
              <td>C. Attendance and Reliability</td>
              <td></td>
              <td></td>
            </tr>
            <tr>
              <td>D. Professionalism and Ethical Conduct</td>
              <td></td>
              <td></td>
            </tr>
            <tr>
              <td class="font-semibold">Overall Rating</td>
              <td></td>
              <td></td>
            </tr>
            <tr>
              <td>E. Strength(s)</td>
              <td colspan="2"><div class="h-16"></div></td>
            </tr>
            <tr>
              <td>F. Area(s) for Improvement</td>
              <td colspan="2"><div class="h-16"></div></td>
            </tr>
            <tr>
              <td>G. Evaluator's Comment(s)/Recommendation</td>
              <td colspan="2"><div class="h-16"></div></td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Signatories -->
      <div class="max-w-4xl mx-auto grid grid-cols-1 sm:grid-cols-3 gap-8 mt-4 text-[12px] font-serif">
        <div>
          <p class="mb-8">Prepared by:</p>
          <p class="font-bold">ARLYN B. TUYOGON</p>
          <p>Head, Admission &amp; Scholarship</p>
        </div>
        <div>
          <p class="mb-8">Checked by:</p>
          <p class="font-bold">FELMARIE MANLUNAS, MACDDS</p>
          <p>Head, Student Affairs &amp; Services</p>
        </div>
        <div>
          <p class="mb-8">Noted by:</p>
          <p class="font-bold">RICKY E. DESTACAMENTO, RGC, MAED</p>
          <p>Head, HRMDO</p>
        </div>
      </div>

      <!-- CC and dates -->
      <div class="max-w-4xl mx-auto mt-10 text-[12px] font-serif space-y-2">
        <p>CC:</p>
        <div class="flex items-center gap-6">
          <div class="flex items-center gap-2">
            <span>Mr. Imam</span>
            <span class="border-b border-black w-48 h-5 inline-block"></span>
            <span class="ml-2">Date Received:</span>
            <span class="border-b border-black w-32 h-5 inline-block"></span>
          </div>
        </div>
        <div class="flex items-center gap-6">
          <div class="flex items-center gap-2">
            <span>Mr. Tabanao</span>
            <span class="border-b border-black w-48 h-5 inline-block"></span>
            <span class="ml-2">Date Received:</span>
            <span class="border-b border-black w-32 h-5 inline-block"></span>
          </div>
        </div>
      </div>

    </div>
  </body>
  </html>
