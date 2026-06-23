<section class="grid gap-6 xl:grid-cols-[1.4fr_0.8fr]" data-motion="fade-up" data-motion-stagger>
    <div class="card p-5">
        <h2 class="text-lg font-black text-ink-900">Data subject requests</h2>
        <div class="mt-5 table-wrap">
            <table class="data-table">
                <thead><tr><th>Subject</th><th>Type</th><th>Status</th><th>Due</th><th>Update</th></tr></thead>
                <tbody class="divide-y divide-ink-100">
                    <?php foreach ($requests as $dsr): ?>
                        <tr>
                            <td><span class="font-bold"><?= e($dsr['subject_name']) ?></span><span class="block text-ink-500"><?= e($dsr['subject_email']) ?></span></td>
                            <td><?= e($dsr['request_type']) ?></td>
                            <td><span class="badge bg-ink-100 text-ink-700"><?= e($dsr['status']) ?></span></td>
                            <td><?= e($dsr['due_at']) ?></td>
                            <td class="min-w-[16rem]">
                                <form method="post" action="/privacy/<?= e($dsr['id']) ?>" class="flex flex-col gap-2">
                                    <?= csrf_field() ?>
                                    <label class="block" for="status-<?= e($dsr['id']) ?>">
                                        <span class="label">Status</span>
                                        <select id="status-<?= e($dsr['id']) ?>" class="field" name="status">
                                            <?php foreach (['received','verifying','processing','completed','rejected'] as $status): ?><option value="<?= e($status) ?>" <?= $dsr['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label class="block" for="verification-notes-<?= e($dsr['id']) ?>">
                                        <span class="label">Verification notes</span>
                                        <textarea id="verification-notes-<?= e($dsr['id']) ?>" class="textarea" name="verification_notes" rows="2"><?= e($dsr['verification_notes']) ?></textarea>
                                    </label>
                                    <label class="block" for="response-notes-<?= e($dsr['id']) ?>">
                                        <span class="label">Response notes</span>
                                        <textarea id="response-notes-<?= e($dsr['id']) ?>" class="textarea" name="response_notes" rows="2"><?= e($dsr['response_notes']) ?></textarea>
                                    </label>
                                    <button class="btn-secondary w-full sm:w-auto">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$requests): ?>
                        <tr><td colspan="5">
                            <?php empty_state([
                                'icon' => 'shield',
                                'title' => 'No privacy requests logged',
                                'description' => 'Data subject access, rectification, and erasure requests will be tracked here for GDPR compliance.',
                                'primaryActionLabel' => 'Log a request',
                                'primaryActionHref' => '#log-request-form',
                            ]) ?>
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="space-y-6">
        <form method="post" action="/privacy" id="log-request-form" class="card p-5">
            <?= csrf_field() ?>
            <h2 class="text-lg font-black text-ink-900">Log request</h2>
            <div class="mt-5 space-y-4">
                <label><span class="label">Request type</span><select class="field" name="request_type"><?php foreach (['access','rectification','erasure','restriction','portability','objection'] as $type): ?><option value="<?= e($type) ?>"><?= e($type) ?></option><?php endforeach; ?></select></label>
                <label><span class="label">Subject name</span><input class="field" name="subject_name" required></label>
                <label><span class="label">Subject email</span><input class="field" name="subject_email" type="email" required></label>
            </div>
            <button class="btn-primary mt-5 w-full"><?= icon('plus') ?> Log request</button>
        </form>

        <div class="card p-5">
            <h2 class="text-lg font-black text-ink-900">Privacy policy structure</h2>
            <p class="mt-3 text-sm leading-6 text-ink-600"><?= e($business['privacy_policy'] ?? 'Configure your privacy policy in Settings. Include purposes, legal bases, retention, data subject rights, and contact details.') ?></p>
        </div>
    </div>
</section>
