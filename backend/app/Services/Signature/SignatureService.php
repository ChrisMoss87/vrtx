<?php

declare(strict_types=1);

namespace App\Services\Signature;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;

class SignatureService
{
    public function create(array $data): SignatureRequest
    {
        $data['created_by'] = Auth::id();

        $request = DB::table('signature_requests')->insertGetId($data);

        // Add signers
        if (!empty($data['signers'])) {
            foreach ($data['signers'] as $index => $signerData) {
                $request->signers()->create([
                    'email' => $signerData['email'],
                    'name' => $signerData['name'],
                    'role' => $signerData['role'] ?? SignatureSigner::ROLE_SIGNER,
                    'sign_order' => $signerData['sign_order'] ?? ($index + 1),
                    'contact_id' => $signerData['contact_id'] ?? null,
                ]);
            }
        }

        // Add fields
        if (!empty($data['fields'])) {
            $signers = $request->signers()->get()->keyBy('sign_order');

            foreach ($data['fields'] as $fieldData) {
                $signerId = null;
                if (isset($fieldData['signer_order']) && isset($signers[$fieldData['signer_order']])) {
                    $signerId = $signers[$fieldData['signer_order']]->id;
                }

                $request->fields()->create([
                    'signer_id' => $signerId,
                    'field_type' => $fieldData['field_type'] ?? SignatureField::TYPE_SIGNATURE,
                    'page_number' => $fieldData['page_number'] ?? 1,
                    'x_position' => $fieldData['x_position'] ?? 100,
                    'y_position' => $fieldData['y_position'] ?? 100,
                    'width' => $fieldData['width'] ?? 200,
                    'height' => $fieldData['height'] ?? 50,
                    'required' => $fieldData['required'] ?? true,
                    'label' => $fieldData['label'] ?? null,
                ]);
            }
        }

        $request->logEvent(SignatureAuditLog::EVENT_CREATED, 'Signature request created');

        return $request->load(['signers', 'fields']);
    }

    public function createFromDocument(GeneratedDocument $document, array $data): SignatureRequest
    {
        $data['document_id'] = $document->id;
        $data['title'] = $data['title'] ?? $document->name;
        $data['file_path'] = $document->file_path;
        $data['file_url'] = $document->file_url;
        $data['source_type'] = $document->record_type;
        $data['source_id'] = $document->record_id;

        return $this->create($data);
    }

    public function createFromTemplate(SignatureTemplate $template, array $data): SignatureRequest
    {
        $request = $this->create($data);
        $template->applyToRequest($request);

        return $request->fresh(['signers', 'fields']);
    }

    public function update(SignatureRequest $request, array $data): SignatureRequest
    {
        if (!$request->isEditable()) {
            throw new \Exception('Cannot edit a signature request that has been sent.');
        }

        $request->update($data);

        // Update signers if provided
        if (isset($data['signers'])) {
            $request->signers()->delete();

            foreach ($data['signers'] as $index => $signerData) {
                $request->signers()->create([
                    'email' => $signerData['email'],
                    'name' => $signerData['name'],
                    'role' => $signerData['role'] ?? SignatureSigner::ROLE_SIGNER,
                    'sign_order' => $signerData['sign_order'] ?? ($index + 1),
                    'contact_id' => $signerData['contact_id'] ?? null,
                ]);
            }
        }

        // Update fields if provided
        if (isset($data['fields'])) {
            $request->fields()->delete();
            $signers = $request->signers()->get()->keyBy('sign_order');

            foreach ($data['fields'] as $fieldData) {
                $signerId = null;
                if (isset($fieldData['signer_order']) && isset($signers[$fieldData['signer_order']])) {
                    $signerId = $signers[$fieldData['signer_order']]->id;
                }

                $request->fields()->create([
                    'signer_id' => $signerId,
                    'field_type' => $fieldData['field_type'] ?? SignatureField::TYPE_SIGNATURE,
                    'page_number' => $fieldData['page_number'] ?? 1,
                    'x_position' => $fieldData['x_position'] ?? 100,
                    'y_position' => $fieldData['y_position'] ?? 100,
                    'width' => $fieldData['width'] ?? 200,
                    'height' => $fieldData['height'] ?? 50,
                    'required' => $fieldData['required'] ?? true,
                    'label' => $fieldData['label'] ?? null,
                ]);
            }
        }

        return $request->fresh(['signers', 'fields']);
    }

    public function send(SignatureRequest $request): void
    {
        if ($request->signers()->count() === 0) {
            throw new \Exception('Cannot send a signature request without signers.');
        }

        $request->send();

        // Send notifications to first signer(s)
        $firstOrder = $request->signers()->min('sign_order');
        $firstSigners = $request->signers()->where('sign_order', $firstOrder)->get();

        foreach ($firstSigners as $signer) {
            $this->sendSigningNotification($signer);
        }
    }

    public function void(SignatureRequest $request, string $reason): void
    {
        if (!$request->canBeVoided()) {
            throw new \Exception('Cannot void this signature request.');
        }

        $request->void($reason);

        // Notify all signers
        foreach ($request->signers as $signer) {
            $this->sendVoidNotification($signer, $reason);
        }
    }

    public function remind(SignatureRequest $request): void
    {
        $nextSigner = $request->getNextSigner();

        if ($nextSigner) {
            $this->sendReminderNotification($nextSigner);
            $request->logEvent(SignatureAuditLog::EVENT_REMINDED, "Reminder sent to {$nextSigner->name}");
        }
    }

    public function viewDocument(SignatureSigner $signer): void
    {
        $signer->markAsViewed();
    }

    public function sign(SignatureSigner $signer, array $fieldValues): void
    {
        if (!$signer->canSign()) {
            throw new \Exception('Cannot sign at this time. Please wait for your turn or check if the request is still valid.');
        }

        // Fill in the signature fields
        $fields = $signer->fields()->get();

        foreach ($fields as $field) {
            if (isset($fieldValues[$field->id])) {
                $field->fill($fieldValues[$field->id]);
            } elseif ($field->required) {
                throw new \Exception("Required field '{$field->label}' is missing.");
            }
        }

        // Mark as signed
        $signatureData = [
            'fields' => $fieldValues,
            'timestamp' => now()->toISOString(),
        ];

        $signer->sign($signatureData, request()->ip(), request()->userAgent());

        // Notify next signer if applicable
        $nextSigner = $signer->request->getNextSigner();
        if ($nextSigner) {
            $this->sendSigningNotification($nextSigner);
        }
    }

    public function decline(SignatureSigner $signer, string $reason): void
    {
        $signer->decline($reason);

        // Notify the request creator
        $this->sendDeclineNotification($signer, $reason);
    }

    protected function sendSigningNotification(SignatureSigner $signer): void
    {
        // In production, send email notification
        // Mail::to($signer->email)->send(new SigningRequestMail($signer));
    }

    protected function sendReminderNotification(SignatureSigner $signer): void
    {
        // In production, send reminder email
        // Mail::to($signer->email)->send(new SigningReminderMail($signer));
    }

    protected function sendVoidNotification(SignatureSigner $signer, string $reason): void
    {
        // In production, send void notification
        // Mail::to($signer->email)->send(new SigningVoidedMail($signer, $reason));
    }

    protected function sendDeclineNotification(SignatureSigner $signer, string $reason): void
    {
        // In production, send decline notification to request creator
    }

    public function getSignerByToken(string $token): ?SignatureSigner
    {
        return DB::table('signature_signers')->where('access_token', $token)->first();
    }

    public function checkExpiredRequests(): int
    {
        $expired = SignatureRequest::expired()->get();
        $count = 0;

        foreach ($expired as $request) {
            $request->status = SignatureRequest::STATUS_EXPIRED;
            $request->save();
            $request->logEvent(SignatureAuditLog::EVENT_EXPIRED, 'Signature request expired');
            $count++;
        }

        return $count;
    }
}
