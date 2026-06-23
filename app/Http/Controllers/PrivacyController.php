<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Services\AuditLogger;
use App\Services\SettingsService;

final class PrivacyController extends Controller
{
    private const REQUEST_TYPES = ['access', 'rectification', 'erasure', 'restriction', 'portability', 'objection'];
    private const STATUSES = ['received', 'verifying', 'processing', 'completed', 'rejected'];

    public function index(): string
    {
        return $this->view('privacy/index', [
            'title' => 'Privacy',
            'requests' => app()->db()->fetchAll('SELECT * FROM data_subject_requests ORDER BY created_at DESC LIMIT 200'),
            'business' => (new SettingsService())->business(),
        ]);
    }

    public function store(Request $request): never
    {
        $data = $request->all();
        $validator = (new Validator($data))
            ->required('request_type', 'Request type')
            ->in('request_type', self::REQUEST_TYPES, 'Request type')
            ->required('subject_name', 'Subject name')
            ->max('subject_name', 190, 'Subject name')
            ->required('subject_email', 'Subject email')
            ->max('subject_email', 190, 'Subject email')
            ->email('subject_email', 'Subject email');

        if ($validator->fails()) {
            $this->backWithErrors($validator->errors(), $data);
        }

        $id = app()->db()->insert(
            'INSERT INTO data_subject_requests (request_type, subject_name, subject_email, due_at)
             VALUES (?, ?, ?, ?)',
            [
                $data['request_type'],
                $data['subject_name'],
                mb_strtolower(trim((string) $data['subject_email'])),
                date('Y-m-d', strtotime('+30 days')),
            ]
        );

        AuditLogger::log('privacy.dsr_created', 'data_subject_request', $id);
        Session::flash('success', 'Data subject request logged with a 30-day deadline.');
        $this->redirect('/privacy');
    }

    public function update(Request $request, string $id): never
    {
        $data = $request->all();
        $validator = (new Validator($data))
            ->required('status', 'Status')
            ->in('status', self::STATUSES, 'Status')
            ->max('verification_notes', 5000, 'Verification notes')
            ->max('response_notes', 5000, 'Response notes');

        if ($validator->fails()) {
            $this->backWithErrors($validator->errors(), $data);
        }

        app()->db()->execute(
            'UPDATE data_subject_requests SET status = ?, verification_notes = ?, response_notes = ?, handled_by = ?, updated_at = NOW() WHERE id = ?',
            [
                $data['status'],
                $data['verification_notes'] ?? null,
                $data['response_notes'] ?? null,
                \App\Core\Auth::id(),
                (int) $id,
            ]
        );

        AuditLogger::log('privacy.dsr_updated', 'data_subject_request', (int) $id);
        Session::flash('success', 'Privacy request updated.');
        $this->redirect('/privacy');
    }
}
