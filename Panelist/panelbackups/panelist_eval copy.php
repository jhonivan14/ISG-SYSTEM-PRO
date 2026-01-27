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

            <div class="px-8 py-10 space-y-10">
                <section class="grid gap-6 md:grid-cols-3">
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-slate-700" for="applicant-name">Applicant&apos;s Name</label>
                        <input id="applicant-name" type="text" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-700 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring focus:ring-blue-500/20" placeholder="Enter applicant name" />
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-slate-700" for="interview-date">Date of Interview</label>
                        <input id="interview-date" type="date" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-700 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring focus:ring-blue-500/20" />
                    </div>
                    <div class="space-y-2 md:col-span-1">
                        <label class="text-sm font-semibold text-slate-700" for="interviewer-name">Interviewer&apos;s Name</label>
                        <input id="interviewer-name" type="text" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-700 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring focus:ring-blue-500/20" placeholder="Enter interviewer name" />
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-slate-50/70 p-6">
                    <p class="text-sm font-semibold uppercase text-slate-600">Directions</p>
                    <p class="mt-2 text-sm text-slate-600">
                        Circle or highlight the rating that best reflects the applicant&apos;s performance. Use the guiding statements below to ensure consistent evaluation across all criteria.
                    </p>
                    <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-xl border border-blue-100 bg-white p-4 shadow-sm">
                            <p class="text-lg font-bold text-blue-900">4 Excellent</p>
                            <p class="mt-1 text-sm text-slate-600">Performance consistently exceeds expectations.</p>
                        </div>
                        <div class="rounded-xl border border-emerald-100 bg-white p-4 shadow-sm">
                            <p class="text-lg font-bold text-emerald-700">3 Good</p>
                            <p class="mt-1 text-sm text-slate-600">Strong performance that meets all expectations.</p>
                        </div>
                        <div class="rounded-xl border border-amber-100 bg-white p-4 shadow-sm">
                            <p class="text-lg font-bold text-amber-700">2 Fair</p>
                            <p class="mt-1 text-sm text-slate-600">Adequate performance that partially meets expectations.</p>
                        </div>
                        <div class="rounded-xl border border-rose-100 bg-white p-4 shadow-sm">
                            <p class="text-lg font-bold text-rose-700">1 Needs Improvement</p>
                            <p class="mt-1 text-sm text-slate-600">Performance does not meet expectations.</p>
                        </div>
                    </div>
                </section>

                <section class="space-y-6">
                    <div class="overflow-x-auto rounded-2xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left uppercase tracking-wide text-slate-600">
                                <tr>
                                    <th class="px-6 py-4 font-semibold">Criteria</th>
                                    <th class="px-6 py-4 font-semibold text-center">4</th>
                                    <th class="px-6 py-4 font-semibold text-center">3</th>
                                    <th class="px-6 py-4 font-semibold text-center">2</th>
                                    <th class="px-6 py-4 font-semibold text-center">1</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                <tr>
                                    <td class="px-6 py-4 font-medium text-slate-700">Academic performance</td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="academic" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="academic" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="academic" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="academic" class="accent-blue-600" /></td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 font-medium text-slate-700">Related work experience</td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="work" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="work" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="work" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="work" class="accent-blue-600" /></td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 font-medium text-slate-700">Computer skills</td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="computer" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="computer" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="computer" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="computer" class="accent-blue-600" /></td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 font-medium text-slate-700">Communication skills</td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="communication" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="communication" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="communication" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="communication" class="accent-blue-600" /></td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 font-medium text-slate-700">Work ethics and reliability</td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="ethics" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="ethics" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="ethics" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="ethics" class="accent-blue-600" /></td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 font-medium text-slate-700">Takes initiative</td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="initiative" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="initiative" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="initiative" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="initiative" class="accent-blue-600" /></td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 font-medium text-slate-700">Integrity and cooperation</td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="integrity" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="integrity" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="integrity" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="integrity" class="accent-blue-600" /></td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 font-medium text-slate-700">Stress tolerance</td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="stress" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="stress" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="stress" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="stress" class="accent-blue-600" /></td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 font-medium text-slate-700">Multitasking abilities</td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="multitasking" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="multitasking" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="multitasking" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="multitasking" class="accent-blue-600" /></td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 font-medium text-slate-700">Interpersonal skills</td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="interpersonal" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="interpersonal" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="interpersonal" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="interpersonal" class="accent-blue-600" /></td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 font-medium text-slate-700">Time management</td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="time" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="time" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="time" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="time" class="accent-blue-600" /></td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 font-medium text-slate-700">Attitude towards this position</td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="attitude" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="attitude" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="attitude" class="accent-blue-600" /></td>
                                    <td class="px-6 py-4 text-center"><input type="radio" name="attitude" class="accent-blue-600" /></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4">
                        <p class="text-xs font-semibold uppercase text-slate-600">Total Points Earned</p>
                        <input type="number" class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-lg font-semibold text-blue-900 focus:border-blue-500 focus:outline-none focus:ring focus:ring-blue-500/20" placeholder="0" />
                    </div>
                </section>

                <section class="grid gap-6 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="text-sm font-semibold text-slate-700" for="comments">Interviewer&apos;s Comment(s)</label>
                        <textarea id="comments" rows="4" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-700 focus:border-blue-500 focus:outline-none focus:ring focus:ring-blue-500/20" placeholder="Provide constructive feedback supporting the evaluation."></textarea>
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-slate-700" for="signature">Interviewer&apos;s Signature</label>
                        <input id="signature" type="text" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-700 focus:border-blue-500 focus:outline-none focus:ring focus:ring-blue-500/20" placeholder="Type or sign your name" />
                    </div>
                </section>

                <div class="flex flex-col gap-3 border-t border-slate-200 pt-8 md:flex-row md:items-center md:justify-between">
                    <p class="text-xs text-slate-500">Finalize the evaluation by verifying that all sections are complete and ratings align with the applicant&apos;s demonstrated competencies.</p>
                    <button class="rounded-full bg-gradient-to-r from-blue-700 to-blue-500 px-6 py-3 text-sm font-semibold uppercase tracking-wide text-white shadow-lg shadow-blue-500/30 transition hover:from-blue-800 hover:to-blue-600 focus:outline-none focus:ring focus:ring-blue-400/40">
                        Submit Evaluation
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
