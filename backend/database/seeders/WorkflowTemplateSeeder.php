<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkflowTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = $this->getTemplates();

        foreach ($templates as $template) {
            WorkflowTemplate::updateOrCreate(
                ['slug' => $template['slug']],
                $template
            );
        }
    }

    /**
     * Get all template definitions.
     */
    private function getTemplates(): array
    {
        return array_merge(
            $this->getLeadTemplates(),
            $this->getDealTemplates(),
            $this->getCustomerTemplates(),
            $this->getDataQualityTemplates(),
            $this->getProductivityTemplates(),
            $this->getCommunicationTemplates()
        );
    }

    /**
     * Lead Management Templates.
     */
    private function getLeadTemplates(): array
    {
        return [
            [
                'name' => 'Welcome Email on New Lead',
                'slug' => 'welcome-email-new-lead',
                'description' => 'Automatically send a personalized welcome email when a new lead is created. Great for immediate engagement and setting expectations.',
                'category' => WorkflowTemplate::CATEGORY_LEAD,
                'icon' => 'mail',
                'difficulty' => WorkflowTemplate::DIFFICULTY_BEGINNER,
                'estimated_time_saved_hours' => 5,
                'is_system' => true,
                'required_modules' => ['leads'],
                'variable_mappings' => [
                    'email_template_id' => ['label' => 'Email Template', 'type' => 'email_template'],
                ],
                'workflow_data' => [
                    'trigger_type' => 'record_created',
                    'trigger_timing' => 'all',
                    'steps' => [
                        [
                            'action_type' => 'send_email',
                            'name' => 'Send Welcome Email',
                            'action_config' => [
                                'recipient_type' => 'field',
                                'recipient_field' => 'email',
                                'template_id' => '{{email_template_id}}',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Lead Assignment Round Robin',
                'slug' => 'lead-assignment-round-robin',
                'description' => 'Automatically assign new leads to sales reps in a round-robin fashion for fair distribution.',
                'category' => WorkflowTemplate::CATEGORY_LEAD,
                'icon' => 'users',
                'difficulty' => WorkflowTemplate::DIFFICULTY_INTERMEDIATE,
                'estimated_time_saved_hours' => 10,
                'is_system' => true,
                'required_modules' => ['leads'],
                'variable_mappings' => [
                    'user_ids' => ['label' => 'Sales Reps', 'type' => 'users', 'multiple' => true],
                ],
                'workflow_data' => [
                    'trigger_type' => 'record_created',
                    'trigger_timing' => 'all',
                    'steps' => [
                        [
                            'action_type' => 'assign_user',
                            'name' => 'Assign to Sales Rep',
                            'action_config' => [
                                'assignment_type' => 'round_robin',
                                'user_ids' => '{{user_ids}}',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Lead Stale Reminder',
                'slug' => 'lead-stale-reminder',
                'description' => 'Send a reminder to the lead owner when a lead has not been contacted in 3 days.',
                'category' => WorkflowTemplate::CATEGORY_LEAD,
                'icon' => 'clock',
                'difficulty' => WorkflowTemplate::DIFFICULTY_INTERMEDIATE,
                'estimated_time_saved_hours' => 8,
                'is_system' => true,
                'required_modules' => ['leads'],
                'variable_mappings' => [
                    'days_threshold' => ['label' => 'Days Before Reminder', 'type' => 'number', 'default' => 3],
                ],
                'workflow_data' => [
                    'trigger_type' => 'time_based',
                    'trigger_config' => [
                        'schedule_type' => 'daily',
                        'time' => '09:00',
                    ],
                    'conditions' => [
                        'logic' => 'and',
                        'groups' => [
                            [
                                'logic' => 'and',
                                'conditions' => [
                                    ['field' => 'last_contacted_at', 'operator' => 'older_than_days', 'value' => '{{days_threshold}}'],
                                    ['field' => 'status', 'operator' => 'not_equals', 'value' => 'converted'],
                                ],
                            ],
                        ],
                    ],
                    'steps' => [
                        [
                            'action_type' => 'send_notification',
                            'name' => 'Notify Owner',
                            'action_config' => [
                                'recipient_type' => 'owner',
                                'title' => 'Lead needs attention',
                                'message' => 'Lead {{record.name}} has not been contacted in {{days_threshold}} days.',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Hot Lead Alert',
                'slug' => 'hot-lead-alert',
                'description' => 'Instantly notify sales manager when a high-value lead is created or lead score exceeds threshold.',
                'category' => WorkflowTemplate::CATEGORY_LEAD,
                'icon' => 'flame',
                'difficulty' => WorkflowTemplate::DIFFICULTY_BEGINNER,
                'estimated_time_saved_hours' => 4,
                'is_system' => true,
                'required_modules' => ['leads'],
                'variable_mappings' => [
                    'score_threshold' => ['label' => 'Score Threshold', 'type' => 'number', 'default' => 80],
                    'notify_user_id' => ['label' => 'Notify User', 'type' => 'user'],
                ],
                'workflow_data' => [
                    'trigger_type' => 'field_changed',
                    'watched_fields' => ['lead_score'],
                    'conditions' => [
                        'logic' => 'and',
                        'groups' => [
                            [
                                'logic' => 'and',
                                'conditions' => [
                                    ['field' => 'lead_score', 'operator' => 'greater_than_or_equal', 'value' => '{{score_threshold}}'],
                                ],
                            ],
                        ],
                    ],
                    'steps' => [
                        [
                            'action_type' => 'send_notification',
                            'name' => 'Alert Manager',
                            'action_config' => [
                                'user_ids' => ['{{notify_user_id}}'],
                                'title' => '🔥 Hot Lead Alert',
                                'message' => '{{record.name}} just became a hot lead with score {{record.lead_score}}!',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Lead Source Tagging',
                'slug' => 'lead-source-tagging',
                'description' => 'Automatically add tags based on lead source for better segmentation and reporting.',
                'category' => WorkflowTemplate::CATEGORY_LEAD,
                'icon' => 'tag',
                'difficulty' => WorkflowTemplate::DIFFICULTY_BEGINNER,
                'estimated_time_saved_hours' => 3,
                'is_system' => true,
                'required_modules' => ['leads'],
                'workflow_data' => [
                    'trigger_type' => 'record_created',
                    'steps' => [
                        [
                            'action_type' => 'condition',
                            'name' => 'Check Source',
                            'action_config' => [
                                'conditions' => [
                                    ['field' => 'source', 'operator' => 'equals', 'value' => 'website'],
                                ],
                                'true_step' => 1,
                                'false_step' => 2,
                            ],
                        ],
                        [
                            'action_type' => 'add_tag',
                            'name' => 'Add Website Tag',
                            'action_config' => ['tags' => ['inbound', 'website']],
                        ],
                        [
                            'action_type' => 'add_tag',
                            'name' => 'Add Other Tag',
                            'action_config' => ['tags' => ['outbound']],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Deal & Sales Templates.
     */
    private function getDealTemplates(): array
    {
        return [
            [
                'name' => 'Deal Stage Change Notification',
                'slug' => 'deal-stage-change-notification',
                'description' => 'Notify stakeholders when a deal moves to a new stage in the pipeline.',
                'category' => WorkflowTemplate::CATEGORY_DEAL,
                'icon' => 'arrow-right',
                'difficulty' => WorkflowTemplate::DIFFICULTY_BEGINNER,
                'estimated_time_saved_hours' => 6,
                'is_system' => true,
                'required_modules' => ['deals'],
                'variable_mappings' => [
                    'notify_user_ids' => ['label' => 'Users to Notify', 'type' => 'users', 'multiple' => true],
                ],
                'workflow_data' => [
                    'trigger_type' => 'field_changed',
                    'watched_fields' => ['stage_id'],
                    'steps' => [
                        [
                            'action_type' => 'send_notification',
                            'name' => 'Notify Team',
                            'action_config' => [
                                'user_ids' => '{{notify_user_ids}}',
                                'title' => 'Deal Stage Updated',
                                'message' => '{{record.name}} moved to {{record.stage}}',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Deal Won Celebration',
                'slug' => 'deal-won-celebration',
                'description' => 'Celebrate closed deals by notifying the team and creating follow-up tasks.',
                'category' => WorkflowTemplate::CATEGORY_DEAL,
                'icon' => 'trophy',
                'difficulty' => WorkflowTemplate::DIFFICULTY_BEGINNER,
                'estimated_time_saved_hours' => 4,
                'is_system' => true,
                'required_modules' => ['deals'],
                'workflow_data' => [
                    'trigger_type' => 'field_changed',
                    'watched_fields' => ['status'],
                    'conditions' => [
                        'logic' => 'and',
                        'groups' => [
                            [
                                'logic' => 'and',
                                'conditions' => [
                                    ['field' => 'status', 'operator' => 'equals', 'value' => 'won'],
                                ],
                            ],
                        ],
                    ],
                    'steps' => [
                        [
                            'action_type' => 'send_notification',
                            'name' => 'Celebrate Win',
                            'action_config' => [
                                'recipient_type' => 'all_users',
                                'title' => '🎉 Deal Won!',
                                'message' => '{{record.owner.name}} just closed {{record.name}} for {{record.amount}}!',
                            ],
                        ],
                        [
                            'action_type' => 'create_task',
                            'name' => 'Create Handoff Task',
                            'action_config' => [
                                'subject' => 'Handoff to Customer Success',
                                'description' => 'Schedule onboarding call with {{record.contact.name}}',
                                'due_in_days' => 2,
                                'assigned_to' => 'owner',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Deal Lost Follow-up',
                'slug' => 'deal-lost-followup',
                'description' => 'Automatically create a follow-up task and send feedback survey when a deal is lost.',
                'category' => WorkflowTemplate::CATEGORY_DEAL,
                'icon' => 'message-circle',
                'difficulty' => WorkflowTemplate::DIFFICULTY_INTERMEDIATE,
                'estimated_time_saved_hours' => 5,
                'is_system' => true,
                'required_modules' => ['deals'],
                'workflow_data' => [
                    'trigger_type' => 'field_changed',
                    'watched_fields' => ['status'],
                    'conditions' => [
                        'logic' => 'and',
                        'groups' => [
                            [
                                'logic' => 'and',
                                'conditions' => [
                                    ['field' => 'status', 'operator' => 'equals', 'value' => 'lost'],
                                ],
                            ],
                        ],
                    ],
                    'steps' => [
                        [
                            'action_type' => 'create_task',
                            'name' => 'Create Follow-up Task',
                            'action_config' => [
                                'subject' => 'Follow up on lost deal: {{record.name}}',
                                'description' => 'Document loss reason and schedule re-engagement in 3 months',
                                'due_in_days' => 90,
                                'assigned_to' => 'owner',
                            ],
                        ],
                        [
                            'action_type' => 'add_tag',
                            'name' => 'Tag for Re-engagement',
                            'action_config' => ['tags' => ['re-engage-later']],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Quote Follow-up Reminder',
                'slug' => 'quote-followup-reminder',
                'description' => 'Remind sales rep to follow up on sent quotes that haven\'t been responded to.',
                'category' => WorkflowTemplate::CATEGORY_DEAL,
                'icon' => 'file-text',
                'difficulty' => WorkflowTemplate::DIFFICULTY_INTERMEDIATE,
                'estimated_time_saved_hours' => 8,
                'is_system' => true,
                'required_modules' => ['deals', 'quotes'],
                'variable_mappings' => [
                    'days_after_quote' => ['label' => 'Days After Quote Sent', 'type' => 'number', 'default' => 3],
                ],
                'workflow_data' => [
                    'trigger_type' => 'time_based',
                    'trigger_config' => ['schedule_type' => 'daily', 'time' => '09:00'],
                    'conditions' => [
                        'logic' => 'and',
                        'groups' => [
                            [
                                'logic' => 'and',
                                'conditions' => [
                                    ['field' => 'quote_sent_at', 'operator' => 'older_than_days', 'value' => '{{days_after_quote}}'],
                                    ['field' => 'quote_status', 'operator' => 'equals', 'value' => 'sent'],
                                ],
                            ],
                        ],
                    ],
                    'steps' => [
                        [
                            'action_type' => 'create_task',
                            'name' => 'Create Follow-up Task',
                            'action_config' => [
                                'subject' => 'Follow up on quote for {{record.name}}',
                                'due_in_days' => 1,
                                'assigned_to' => 'owner',
                                'priority' => 'high',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Large Deal Approval',
                'slug' => 'large-deal-approval',
                'description' => 'Require manager approval for deals above a certain amount before moving to final stage.',
                'category' => WorkflowTemplate::CATEGORY_DEAL,
                'icon' => 'shield-check',
                'difficulty' => WorkflowTemplate::DIFFICULTY_ADVANCED,
                'estimated_time_saved_hours' => 6,
                'is_system' => true,
                'required_modules' => ['deals'],
                'variable_mappings' => [
                    'amount_threshold' => ['label' => 'Amount Threshold', 'type' => 'currency', 'default' => 50000],
                    'approver_id' => ['label' => 'Approver', 'type' => 'user'],
                ],
                'workflow_data' => [
                    'trigger_type' => 'field_changed',
                    'watched_fields' => ['stage_id'],
                    'conditions' => [
                        'logic' => 'and',
                        'groups' => [
                            [
                                'logic' => 'and',
                                'conditions' => [
                                    ['field' => 'amount', 'operator' => 'greater_than_or_equal', 'value' => '{{amount_threshold}}'],
                                    ['field' => 'stage', 'operator' => 'equals', 'value' => 'negotiation'],
                                ],
                            ],
                        ],
                    ],
                    'steps' => [
                        [
                            'action_type' => 'send_notification',
                            'name' => 'Request Approval',
                            'action_config' => [
                                'user_ids' => ['{{approver_id}}'],
                                'title' => 'Deal Approval Required',
                                'message' => '{{record.name}} (${{record.amount}}) requires your approval',
                            ],
                        ],
                        [
                            'action_type' => 'create_task',
                            'name' => 'Approval Task',
                            'action_config' => [
                                'subject' => 'Approve deal: {{record.name}}',
                                'assigned_to' => '{{approver_id}}',
                                'due_in_days' => 1,
                                'priority' => 'high',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Customer Success Templates.
     */
    private function getCustomerTemplates(): array
    {
        return [
            [
                'name' => 'Customer Onboarding Sequence',
                'slug' => 'customer-onboarding-sequence',
                'description' => 'Automatically start onboarding tasks and emails when a deal is won.',
                'category' => WorkflowTemplate::CATEGORY_CUSTOMER,
                'icon' => 'rocket',
                'difficulty' => WorkflowTemplate::DIFFICULTY_INTERMEDIATE,
                'estimated_time_saved_hours' => 15,
                'is_system' => true,
                'required_modules' => ['deals', 'contacts'],
                'workflow_data' => [
                    'trigger_type' => 'field_changed',
                    'watched_fields' => ['status'],
                    'conditions' => [
                        'logic' => 'and',
                        'groups' => [
                            [
                                'logic' => 'and',
                                'conditions' => [
                                    ['field' => 'status', 'operator' => 'equals', 'value' => 'won'],
                                ],
                            ],
                        ],
                    ],
                    'steps' => [
                        [
                            'action_type' => 'create_task',
                            'name' => 'Schedule Kickoff Call',
                            'action_config' => [
                                'subject' => 'Schedule onboarding kickoff with {{record.contact.name}}',
                                'due_in_days' => 2,
                                'priority' => 'high',
                            ],
                        ],
                        [
                            'action_type' => 'send_email',
                            'name' => 'Send Welcome Email',
                            'action_config' => [
                                'recipient_type' => 'contact',
                                'subject' => 'Welcome to {{company_name}}!',
                                'body_html' => '<p>Hi {{record.contact.first_name}},</p><p>Welcome aboard! We\'re excited to have you as a customer.</p>',
                            ],
                        ],
                        [
                            'action_type' => 'update_field',
                            'name' => 'Update Contact Status',
                            'action_config' => [
                                'field' => 'customer_status',
                                'value' => 'onboarding',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Contract Renewal Reminder',
                'slug' => 'contract-renewal-reminder',
                'description' => 'Notify account manager 60 days before contract renewal date.',
                'category' => WorkflowTemplate::CATEGORY_CUSTOMER,
                'icon' => 'calendar',
                'difficulty' => WorkflowTemplate::DIFFICULTY_INTERMEDIATE,
                'estimated_time_saved_hours' => 10,
                'is_system' => true,
                'required_modules' => ['deals'],
                'variable_mappings' => [
                    'days_before' => ['label' => 'Days Before Renewal', 'type' => 'number', 'default' => 60],
                ],
                'workflow_data' => [
                    'trigger_type' => 'time_based',
                    'trigger_config' => ['schedule_type' => 'daily', 'time' => '08:00'],
                    'conditions' => [
                        'logic' => 'and',
                        'groups' => [
                            [
                                'logic' => 'and',
                                'conditions' => [
                                    ['field' => 'renewal_date', 'operator' => 'within_days', 'value' => '{{days_before}}'],
                                    ['field' => 'status', 'operator' => 'equals', 'value' => 'active'],
                                ],
                            ],
                        ],
                    ],
                    'steps' => [
                        [
                            'action_type' => 'create_task',
                            'name' => 'Create Renewal Task',
                            'action_config' => [
                                'subject' => 'Contract renewal coming up: {{record.name}}',
                                'description' => 'Contract renews on {{record.renewal_date}}. Start renewal discussion.',
                                'due_in_days' => 7,
                                'assigned_to' => 'owner',
                                'priority' => 'high',
                            ],
                        ],
                        [
                            'action_type' => 'send_notification',
                            'name' => 'Notify Owner',
                            'action_config' => [
                                'recipient_type' => 'owner',
                                'title' => 'Upcoming Renewal',
                                'message' => '{{record.name}} contract renews in {{days_before}} days',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Customer Anniversary Email',
                'slug' => 'customer-anniversary-email',
                'description' => 'Send a personalized email on customer anniversary to strengthen relationships.',
                'category' => WorkflowTemplate::CATEGORY_CUSTOMER,
                'icon' => 'gift',
                'difficulty' => WorkflowTemplate::DIFFICULTY_BEGINNER,
                'estimated_time_saved_hours' => 3,
                'is_system' => true,
                'required_modules' => ['contacts'],
                'workflow_data' => [
                    'trigger_type' => 'time_based',
                    'trigger_config' => ['schedule_type' => 'daily', 'time' => '09:00'],
                    'conditions' => [
                        'logic' => 'and',
                        'groups' => [
                            [
                                'logic' => 'and',
                                'conditions' => [
                                    ['field' => 'customer_since', 'operator' => 'anniversary_today', 'value' => true],
                                ],
                            ],
                        ],
                    ],
                    'steps' => [
                        [
                            'action_type' => 'send_email',
                            'name' => 'Send Anniversary Email',
                            'action_config' => [
                                'recipient_type' => 'field',
                                'recipient_field' => 'email',
                                'subject' => 'Happy Anniversary, {{record.first_name}}! 🎉',
                                'body_html' => '<p>It\'s been another great year working together. Thank you for your continued partnership!</p>',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Churn Risk Alert',
                'slug' => 'churn-risk-alert',
                'description' => 'Alert customer success team when engagement drops or health score decreases.',
                'category' => WorkflowTemplate::CATEGORY_CUSTOMER,
                'icon' => 'alert-triangle',
                'difficulty' => WorkflowTemplate::DIFFICULTY_ADVANCED,
                'estimated_time_saved_hours' => 12,
                'is_system' => true,
                'required_modules' => ['contacts'],
                'variable_mappings' => [
                    'health_threshold' => ['label' => 'Health Score Threshold', 'type' => 'number', 'default' => 50],
                    'cs_team_ids' => ['label' => 'CS Team', 'type' => 'users', 'multiple' => true],
                ],
                'workflow_data' => [
                    'trigger_type' => 'field_changed',
                    'watched_fields' => ['health_score'],
                    'conditions' => [
                        'logic' => 'and',
                        'groups' => [
                            [
                                'logic' => 'and',
                                'conditions' => [
                                    ['field' => 'health_score', 'operator' => 'less_than', 'value' => '{{health_threshold}}'],
                                    ['field' => 'customer_status', 'operator' => 'equals', 'value' => 'active'],
                                ],
                            ],
                        ],
                    ],
                    'steps' => [
                        [
                            'action_type' => 'send_notification',
                            'name' => 'Alert CS Team',
                            'action_config' => [
                                'user_ids' => '{{cs_team_ids}}',
                                'title' => '⚠️ Churn Risk Detected',
                                'message' => '{{record.name}} health score dropped to {{record.health_score}}. Immediate attention needed.',
                            ],
                        ],
                        [
                            'action_type' => 'create_task',
                            'name' => 'Create Intervention Task',
                            'action_config' => [
                                'subject' => 'Churn intervention: {{record.name}}',
                                'description' => 'Health score: {{record.health_score}}. Schedule check-in call.',
                                'due_in_days' => 1,
                                'priority' => 'urgent',
                            ],
                        ],
                        [
                            'action_type' => 'add_tag',
                            'name' => 'Tag as At Risk',
                            'action_config' => ['tags' => ['churn-risk', 'needs-attention']],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Data Quality Templates.
     */
    private function getDataQualityTemplates(): array
    {
        return [
            [
                'name' => 'Missing Field Reminder',
                'slug' => 'missing-field-reminder',
                'description' => 'Remind record owner to fill in important missing fields.',
                'category' => WorkflowTemplate::CATEGORY_DATA,
                'icon' => 'alert-circle',
                'difficulty' => WorkflowTemplate::DIFFICULTY_BEGINNER,
                'estimated_time_saved_hours' => 5,
                'is_system' => true,
                'required_modules' => ['leads', 'contacts', 'deals'],
                'variable_mappings' => [
                    'required_field' => ['label' => 'Required Field', 'type' => 'field'],
                ],
                'workflow_data' => [
                    'trigger_type' => 'record_created',
                    'steps' => [
                        [
                            'action_type' => 'delay',
                            'name' => 'Wait 1 hour',
                            'action_config' => ['delay_type' => 'hours', 'delay_value' => 1],
                        ],
                        [
                            'action_type' => 'condition',
                            'name' => 'Check if field is empty',
                            'action_config' => [
                                'conditions' => [
                                    ['field' => '{{required_field}}', 'operator' => 'is_empty', 'value' => true],
                                ],
                            ],
                        ],
                        [
                            'action_type' => 'send_notification',
                            'name' => 'Remind Owner',
                            'action_config' => [
                                'recipient_type' => 'owner',
                                'title' => 'Missing Information',
                                'message' => 'Please add {{required_field}} for {{record.name}}',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Duplicate Detection Alert',
                'slug' => 'duplicate-detection-alert',
                'description' => 'Alert when a potential duplicate record is created based on email or phone.',
                'category' => WorkflowTemplate::CATEGORY_DATA,
                'icon' => 'copy',
                'difficulty' => WorkflowTemplate::DIFFICULTY_ADVANCED,
                'estimated_time_saved_hours' => 8,
                'is_system' => true,
                'required_modules' => ['contacts', 'leads'],
                'workflow_data' => [
                    'trigger_type' => 'record_created',
                    'steps' => [
                        [
                            'action_type' => 'webhook',
                            'name' => 'Check for Duplicates',
                            'action_config' => [
                                'url' => '/api/v1/internal/check-duplicates',
                                'method' => 'POST',
                                'payload' => [
                                    'email' => '{{record.email}}',
                                    'phone' => '{{record.phone}}',
                                    'record_id' => '{{record.id}}',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Inactive Record Cleanup',
                'slug' => 'inactive-record-cleanup',
                'description' => 'Tag records that have been inactive for 6 months for cleanup review.',
                'category' => WorkflowTemplate::CATEGORY_DATA,
                'icon' => 'archive',
                'difficulty' => WorkflowTemplate::DIFFICULTY_INTERMEDIATE,
                'estimated_time_saved_hours' => 6,
                'is_system' => true,
                'required_modules' => ['leads', 'contacts'],
                'variable_mappings' => [
                    'inactive_days' => ['label' => 'Days Inactive', 'type' => 'number', 'default' => 180],
                ],
                'workflow_data' => [
                    'trigger_type' => 'time_based',
                    'trigger_config' => ['schedule_type' => 'weekly', 'day' => 'monday', 'time' => '06:00'],
                    'conditions' => [
                        'logic' => 'and',
                        'groups' => [
                            [
                                'logic' => 'and',
                                'conditions' => [
                                    ['field' => 'updated_at', 'operator' => 'older_than_days', 'value' => '{{inactive_days}}'],
                                ],
                            ],
                        ],
                    ],
                    'steps' => [
                        [
                            'action_type' => 'add_tag',
                            'name' => 'Tag for Review',
                            'action_config' => ['tags' => ['cleanup-candidate', 'inactive']],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Team Productivity Templates.
     */
    private function getProductivityTemplates(): array
    {
        return [
            [
                'name' => 'Task Overdue Notification',
                'slug' => 'task-overdue-notification',
                'description' => 'Notify task owner and manager when a task becomes overdue.',
                'category' => WorkflowTemplate::CATEGORY_PRODUCTIVITY,
                'icon' => 'clock',
                'difficulty' => WorkflowTemplate::DIFFICULTY_BEGINNER,
                'estimated_time_saved_hours' => 4,
                'is_system' => true,
                'required_modules' => ['tasks'],
                'workflow_data' => [
                    'trigger_type' => 'time_based',
                    'trigger_config' => ['schedule_type' => 'hourly'],
                    'conditions' => [
                        'logic' => 'and',
                        'groups' => [
                            [
                                'logic' => 'and',
                                'conditions' => [
                                    ['field' => 'due_date', 'operator' => 'less_than', 'value' => 'now'],
                                    ['field' => 'status', 'operator' => 'not_equals', 'value' => 'completed'],
                                ],
                            ],
                        ],
                    ],
                    'steps' => [
                        [
                            'action_type' => 'send_notification',
                            'name' => 'Notify Owner',
                            'action_config' => [
                                'recipient_type' => 'owner',
                                'title' => '⏰ Task Overdue',
                                'message' => 'Task "{{record.subject}}" is overdue!',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Meeting Follow-up Task',
                'slug' => 'meeting-followup-task',
                'description' => 'Automatically create a follow-up task after a meeting is completed.',
                'category' => WorkflowTemplate::CATEGORY_PRODUCTIVITY,
                'icon' => 'calendar-check',
                'difficulty' => WorkflowTemplate::DIFFICULTY_BEGINNER,
                'estimated_time_saved_hours' => 5,
                'is_system' => true,
                'required_modules' => ['meetings', 'tasks'],
                'workflow_data' => [
                    'trigger_type' => 'field_changed',
                    'watched_fields' => ['status'],
                    'conditions' => [
                        'logic' => 'and',
                        'groups' => [
                            [
                                'logic' => 'and',
                                'conditions' => [
                                    ['field' => 'status', 'operator' => 'equals', 'value' => 'completed'],
                                ],
                            ],
                        ],
                    ],
                    'steps' => [
                        [
                            'action_type' => 'create_task',
                            'name' => 'Create Follow-up Task',
                            'action_config' => [
                                'subject' => 'Follow up: {{record.subject}}',
                                'description' => 'Send meeting notes and next steps to attendees',
                                'due_in_days' => 1,
                                'assigned_to' => 'owner',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Activity Logging Reminder',
                'slug' => 'activity-logging-reminder',
                'description' => 'Remind sales reps to log activities if no activity recorded in 2 days.',
                'category' => WorkflowTemplate::CATEGORY_PRODUCTIVITY,
                'icon' => 'edit',
                'difficulty' => WorkflowTemplate::DIFFICULTY_INTERMEDIATE,
                'estimated_time_saved_hours' => 6,
                'is_system' => true,
                'required_modules' => ['leads', 'deals'],
                'workflow_data' => [
                    'trigger_type' => 'time_based',
                    'trigger_config' => ['schedule_type' => 'daily', 'time' => '17:00'],
                    'conditions' => [
                        'logic' => 'and',
                        'groups' => [
                            [
                                'logic' => 'and',
                                'conditions' => [
                                    ['field' => 'last_activity_at', 'operator' => 'older_than_days', 'value' => 2],
                                    ['field' => 'status', 'operator' => 'not_in', 'value' => ['won', 'lost', 'converted']],
                                ],
                            ],
                        ],
                    ],
                    'steps' => [
                        [
                            'action_type' => 'send_notification',
                            'name' => 'Remind to Log Activity',
                            'action_config' => [
                                'recipient_type' => 'owner',
                                'title' => 'Activity Reminder',
                                'message' => 'No activity logged for {{record.name}} in 2 days',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Manager Escalation',
                'slug' => 'manager-escalation',
                'description' => 'Escalate to manager when high-priority tasks are overdue by more than 1 day.',
                'category' => WorkflowTemplate::CATEGORY_PRODUCTIVITY,
                'icon' => 'arrow-up',
                'difficulty' => WorkflowTemplate::DIFFICULTY_INTERMEDIATE,
                'estimated_time_saved_hours' => 4,
                'is_system' => true,
                'required_modules' => ['tasks'],
                'variable_mappings' => [
                    'manager_id' => ['label' => 'Manager', 'type' => 'user'],
                ],
                'workflow_data' => [
                    'trigger_type' => 'time_based',
                    'trigger_config' => ['schedule_type' => 'daily', 'time' => '09:00'],
                    'conditions' => [
                        'logic' => 'and',
                        'groups' => [
                            [
                                'logic' => 'and',
                                'conditions' => [
                                    ['field' => 'due_date', 'operator' => 'older_than_days', 'value' => 1],
                                    ['field' => 'priority', 'operator' => 'equals', 'value' => 'high'],
                                    ['field' => 'status', 'operator' => 'not_equals', 'value' => 'completed'],
                                ],
                            ],
                        ],
                    ],
                    'steps' => [
                        [
                            'action_type' => 'send_notification',
                            'name' => 'Escalate to Manager',
                            'action_config' => [
                                'user_ids' => ['{{manager_id}}'],
                                'title' => '🚨 Overdue High-Priority Task',
                                'message' => 'Task "{{record.subject}}" assigned to {{record.owner.name}} is overdue',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Communication Templates.
     */
    private function getCommunicationTemplates(): array
    {
        return [
            [
                'name' => 'Auto-Reply to Web Form',
                'slug' => 'auto-reply-web-form',
                'description' => 'Send immediate auto-reply when a web form submission creates a lead.',
                'category' => WorkflowTemplate::CATEGORY_COMMUNICATION,
                'icon' => 'mail',
                'difficulty' => WorkflowTemplate::DIFFICULTY_BEGINNER,
                'estimated_time_saved_hours' => 8,
                'is_system' => true,
                'required_modules' => ['leads'],
                'workflow_data' => [
                    'trigger_type' => 'record_created',
                    'conditions' => [
                        'logic' => 'and',
                        'groups' => [
                            [
                                'logic' => 'and',
                                'conditions' => [
                                    ['field' => 'source', 'operator' => 'equals', 'value' => 'web_form'],
                                ],
                            ],
                        ],
                    ],
                    'steps' => [
                        [
                            'action_type' => 'send_email',
                            'name' => 'Send Auto-Reply',
                            'action_config' => [
                                'recipient_type' => 'field',
                                'recipient_field' => 'email',
                                'subject' => 'Thanks for reaching out, {{record.first_name}}!',
                                'body_html' => '<p>Hi {{record.first_name}},</p><p>Thank you for contacting us! We\'ve received your inquiry and will get back to you within 24 hours.</p><p>Best regards,<br>The Team</p>',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Birthday Email',
                'slug' => 'birthday-email',
                'description' => 'Send personalized birthday wishes to contacts.',
                'category' => WorkflowTemplate::CATEGORY_COMMUNICATION,
                'icon' => 'cake',
                'difficulty' => WorkflowTemplate::DIFFICULTY_BEGINNER,
                'estimated_time_saved_hours' => 3,
                'is_system' => true,
                'required_modules' => ['contacts'],
                'workflow_data' => [
                    'trigger_type' => 'time_based',
                    'trigger_config' => ['schedule_type' => 'daily', 'time' => '09:00'],
                    'conditions' => [
                        'logic' => 'and',
                        'groups' => [
                            [
                                'logic' => 'and',
                                'conditions' => [
                                    ['field' => 'birthday', 'operator' => 'is_today', 'value' => true],
                                ],
                            ],
                        ],
                    ],
                    'steps' => [
                        [
                            'action_type' => 'send_email',
                            'name' => 'Send Birthday Email',
                            'action_config' => [
                                'recipient_type' => 'field',
                                'recipient_field' => 'email',
                                'subject' => 'Happy Birthday, {{record.first_name}}! 🎂',
                                'body_html' => '<p>Hi {{record.first_name}},</p><p>Wishing you a wonderful birthday filled with joy and happiness!</p><p>Best wishes from all of us!</p>',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Support Ticket Escalation',
                'slug' => 'support-ticket-escalation',
                'description' => 'Escalate unresolved support tickets to senior support after 24 hours.',
                'category' => WorkflowTemplate::CATEGORY_COMMUNICATION,
                'icon' => 'headphones',
                'difficulty' => WorkflowTemplate::DIFFICULTY_INTERMEDIATE,
                'estimated_time_saved_hours' => 10,
                'is_system' => true,
                'required_modules' => ['tickets'],
                'variable_mappings' => [
                    'escalation_hours' => ['label' => 'Hours Before Escalation', 'type' => 'number', 'default' => 24],
                    'senior_support_id' => ['label' => 'Senior Support', 'type' => 'user'],
                ],
                'workflow_data' => [
                    'trigger_type' => 'time_based',
                    'trigger_config' => ['schedule_type' => 'hourly'],
                    'conditions' => [
                        'logic' => 'and',
                        'groups' => [
                            [
                                'logic' => 'and',
                                'conditions' => [
                                    ['field' => 'created_at', 'operator' => 'older_than_hours', 'value' => '{{escalation_hours}}'],
                                    ['field' => 'status', 'operator' => 'in', 'value' => ['open', 'in_progress']],
                                    ['field' => 'priority', 'operator' => 'equals', 'value' => 'high'],
                                ],
                            ],
                        ],
                    ],
                    'steps' => [
                        [
                            'action_type' => 'assign_user',
                            'name' => 'Escalate to Senior',
                            'action_config' => [
                                'assignment_type' => 'specific',
                                'user_id' => '{{senior_support_id}}',
                            ],
                        ],
                        [
                            'action_type' => 'send_notification',
                            'name' => 'Notify Senior Support',
                            'action_config' => [
                                'user_ids' => ['{{senior_support_id}}'],
                                'title' => '🎫 Escalated Ticket',
                                'message' => 'Ticket #{{record.id}} has been escalated to you',
                            ],
                        ],
                        [
                            'action_type' => 'add_tag',
                            'name' => 'Tag as Escalated',
                            'action_config' => ['tags' => ['escalated']],
                        ],
                    ],
                ],
            ],
        ];
    }
}
