<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Domain\User\Entities\User;
use App\Domain\Email\Entities\EmailAccount;
use App\Domain\Email\Entities\EmailMessage;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmailApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Role $role;
    protected EmailAccount $emailAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // Create role with email permissions
        $this->role = DB::table('roles')->insertGetId(['name' => 'admin', 'guard_name' => 'web']);
        $permissions = [
            'email.view',
            'email.create',
            'email.send',
            'email.delete',
        ];
        foreach ($permissions as $permName) {
            $perm = DB::table('permissions')->insertGetId(['name' => $permName, 'guard_name' => 'web']);
            $this->role->givePermissionTo($perm);
        }
        $this->user->assignRole($this->role);

        $this->emailAccount = EmailAccount::factory()->create([
            'user_id' => $this->user->id,
        ]);

        Sanctum::actingAs($this->user);
    }

    // ==========================================
    // Email Account Tests
    // ==========================================

    public function test_can_list_email_accounts(): void
    {
        EmailAccount::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/email/accounts');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'accounts' => [
                    '*' => ['id', 'email', 'provider', 'is_active'],
                ],
            ]);
    }

    public function test_can_create_email_account(): void
    {
        $response = $this->postJson('/api/v1/email/accounts', [
            'email' => 'new@example.com',
            'provider' => 'gmail',
            'name' => 'Work Email',
        ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_can_delete_email_account(): void
    {
        $response = $this->deleteJson("/api/v1/email/accounts/{$this->emailAccount->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('email_accounts', ['id' => $this->emailAccount->id]);
    }

    public function test_can_sync_email_account(): void
    {
        $response = $this->postJson("/api/v1/email/accounts/{$this->emailAccount->id}/sync");

        // Should queue a sync job or return appropriate response
        $response->assertOk();
    }

    // ==========================================
    // Email Message Tests
    // ==========================================

    public function test_can_list_email_messages(): void
    {
        EmailMessage::factory()->count(5)->create([
            'email_account_id' => $this->emailAccount->id,
        ]);

        $response = $this->getJson('/api/v1/email/messages');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'messages' => [
                    'data',
                    'current_page',
                    'total',
                ],
            ]);
    }

    public function test_can_filter_messages_by_folder(): void
    {
        EmailMessage::factory()->count(2)->create([
            'email_account_id' => $this->emailAccount->id,
            'folder' => 'inbox',
        ]);
        EmailMessage::factory()->create([
            'email_account_id' => $this->emailAccount->id,
            'folder' => 'sent',
        ]);

        $response = $this->getJson('/api/v1/email/messages?folder=inbox');

        $response->assertOk();
        $messages = $response->json('messages.data');
        foreach ($messages as $message) {
            $this->assertEquals('inbox', $message['folder']);
        }
    }

    public function test_can_filter_messages_by_read_status(): void
    {
        EmailMessage::factory()->count(2)->create([
            'email_account_id' => $this->emailAccount->id,
            'is_read' => true,
        ]);
        EmailMessage::factory()->create([
            'email_account_id' => $this->emailAccount->id,
            'is_read' => false,
        ]);

        $response = $this->getJson('/api/v1/email/messages?is_read=false');

        $response->assertOk();
        $messages = $response->json('messages.data');
        foreach ($messages as $message) {
            $this->assertFalse($message['is_read']);
        }
    }

    public function test_can_show_email_message(): void
    {
        $message = EmailMessage::factory()->create([
            'email_account_id' => $this->emailAccount->id,
        ]);

        $response = $this->getJson("/api/v1/email/messages/{$message->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => [
                    'id' => $message->id,
                ],
            ]);
    }

    public function test_can_compose_email(): void
    {
        $response = $this->postJson('/api/v1/email/compose', [
            'account_id' => $this->emailAccount->id,
            'to' => ['recipient@example.com'],
            'subject' => 'Test Subject',
            'body' => '<p>Test body content</p>',
        ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('email_messages', [
            'email_account_id' => $this->emailAccount->id,
            'subject' => 'Test Subject',
            'status' => 'draft',
        ]);
    }

    public function test_can_send_email(): void
    {
        $message = EmailMessage::factory()->create([
            'email_account_id' => $this->emailAccount->id,
            'status' => 'draft',
        ]);

        $response = $this->postJson("/api/v1/email/messages/{$message->id}/send");

        $response->assertOk();
        // In real implementation, this would queue an email job
    }

    public function test_can_mark_email_as_read(): void
    {
        $message = EmailMessage::factory()->create([
            'email_account_id' => $this->emailAccount->id,
            'is_read' => false,
        ]);

        $response = $this->postJson("/api/v1/email/messages/{$message->id}/mark-read");

        $response->assertOk();
        $this->assertTrue($message->fresh()->is_read);
    }

    public function test_can_mark_email_as_unread(): void
    {
        $message = EmailMessage::factory()->create([
            'email_account_id' => $this->emailAccount->id,
            'is_read' => true,
        ]);

        $response = $this->postJson("/api/v1/email/messages/{$message->id}/mark-unread");

        $response->assertOk();
        $this->assertFalse($message->fresh()->is_read);
    }

    public function test_can_delete_email_message(): void
    {
        $message = EmailMessage::factory()->create([
            'email_account_id' => $this->emailAccount->id,
        ]);

        $response = $this->deleteJson("/api/v1/email/messages/{$message->id}");

        $response->assertOk();
        $this->assertSoftDeleted('email_messages', ['id' => $message->id]);
    }

    public function test_can_move_email_to_folder(): void
    {
        $message = EmailMessage::factory()->create([
            'email_account_id' => $this->emailAccount->id,
            'folder' => 'inbox',
        ]);

        $response = $this->postJson("/api/v1/email/messages/{$message->id}/move", [
            'folder' => 'archive',
        ]);

        $response->assertOk();
        $this->assertEquals('archive', $message->fresh()->folder);
    }

    public function test_can_star_email(): void
    {
        $message = EmailMessage::factory()->create([
            'email_account_id' => $this->emailAccount->id,
            'is_starred' => false,
        ]);

        $response = $this->postJson("/api/v1/email/messages/{$message->id}/star");

        $response->assertOk();
        $this->assertTrue($message->fresh()->is_starred);
    }

    // ==========================================
    // Email Thread Tests
    // ==========================================

    public function test_can_get_email_thread(): void
    {
        $threadId = 'thread_123';
        EmailMessage::factory()->count(3)->create([
            'email_account_id' => $this->emailAccount->id,
            'thread_id' => $threadId,
        ]);

        $response = $this->getJson("/api/v1/email/threads/{$threadId}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'messages',
            ]);
    }

    // ==========================================
    // Email Template Tests
    // ==========================================

    public function test_can_list_email_templates(): void
    {
        EmailTemplate::factory()->count(3)->create([
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/email/templates');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'templates' => [
                    '*' => ['id', 'name', 'subject'],
                ],
            ]);
    }

    public function test_can_create_email_template(): void
    {
        $response = $this->postJson('/api/v1/email/templates', [
            'name' => 'Welcome Email',
            'subject' => 'Welcome to our platform!',
            'body' => '<h1>Welcome!</h1><p>Thank you for joining.</p>',
        ]);

        $response->assertCreated()
            ->assertJsonPath('template.name', 'Welcome Email');

        $this->assertDatabaseHas('email_templates', [
            'name' => 'Welcome Email',
        ]);
    }

    public function test_can_update_email_template(): void
    {
        $template = EmailTemplate::factory()->create([
            'name' => 'Original',
            'created_by' => $this->user->id,
        ]);

        $response = $this->putJson("/api/v1/email/templates/{$template->id}", [
            'name' => 'Updated Template',
        ]);

        $response->assertOk();
        $this->assertEquals('Updated Template', $template->fresh()->name);
    }

    public function test_can_delete_email_template(): void
    {
        $template = EmailTemplate::factory()->create([
            'created_by' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/v1/email/templates/{$template->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('email_templates', ['id' => $template->id]);
    }

    // ==========================================
    // Search Tests
    // ==========================================

    public function test_can_search_emails(): void
    {
        EmailMessage::factory()->create([
            'email_account_id' => $this->emailAccount->id,
            'subject' => 'Important meeting reminder',
        ]);
        EmailMessage::factory()->create([
            'email_account_id' => $this->emailAccount->id,
            'subject' => 'Random subject',
        ]);

        $response = $this->getJson('/api/v1/email/messages?search=important');

        $response->assertOk();
    }

    // ==========================================
    // Bulk Actions Tests
    // ==========================================

    public function test_can_bulk_mark_as_read(): void
    {
        $messages = EmailMessage::factory()->count(3)->create([
            'email_account_id' => $this->emailAccount->id,
            'is_read' => false,
        ]);

        $ids = $messages->pluck('id')->toArray();

        $response = $this->postJson('/api/v1/email/messages/bulk-mark-read', [
            'ids' => $ids,
        ]);

        $response->assertOk();
        foreach ($messages as $message) {
            $this->assertTrue($message->fresh()->is_read);
        }
    }

    public function test_can_bulk_delete(): void
    {
        $messages = EmailMessage::factory()->count(3)->create([
            'email_account_id' => $this->emailAccount->id,
        ]);

        $ids = $messages->pluck('id')->toArray();

        $response = $this->postJson('/api/v1/email/messages/bulk-delete', [
            'ids' => $ids,
        ]);

        $response->assertOk();
        foreach ($ids as $id) {
            $this->assertSoftDeleted('email_messages', ['id' => $id]);
        }
    }
}
