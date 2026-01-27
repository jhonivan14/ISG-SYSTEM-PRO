<?php
require_once __DIR__ . '/../db.php';

$save_success = null;
$save_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $applicant_name = trim($_POST['applicant_name'] ?? '');
    $interview_date = $_POST['interview_date'] ?? '';
    $interviewer_name = trim($_POST['interviewer_name'] ?? '');
    $total_points = $_POST['total_points'] ?? null;
    $overall_assessment = $_POST['overall_assessment'] ?? null;
    $strengths = trim($_POST['strengths'] ?? '');
    $areas_for_improvement = trim($_POST['areas_for_improvement'] ?? '');
    $signature_data = $_POST['signature_data'] ?? null;

    $total_points = ($total_points === '' || $total_points === null) ? null : (string) ((int) $total_points);
    $overall_assessment = ($overall_assessment === '' || $overall_assessment === null) ? null : $overall_assessment;
    $strengths = $strengths === '' ? null : $strengths;
    $areas_for_improvement = $areas_for_improvement === '' ? null : $areas_for_improvement;
    $signature_data = $signature_data === '' ? null : $signature_data;

    if ($applicant_name === '' || $interview_date === '' || $interviewer_name === '') {
        $save_error = 'Please fill out the applicant name, interview date, and interviewer name.';
    } else {
        try {
            $conn->begin_transaction();

            $stmt = $conn->prepare(
                'INSERT INTO interview_evaluations (applicant_id, applicant_name, interview_date, interviewer_name, total_points, overall_assessment, strengths, areas_for_improvement, signature_data)
                 VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'ssssssss',
                $applicant_name,
                $interview_date,
                $interviewer_name,
                $total_points,
                $overall_assessment,
                $strengths,
                $areas_for_improvement,
                $signature_data
            );
            $stmt->execute();
            $evaluation_id = $stmt->insert_id;
            $stmt->close();

            $ratings = $_POST['rating'] ?? [];
            $comments = $_POST['comment'] ?? [];
            $item_stmt = $conn->prepare(
                'INSERT INTO interview_evaluation_items (evaluation_id, criterion_id, rating, comment)
                 VALUES (?, ?, ?, ?)'
            );

            foreach (range(1, 12) as $criterion_id) {
                if (!isset($ratings[$criterion_id])) {
                    continue;
                }
                $rating_value = (int) $ratings[$criterion_id];
                if ($rating_value < 1 || $rating_value > 5) {
                    continue;
                }
                $comment_value = trim($comments[$criterion_id] ?? '');
                $comment_value = $comment_value === '' ? null : $comment_value;

                $item_stmt->bind_param('iiis', $evaluation_id, $criterion_id, $rating_value, $comment_value);
                $item_stmt->execute();
            }
            $item_stmt->close();

            $conn->commit();
            $save_success = 'Evaluation saved successfully.';
        } catch (Throwable $e) {
            $conn->rollback();
            $save_error = 'Failed to save evaluation. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Panelist Evaluation Sheet</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-100 via-blue-50 to-slate-200 py-10">
    <div class="max-w-5xl mx-auto px-4">
        <div class="bg-white shadow-2xl shadow-blue-100/60 rounded-2xl overflow-hidden border border-slate-200">
            <div class="bg-gradient-to-r from-blue-900 via-blue-800 to-blue-600 px-8 py-10 text-white">
                <p class="text-sm uppercase tracking-[0.35em] text-blue-100">Student Assistance Scholarship Program</p>
                <h1 class="mt-3 text-3xl font-bold tracking-tight">Interview Evaluation Sheet</h1>
                <p class="mt-4 max-w-3xl text-blue-100 text-sm leading-relaxed">
                    Evaluate applicants using the performance scale below. Document constructive feedback to support the final decision.
                </p>
            </div>

            <form method="post" class="px-8 py-10 space-y-10">
                <?php if ($save_success): ?>
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                        <?php echo htmlspecialchars($save_success); ?>
                    </div>
                <?php endif; ?>
                <?php if ($save_error): ?>
                    <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        <?php echo htmlspecialchars($save_error); ?>
                    </div>
                <?php endif; ?>
                <section class="grid gap-6 md:grid-cols-3">
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-slate-700" for="applicant-name">Applicant&apos;s Name</label>
                        <input id="applicant-name" name="applicant_name" type="text" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-700 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring focus:ring-blue-500/20" placeholder="__________________________" />
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-slate-700" for="interview-date">Date of Interview</label>
                        <input id="interview-date" name="interview_date" type="date" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-700 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring focus:ring-blue-500/20" />
                    </div>
                    <div class="space-y-2 md:col-span-1">
                        <label class="text-sm font-semibold text-slate-700" for="interviewer-name">Interviewer&apos;s Name</label>
                        <input id="interviewer-name" name="interviewer_name" type="text" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-700 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring focus:ring-blue-500/20" placeholder="__________________________" />
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-slate-50/70 p-6">
                    <p class="text-sm font-semibold uppercase text-slate-600">Direction</p>
                    <p class="mt-2 text-sm text-slate-600">
                        Please evaluate the applicant based on the criteria below. Use the rating scale provided.
                    </p>
                    <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left uppercase tracking-wide text-slate-600">
                                <tr>
                                    <th class="px-4 py-3 font-semibold">Rating</th>
                                    <th class="px-4 py-3 font-semibold">Description</th>
                                    <th class="px-4 py-3 font-semibold">Interpretation</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-700">5</td>
                                    <td class="px-4 py-3 text-slate-700">Excellent</td>
                                    <td class="px-4 py-3 text-slate-600">Outstanding performance; far exceeds expectations.</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-700">4</td>
                                    <td class="px-4 py-3 text-slate-700">Very Good</td>
                                    <td class="px-4 py-3 text-slate-600">Above average; exceeds expectations.</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-700">3</td>
                                    <td class="px-4 py-3 text-slate-700">Good</td>
                                    <td class="px-4 py-3 text-slate-600">Meets expectations satisfactorily.</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-700">2</td>
                                    <td class="px-4 py-3 text-slate-700">Fair</td>
                                    <td class="px-4 py-3 text-slate-600">Below expectations; needs improvement.</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-700">1</td>
                                    <td class="px-4 py-3 text-slate-700">Poor</td>
                                    <td class="px-4 py-3 text-slate-600">Far below expectations.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="space-y-6">
                    <div class="overflow-x-auto rounded-2xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left uppercase tracking-wide text-slate-600">
                                <tr>
                                    <th class="px-6 py-4 font-semibold">Criteria</th>
                                    <th class="px-4 py-4 font-semibold text-center">5</th>
                                    <th class="px-4 py-4 font-semibold text-center">4</th>
                                    <th class="px-4 py-4 font-semibold text-center">3</th>
                                    <th class="px-4 py-4 font-semibold text-center">2</th>
                                    <th class="px-4 py-4 font-semibold text-center">1</th>
                                    <th class="px-6 py-4 font-semibold">Interviewer&apos;s Comment(s)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                <tr class="bg-slate-50/70">
                                    <td class="px-6 py-3 font-semibold text-slate-700">A. Academic Qualifications</td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-6 py-3"></td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 text-slate-700">1. Academic Performance</td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[1]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[1]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[1]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[1]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[1]" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4"><input type="text" name="comment[1]" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700" /></td>
                                </tr>
                                <tr class="bg-slate-50/70">
                                    <td class="px-6 py-3 font-semibold text-slate-700">B. Skills and Competencies</td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-6 py-3"></td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 text-slate-700">2. Related Work Experience</td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[2]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[2]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[2]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[2]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[2]" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4"><input type="text" name="comment[2]" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700" /></td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 text-slate-700">3. Computer Skills</td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[3]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[3]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[3]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[3]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[3]" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4"><input type="text" name="comment[3]" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700" /></td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 text-slate-700">4. Communication Skills</td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[4]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[4]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[4]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[4]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[4]" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4"><input type="text" name="comment[4]" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700" /></td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 text-slate-700">5. Multitasking Abilities</td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[5]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[5]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[5]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[5]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[5]" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4"><input type="text" name="comment[5]" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700" /></td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 text-slate-700">6. Time Management</td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[6]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[6]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[6]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[6]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[6]" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4"><input type="text" name="comment[6]" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700" /></td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 text-slate-700">7. Interpersonal Skills</td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[7]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[7]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[7]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[7]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[7]" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4"><input type="text" name="comment[7]" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700" /></td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 text-slate-700">8. Stress Tolerance</td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[8]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[8]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[8]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[8]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[8]" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4"><input type="text" name="comment[8]" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700" /></td>
                                </tr>
                                <tr class="bg-slate-50/70">
                                    <td class="px-6 py-3 font-semibold text-slate-700">C. Work Behavior and Personal Qualities</td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-6 py-3"></td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 text-slate-700">9. Work Ethics and Reliability</td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[9]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[9]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[9]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[9]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[9]" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4"><input type="text" name="comment[9]" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700" /></td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 text-slate-700">10. Initiative</td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[10]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[10]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[10]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[10]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[10]" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4"><input type="text" name="comment[10]" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700" /></td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 text-slate-700">11. Integrity and Cooperation</td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[11]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[11]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[11]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[11]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[11]" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4"><input type="text" name="comment[11]" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700" /></td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 text-slate-700">12. Attitude Towards the Position</td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[12]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[12]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[12]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[12]" class="accent-blue-600" /></td>
                                    <td class="px-4 py-4 text-center"><input type="radio" name="rating[12]" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4"><input type="text" name="comment[12]" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700" /></td>
                                </tr>
                                <tr class="bg-slate-50/70">
                                    <td class="px-6 py-3 font-semibold text-slate-700">Total Points Earned</td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-6 py-3">
                                        <input type="number" name="total_points" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-blue-900" placeholder="0" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="space-y-6">
                    <div>
                        <p class="text-sm font-semibold text-slate-700">Overall Assessment</p>
                        <p class="mt-3 text-sm font-semibold text-slate-700">General Impression of the Applicant:</p>
                        <div class="mt-3 grid gap-2 text-sm text-slate-700 md:grid-cols-2">
                            <label class="flex items-center gap-2">
                                <input type="radio" name="overall_assessment" value="highly_recommended" class="accent-blue-600" />
                                Highly Recommended
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" name="overall_assessment" value="recommended" class="accent-blue-600" />
                                Recommended
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" name="overall_assessment" value="recommended_with_reservations" class="accent-blue-600" />
                                Recommended with Reservations
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" name="overall_assessment" value="not_recommended" class="accent-blue-600" />
                                Not Recommended
                            </label>
                        </div>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-slate-700" for="strengths">Strengths</label>
                            <input id="strengths" name="strengths" type="text" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-700 focus:border-blue-500 focus:outline-none focus:ring focus:ring-blue-500/20" />
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-slate-700" for="improvements">Areas for Improvement</label>
                            <input id="improvements" name="areas_for_improvement" type="text" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-700 focus:border-blue-500 focus:outline-none focus:ring focus:ring-blue-500/20" />
                        </div>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div></div>
                        <div class="space-y-2 md:justify-self-end">
                            <label class="text-sm font-semibold text-slate-700" for="signature-pad">Interviewer&apos;s Signature</label>
                            <div class="rounded-xl border border-slate-200 bg-white p-3">
                                <canvas id="signature-pad" class="h-36 w-full rounded-lg border border-dashed border-slate-300"></canvas>
                                <input type="hidden" id="signature-data" name="signature_data" />
                                <div class="mt-3 flex items-center justify-between text-xs text-slate-500">
                                    <span>Draw signature above</span>
                                    <button type="button" id="signature-clear" class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600 transition hover:border-blue-400 hover:text-blue-600">
                                        Clear
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
                <div class="flex flex-col gap-3 border-t border-slate-200 pt-8 md:flex-row md:items-center md:justify-between">
                    <p class="text-xs text-slate-500">Finalize the evaluation by verifying that all sections are complete and ratings align with the applicant&apos;s demonstrated competencies.</p>
                    <button type="submit" class="rounded-full bg-gradient-to-r from-blue-700 to-blue-500 px-6 py-3 text-sm font-semibold uppercase tracking-wide text-white shadow-lg shadow-blue-500/30 transition hover:from-blue-800 hover:to-blue-600 focus:outline-none focus:ring focus:ring-blue-400/40">
                        Submit Evaluation
                    </button>
                </div>
            </form>
        </div>
    </div>
    <script>
        (function () {
            const canvas = document.getElementById('signature-pad');
            const clearBtn = document.getElementById('signature-clear');
            const signatureInput = document.getElementById('signature-data');
            if (!canvas || !clearBtn || !signatureInput) return;

            const ctx = canvas.getContext('2d');
            const scaleCanvas = () => {
                const ratio = window.devicePixelRatio || 1;
                const rect = canvas.getBoundingClientRect();
                canvas.width = rect.width * ratio;
                canvas.height = rect.height * ratio;
                ctx.setTransform(1, 0, 0, 1, 0, 0);
                ctx.scale(ratio, ratio);
                ctx.lineWidth = 2;
                ctx.lineCap = 'round';
                ctx.strokeStyle = '#0f172a';
            };

            let drawing = false;

            const getPoint = (event) => {
                const rect = canvas.getBoundingClientRect();
                const clientX = event.touches ? event.touches[0].clientX : event.clientX;
                const clientY = event.touches ? event.touches[0].clientY : event.clientY;
                return { x: clientX - rect.left, y: clientY - rect.top };
            };

            const startDraw = (event) => {
                drawing = true;
                const { x, y } = getPoint(event);
                ctx.beginPath();
                ctx.moveTo(x, y);
                event.preventDefault();
            };

            const draw = (event) => {
                if (!drawing) return;
                const { x, y } = getPoint(event);
                ctx.lineTo(x, y);
                ctx.stroke();
                event.preventDefault();
            };

            const endDraw = () => {
                if (!drawing) return;
                drawing = false;
                signatureInput.value = canvas.toDataURL('image/png');
            };

            scaleCanvas();
            window.addEventListener('resize', scaleCanvas);

            canvas.addEventListener('mousedown', startDraw);
            canvas.addEventListener('mousemove', draw);
            canvas.addEventListener('mouseup', endDraw);
            canvas.addEventListener('mouseleave', endDraw);

            canvas.addEventListener('touchstart', startDraw, { passive: false });
            canvas.addEventListener('touchmove', draw, { passive: false });
            canvas.addEventListener('touchend', endDraw);

            clearBtn.addEventListener('click', () => {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                signatureInput.value = '';
            });
        })();
    </script>
</body>
</html>


