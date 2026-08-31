<?php
// Guide: Static printable department evaluation form layout.
// Trace: auth check -> render evaluation form template.

require_once __DIR__ . "/includes/admin-auth.php";
adminRequireLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Student Assistants' Evaluation Form</title>
    <link rel="stylesheet" href="../assets/css/tailwind.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Times+New+Roman&display=swap" rel="stylesheet" />
    <style>
        @page {
            size: A4;
            margin: 30mm 25mm 30mm 25mm;
        }

        body {
            font-family: "Times New Roman", serif;
            background: #ffffff;
            color: #111827;
        }

        header {
           
            margin-bottom: 1rem;
            text-align: center;
        }

        .header-top {
            margin-top: 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-bottom: 0.5rem;
        }

        .header-logo,
        .header-cert {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .header-logo img {
            width: 80px;
            height: 80px;
            object-fit: contain;
        }

        .header-center {
            line-height: 1.2;
            text-align: center;
        }

        .header-center h1 {
            font-weight: 700;
            font-size: 16pt;
            margin: 0;
        }

        .header-center p {
            margin: 0;
            font-size: 10pt;
        }

        .header-cert img {
            width: 100px;
            height: 80px;
            object-fit: contain;
        }

        .paper {
          
            padding: 1rem 1.4rem 2.5rem;
        }

        .title {
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            text-align: center;
            margin-bottom: 1rem;
        }

        .info-block {
            margin-bottom: 1.1rem;
            font-size: 12px;
        }

        .info-row {
            display: grid;
            grid-template-columns: 235px minmax(0, 320px);
            justify-content: start;
            align-items: end;
            gap: 0.45rem;
            margin-bottom: 0.3rem;
        }

        .info-label {
            font-weight: 600;
        }

        .info-value {
            border-bottom: 1px solid #111827;
            min-height: 1rem;
            padding-left: 0.35rem;
        }

        .direction {
            font-size: 12px;
            line-height: 1.5;
            margin-bottom: 1.1rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #111827;
            padding: 0.38rem 0.55rem;
            font-size: 12px;
            vertical-align: middle;
        }

        th {
            background: #f7f7f7;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .scale-table {
            border: none;
            margin-bottom: 1.2rem;
        }

        .scale-table th,
        .scale-table td {
            border: none;
        }

        .scale-table th {
            background: transparent;
            border-bottom: 1px solid #111827;
            padding-bottom: 0.5rem;
        }

        .scale-table td {
            padding: 0.5rem 0.55rem;
            font-size: 11px;
        }

        .scale-table td:first-child {
            width: 9%;
            text-align: center;
            font-weight: 700;
        }

        .rating-table th {
            font-size: 11px;
            text-align: center;
        }

        .rating-table td {
            height: 2.05rem;
            text-align: center;
        }

        .rating-table td:first-child {
            text-align: left;
        }

        .section-label {
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: #f3f4f6;
        }

        .subtotal {
            font-weight: 600;
        }

        .comment-box {
            border: 1px solid #111827;
            min-height: 3.2rem;
            margin-top: 0.6rem;
            padding: 0.45rem 0.6rem;
            font-size: 12px;
            line-height: 1.45;
        }

        .signature-row {
            display: flex;
            justify-content: flex-end;
            margin-top: 2.4rem;
        }

        .signature-block {
            width: 220px;
            font-size: 12px;
            text-align: center;
        }

        .signature-line {
            border-top: 1px solid #111827;
            margin-top: 0.9rem;
            padding-top: 0.25rem;
            text-align: center;
        }

        footer {
            margin-top: 1.5rem;
        }

        footer img {
            width: 100%;
            height: auto;
            object-fit: contain;
        }

        .footer-box {
            margin-top: 1.5rem;
            display: flex;
            justify-content: flex-start;
            padding-left: 0.25rem;
        }

        .footer-box img {
            width: 18rem;
            max-width: calc(100% - 0.5rem);
            height: auto;
            object-fit: contain;
        }

        @media print {
            body {
                background: #ffffff;
            }

            .paper {
                border: none;
                padding: 0;
            }

            header {
                margin-bottom: 0.8rem;
            }

            .footer-box {
                margin-top: 1rem;
            }

            footer {
                margin-top: 1rem;
            }
        }
    </style>
</head>
<body class="bg-white text-black">
    <div class="max-w-5xl mx-auto">
        <header>
            <div class="header-top">
                <div class="header-logo">
                    <img src="../img/SMCCNEWLOGO.png" alt="Seal of Saint Michael College of Caraga" />
                </div>
                <div class="header-center">
                    <h1>Saint Michael College of Caraga</h1>
                    <p>Brgy. 4, Nasipit, Agusan del Norte, Philippines<br />District 8, Brgy. Triangulo, Nasipit, Agusan del Norte, Philippines</p>
                    <p>Tel. Nos. +63 085 343-3251 / +63 085 283-3113</p>
                    <p><a href="http://www.smccnasipit.edu.ph" style="color: blue; text-decoration: underline;">www.smccnasipit.edu.ph</a></p>
                </div>
                <div class="header-cert">
                    <img src="../img/SOCO-PAB-1024x672.jpg" alt="SOCOTEC ISO 9001 logo" />
                </div>
            </div>
        </header>

        <main class="paper">
            <h2 class="title">Student Assistants' Evaluation Form</h2>

            <section class="info-block">
                <div class="info-row">
                    <span class="info-label">Name of Student Assistant:</span>
                    <span class="info-value">John Michael Santos</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Semester &amp; School Year:</span>
                    <span class="info-value">2nd Semester, S.Y. 2024-2025</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Area of Assignment:</span>
                    <span class="info-value">Learning Resource Center</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Head of Office:</span>
                    <span class="info-value">Mary Ann L. Rosales</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date of Evaluation:</span>
                    <span class="info-value">March 15, 2025</span>
                </div>
            </section>

            <section class="direction">
                <p>
                    <span class="font-semibold">Evaluator:</span>
                    <span class="inline-flex items-center gap-1 ml-3"><span class="inline-block h-4 w-4 border border-black align-middle"></span> Self</span>
                    <span class="inline-flex items-center gap-1 ml-3"><span class="inline-flex h-4 w-4 items-center justify-center border border-black text-[10px] leading-none">&#10003;</span> Head of Office</span>
                    <span class="inline-flex items-center gap-1 ml-3"><span class="inline-block h-4 w-4 border border-black align-middle"></span> Administrator</span>
                </p>
            </section>

            <section class="direction">
                <p><span class="font-semibold">Direction:</span> Please rate each item below to determine the performance of the assigned student assistant of your respective office/department. Put a check (&#10003;) to rate their performance.</p>
            </section>

            <section>
                <table class="scale-table">
                    <thead>
                        <tr>
                            <th style="width: 9%;">Scale</th>
                            <th style="width: 18%;">Verbal Description</th>
                            <th>Verbal Interpretation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>4</td>
                            <td>Very Good (VG)</td>
                            <td>Consistently exceeds the performance expectations stated in the indicator.</td>
                        </tr>
                        <tr>
                            <td>3</td>
                            <td>Good (G)</td>
                            <td>Consistently meets the performance expectations stated in the indicator.</td>
                        </tr>
                        <tr>
                            <td>2</td>
                            <td>Poor (P)</td>
                            <td>Frequently falls below the performance expectations stated in the indicator and requires improvement.</td>
                        </tr>
                        <tr>
                            <td>1</td>
                            <td>Needs Improvement (NI)</td>
                            <td>Consistently fails to meet the performance expectations stated in the indicator and requires close supervision and additional guidance.</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="pt-4">
                <table class="rating-table">
                    <thead>
                        <tr>
                            <th rowspan="2" style="width: 47%;">Performance Indicators</th>
                            <th colspan="4">Rating</th>
                        </tr>
                        <tr>
                            <th>Very Good (4)</th>
                            <th>Good (3)</th>
                            <th>Poor (2)</th>
                            <th>NI (1)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td class="section-label">A. Quality and Quantity of Work</td><td></td><td></td><td></td><td></td></tr>
                        <tr><td>A.1 Completes assigned tasks accurately and with minimal errors.</td><td></td><td></td><td></td><td></td></tr>
                        <tr><td>A.2 Completes assigned tasks thoroughly and according to instructions.</td><td></td><td></td><td></td><td></td></tr>
                        <tr><td>A.3 Completes work within the required time.</td><td></td><td></td><td></td><td></td></tr>
                        <tr><td>A.4 Demonstrates initiative by seeking additional responsibilities after completing assigned works.</td><td></td><td></td><td></td><td></td></tr>
                        <tr><td>A.5 Willingly accepts new assignments and responsibilities.</td><td></td><td></td><td></td><td></td></tr>
                        <tr><td class="subtotal">Total</td><td></td><td></td><td></td><td></td></tr>
                        <tr><td class="section-label">B. Interpersonal Skills</td><td></td><td></td><td></td><td></td></tr>
                        <tr><td>B.1 Communicates clearly and respectfully with students, employees, and visitors.</td><td></td><td></td><td></td><td></td></tr>
                        <tr><td>B.2 Demonstrates courtesy and professionalism when assisting students, employees, parents, and visitors.</td><td></td><td></td><td></td><td></td></tr>
                        <tr><td>B.3 Works cooperatively with office personnel and fellow student assistants.</td><td></td><td></td><td></td><td></td></tr>
                        <tr><td>B.4 Responds appropriately to questions, concerns, and requests.</td><td></td><td></td><td></td><td></td></tr>
                        <tr><td>B.5 Contributes positively to teamwork and collaborates effectively with colleagues.</td><td></td><td></td><td></td><td></td></tr>
                        <tr><td class="subtotal">Total</td><td></td><td></td><td></td><td></td></tr>
                        <tr><td class="section-label">C. Attendance and Reliability</td><td></td><td></td><td></td><td></td></tr>
                        <tr><td>C.1 Maintains regular attendance and provides timely notification for any authorized absence.</td><td></td><td></td><td></td><td></td></tr>
                        <tr><td>C.2 Reports for duty punctually and observes the assigned work schedule.</td><td></td><td></td><td></td><td></td></tr>
                        <tr><td>C.3 Participates actively in institutional activities, meetings, orientations, and trainings when required.</td><td></td><td></td><td></td><td></td></tr>
                        <tr><td>C.4 Works responsibly with minimal supervision.</td><td></td><td></td><td></td><td></td></tr>
                        <tr><td>C.5 Follows instructions and completes assigned responsibilities consistently.</td><td></td><td></td><td></td><td></td></tr>
                        <tr><td class="subtotal">Total</td><td></td><td></td><td></td><td></td></tr>
                        <tr><td class="section-label">D. Professionalism and Ethical Conduct</td><td></td><td></td><td></td><td></td></tr>
                        <tr><td>D.1 Demonstrates honesty and integrity in performing assigned duties.</td><td></td><td></td><td></td><td></td></tr>
                        <tr><td>D.2 Maintains confidentiality of office records and information.</td><td></td><td></td><td></td><td></td></tr>
                        <tr><td>D.3 Shows respect for institutional policies and procedures.</td><td></td><td></td><td></td><td></td></tr>
                        <tr><td>D.4 Maintains a positive attitude and professional demeanor while performing assigned duties.</td><td></td><td></td><td></td><td></td></tr>
                        <tr><td>D.5 Observes proper dress code and behaves appropriately while on duty.</td><td></td><td></td><td></td><td></td></tr>
                        <tr><td class="subtotal">Total</td><td></td><td></td><td></td><td></td></tr>
                        <tr><td class="subtotal">Over-all Total</td><td></td><td></td><td></td><td></td></tr>
                    </tbody>
                </table>
            </section>

            <section class="pt-4 text-[12px]">
                <p class="font-semibold">Performance Summary</p>
                <div class="comment-box">
                    Overall Total Score: __________/80<br />
                    Average Rating: __________<br />
                    Performance Level: Very Good ____ Good ____ Poor ____ Needs Improvement ____
                </div>
            </section>

            <section class="pt-6 text-[12px]">
                <p class="font-semibold">E. Strength(s):</p>
                <div class="comment-box"></div>
            </section>

            <section class="pt-4 text-[12px]">
                <p class="font-semibold">F. Area(s) for Improvement:</p>
                <div class="comment-box"></div>
            </section>

            <section class="pt-4 text-[12px]">
                <p class="font-semibold">G. Evaluator's Comment(s)/Recommendation:</p>
                <div class="comment-box"></div>
            </section>
            <div class="signature-row">
                <div class="signature-block">
                    <p>Evaluator's Signature</p>
                    <div class="signature-line"></div>
                </div>
            </div>

            <div class="footer-box">
                <img src="../img/box.png" alt="footer box" />
            </div>
        </main>

        <footer>
            <img src="../img/footer.png" alt="SMCC footer" />
        </footer>
    </div>
</body>
</html>
