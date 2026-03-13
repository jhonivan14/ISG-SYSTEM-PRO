<?php
require_once __DIR__ . "/includes/admin-auth.php";
adminRequireLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Student Assistance Applicants� Interview Result</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: "Times New Roman", Times, serif;
            background: #f1f5f9;
            color: #1f2937;
        }

        .paper {
            width: 8.5in;
            min-height: 13in;
            margin: 1.25rem auto;
            background: #ffffff;
            padding: 0.75in;
            box-shadow: 0 18px 38px rgba(71, 85, 105, 0.18);
            border: 1px solid rgba(148, 163, 184, 0.4);
        }

        hr {
            border-top: 1.5px solid #111827;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th,
        td {
            border: 1px solid #1f2937;
            padding: 0.45rem 0.55rem;
        }

        th {
            background: #fef08a;
            font-size: 11px;
            text-transform: uppercase;
            text-align: left;
            letter-spacing: 0.05em;
            font-weight: 600;
        }

        td {
            font-size: 12px;
            height: 2.1rem;
        }

        .col-seq { width: 7%; }
        .col-name { width: 31%; }
        .col-mean { width: 18%; }
        .col-description { width: 18%; }
        .col-interpretation { width: 31%; }
        .col-remarks { width: 14%; }

        @media print {
            body {
                background: #ffffff;
            }

            .paper {
                margin: 0;
                width: 100%;
                min-height: auto;
                box-shadow: none;
                border: none;
                padding: 0.5in 0.6in;
            }
        }
    </style>
</head>
<body>
    <main class="paper">
        <header class="pb-3">
            <div class="flex flex-col items-center gap-4 text-center md:grid md:grid-cols-[160px,1fr,160px] md:items-center">
                <div class="flex justify-center md:justify-start">
                    <img src="../img/SMCCNEWLOGO.png" alt="Seal of Saint Michael College of Caraga" class="h-20 w-20 object-contain" />
                </div>
                <div>
                    <h1 class="text-[20px] font-bold leading-tight text-[#3a3a6e]">Saint Michael College of Caraga</h1>
                    <p class="text-[12px] leading-tight">Brgy. 4, Nasipit, Agusan del Norte, Philippines</p>
                    <p class="text-[12px] leading-tight">Tel. No. 085 225-0208</p>
                    <p class="text-[12px] leading-tight">Website: <a href="http://www.smccnasipit.edu.ph" class="text-[#3a3a6e] underline">www.smccnasipit.edu.ph</a>, Email: <a href="mailto:communications@smccnasipit.edu.ph" class="text-[#3a3a6e] underline">communications@smccnasipit.edu.ph</a></p>
                </div>
                <div class="flex justify-center md:justify-end">
                    <img src="../img/SOCO-PAB-1024x672.jpg" alt="SOCOTEC ISO 9001 logo" class="h-20 w-20 object-contain" />
                </div>
            </div>
        </header>

        <hr class="my-1" />

        <section class="py-2 text-center">
            <p class="text-[11px] font-semibold uppercase tracking-[0.25em] text-[#3a3a6e]">Office of the Admission &amp; Scholarship</p>
        </section>

        <section class="pb-4 text-center">
            <p class="text-[12px] font-semibold uppercase text-[#3a3a6e]">Student Assistance Applicants' Interview Result</p>
            <p class="text-[11px]">1st Semester, S.Y. 2025-2026</p>
            <p class="text-[11px]">Batch 3</p>
        </section>

        <section class="pb-6">
            <div class="border border-slate-700">
                <table>
                    <colgroup>
                        <col class="col-seq" />
                        <col class="col-name" />
                        <col class="col-mean" />
                        <col class="col-description" />
                        <col class="col-interpretation" />
                        <col class="col-remarks" />
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Seq.</th>
                            <th>Name of Applicant</th>
                            <th>Weighted Mean</th>
                            <th>Verbal Description</th>
                            <th>Verbal Interpretation</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1</td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td>2</td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td>3</td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td>4</td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td>5</td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td>6</td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td>7</td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td>8</td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="pt-2 text-[12px]">
            <div class="space-y-6">
                <div>
                    <p class="uppercase text-[11px] tracking-wide">Prepared by:</p>
                    <p class="mt-3 font-semibold">Arlyn B. Tuyogon</p>
                    <p>Head, Admission &amp; Scholarship</p>
                </div>
                <div>
                    <p class="uppercase text-[11px] tracking-wide">Checked by:</p>
                    <p class="mt-3 font-semibold">Felmarie Manlunas, MACDDS</p>
                    <p>Head, Student Affairs &amp; Services</p>
                </div>
                <div>
                    <p class="uppercase text-[11px] tracking-wide">Noted by:</p>
                    <p class="mt-3 font-semibold">Ricky E. Destacamento, RGC, MAED</p>
                    <p>Head, HRMDO</p>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
