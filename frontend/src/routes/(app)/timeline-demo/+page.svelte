<script lang="ts">
	import * as Timeline from '$lib/components/ui/timeline';
	import * as Card from '$lib/components/ui/card';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import { Separator } from '$lib/components/ui/separator';
	import {
		ArrowLeft,
		CheckCircle,
		Clock,
		AlertCircle,
		Mail,
		Phone,
		FileText,
		MessageSquare,
		Calendar,
		DollarSign,
		User,
		Building,
		Pencil,
		Trash2,
		Plus,
		GitMerge
	} from 'lucide-svelte';
	import { goto } from '$app/navigation';
</script>

<div class="container mx-auto max-w-6xl py-8 space-y-8">
	<!-- Header -->
	<div class="flex items-center gap-4">
		<Button variant="ghost" size="icon" onclick={() => goto('/dashboard')}>
			<ArrowLeft class="h-4 w-4" />
		</Button>
		<div>
			<h1 class="text-2xl font-bold">Timeline Component</h1>
			<p class="text-muted-foreground">A flexible timeline component for displaying chronological events</p>
		</div>
	</div>

	<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
		<!-- Basic Timeline -->
		<Card.Root>
			<Card.Header>
				<Card.Title>Basic Timeline</Card.Title>
				<Card.Description>Simple timeline with default styling</Card.Description>
			</Card.Header>
			<Card.Content>
				<Timeline.Root>
					<Timeline.Item>
						<Timeline.Icon />
						<Timeline.Connector />
						<Timeline.Content>
							<Timeline.Header>
								<Timeline.Title>First Event</Timeline.Title>
								<Timeline.Time>2 hours ago</Timeline.Time>
							</Timeline.Header>
							<Timeline.Description>
								This is the first event in the timeline with a simple description.
							</Timeline.Description>
						</Timeline.Content>
					</Timeline.Item>

					<Timeline.Item>
						<Timeline.Icon />
						<Timeline.Connector />
						<Timeline.Content>
							<Timeline.Header>
								<Timeline.Title>Second Event</Timeline.Title>
								<Timeline.Time>Yesterday</Timeline.Time>
							</Timeline.Header>
							<Timeline.Description>
								Something else happened here that's worth noting.
							</Timeline.Description>
						</Timeline.Content>
					</Timeline.Item>

					<Timeline.Item isLast>
						<Timeline.Icon />
						<Timeline.Content>
							<Timeline.Header>
								<Timeline.Title>Third Event</Timeline.Title>
								<Timeline.Time>3 days ago</Timeline.Time>
							</Timeline.Header>
							<Timeline.Description>
								The final event in this basic timeline.
							</Timeline.Description>
						</Timeline.Content>
					</Timeline.Item>
				</Timeline.Root>
			</Card.Content>
		</Card.Root>

		<!-- Colored Variants -->
		<Card.Root>
			<Card.Header>
				<Card.Title>Status Variants</Card.Title>
				<Card.Description>Different colors for different event types</Card.Description>
			</Card.Header>
			<Card.Content>
				<Timeline.Root>
					<Timeline.Item variant="success">
						<Timeline.Icon variant="success">
							<CheckCircle class="h-4 w-4" />
						</Timeline.Icon>
						<Timeline.Connector variant="success" />
						<Timeline.Content>
							<Timeline.Header>
								<Timeline.Title>Deal Won</Timeline.Title>
								<Badge variant="outline" class="text-green-600">Success</Badge>
							</Timeline.Header>
							<Timeline.Description>
								Successfully closed the deal with Acme Corp for $50,000.
							</Timeline.Description>
						</Timeline.Content>
					</Timeline.Item>

					<Timeline.Item variant="warning">
						<Timeline.Icon variant="warning">
							<Clock class="h-4 w-4" />
						</Timeline.Icon>
						<Timeline.Connector variant="warning" />
						<Timeline.Content>
							<Timeline.Header>
								<Timeline.Title>Follow-up Pending</Timeline.Title>
								<Badge variant="outline" class="text-amber-600">Warning</Badge>
							</Timeline.Header>
							<Timeline.Description>
								Scheduled follow-up call is overdue by 2 days.
							</Timeline.Description>
						</Timeline.Content>
					</Timeline.Item>

					<Timeline.Item variant="error">
						<Timeline.Icon variant="error">
							<AlertCircle class="h-4 w-4" />
						</Timeline.Icon>
						<Timeline.Connector variant="error" />
						<Timeline.Content>
							<Timeline.Header>
								<Timeline.Title>Deal Lost</Timeline.Title>
								<Badge variant="outline" class="text-red-600">Error</Badge>
							</Timeline.Header>
							<Timeline.Description>
								Lost opportunity due to competitor pricing.
							</Timeline.Description>
						</Timeline.Content>
					</Timeline.Item>

					<Timeline.Item variant="info" isLast>
						<Timeline.Icon variant="info">
							<Mail class="h-4 w-4" />
						</Timeline.Icon>
						<Timeline.Content>
							<Timeline.Header>
								<Timeline.Title>Email Sent</Timeline.Title>
								<Badge variant="outline" class="text-blue-600">Info</Badge>
							</Timeline.Header>
							<Timeline.Description>
								Initial outreach email sent to prospect.
							</Timeline.Description>
						</Timeline.Content>
					</Timeline.Item>
				</Timeline.Root>
			</Card.Content>
		</Card.Root>

		<!-- Activity Feed -->
		<Card.Root>
			<Card.Header>
				<Card.Title>Activity Feed</Card.Title>
				<Card.Description>CRM-style activity timeline</Card.Description>
			</Card.Header>
			<Card.Content>
				<Timeline.Root>
					<Timeline.Item>
						<Timeline.Icon variant="info">
							<Phone class="h-4 w-4" />
						</Timeline.Icon>
						<Timeline.Connector />
						<Timeline.Content>
							<Timeline.Header>
								<Timeline.Title>Call with John Smith</Timeline.Title>
								<Timeline.Time>10:30 AM</Timeline.Time>
							</Timeline.Header>
							<Timeline.Description>
								Discussed quarterly targets and next steps. Follow-up meeting scheduled for next week.
							</Timeline.Description>
							<div class="mt-2 flex gap-2">
								<Button variant="ghost" size="sm">
									<Pencil class="h-3 w-3 mr-1" />
									Edit
								</Button>
								<Button variant="ghost" size="sm" class="text-destructive">
									<Trash2 class="h-3 w-3 mr-1" />
									Delete
								</Button>
							</div>
						</Timeline.Content>
					</Timeline.Item>

					<Timeline.Item>
						<Timeline.Icon variant="outline">
							<FileText class="h-4 w-4" />
						</Timeline.Icon>
						<Timeline.Connector variant="dashed" />
						<Timeline.Content>
							<Timeline.Header>
								<Timeline.Title>Proposal Sent</Timeline.Title>
								<Timeline.Time>Yesterday</Timeline.Time>
							</Timeline.Header>
							<Timeline.Description>
								Sent updated proposal document with revised pricing.
							</Timeline.Description>
							<div class="mt-2 p-2 bg-muted rounded-md text-sm">
								<span class="text-muted-foreground">Attachment:</span> Proposal_v2.pdf
							</div>
						</Timeline.Content>
					</Timeline.Item>

					<Timeline.Item>
						<Timeline.Icon variant="success">
							<MessageSquare class="h-4 w-4" />
						</Timeline.Icon>
						<Timeline.Connector />
						<Timeline.Content>
							<Timeline.Header>
								<Timeline.Title>Note Added</Timeline.Title>
								<Timeline.Time>2 days ago</Timeline.Time>
							</Timeline.Header>
							<Timeline.Description>
								Client expressed interest in premium tier. May need to involve technical team for demo.
							</Timeline.Description>
						</Timeline.Content>
					</Timeline.Item>

					<Timeline.Item isLast>
						<Timeline.Icon>
							<Calendar class="h-4 w-4" />
						</Timeline.Icon>
						<Timeline.Content>
							<Timeline.Header>
								<Timeline.Title>Meeting Scheduled</Timeline.Title>
								<Timeline.Time>1 week ago</Timeline.Time>
							</Timeline.Header>
							<Timeline.Description>
								Initial discovery call booked via calendar invite.
							</Timeline.Description>
						</Timeline.Content>
					</Timeline.Item>
				</Timeline.Root>
			</Card.Content>
		</Card.Root>

		<!-- Deal History -->
		<Card.Root>
			<Card.Header>
				<Card.Title>Deal Stage History</Card.Title>
				<Card.Description>Track deal progression through pipeline stages</Card.Description>
			</Card.Header>
			<Card.Content>
				<Timeline.Root>
					<Timeline.Item variant="success">
						<Timeline.Icon variant="success">
							<DollarSign class="h-4 w-4" />
						</Timeline.Icon>
						<Timeline.Connector variant="success" />
						<Timeline.Content>
							<Timeline.Header>
								<Timeline.Title>Closed Won</Timeline.Title>
								<Timeline.Time>Today</Timeline.Time>
							</Timeline.Header>
							<div class="mt-1 text-sm">
								<span class="text-muted-foreground">Value:</span>{' '}
								<span class="font-semibold text-green-600">$125,000</span>
							</div>
						</Timeline.Content>
					</Timeline.Item>

					<Timeline.Item>
						<Timeline.Icon variant="info">
							<GitMerge class="h-4 w-4" />
						</Timeline.Icon>
						<Timeline.Connector />
						<Timeline.Content>
							<Timeline.Header>
								<Timeline.Title>Negotiation</Timeline.Title>
								<Timeline.Time>3 days</Timeline.Time>
							</Timeline.Header>
							<Timeline.Description>
								Final contract terms negotiated
							</Timeline.Description>
						</Timeline.Content>
					</Timeline.Item>

					<Timeline.Item>
						<Timeline.Icon variant="outline">
							<FileText class="h-4 w-4" />
						</Timeline.Icon>
						<Timeline.Connector />
						<Timeline.Content>
							<Timeline.Header>
								<Timeline.Title>Proposal</Timeline.Title>
								<Timeline.Time>1 week</Timeline.Time>
							</Timeline.Header>
							<Timeline.Description>
								Formal proposal presented to stakeholders
							</Timeline.Description>
						</Timeline.Content>
					</Timeline.Item>

					<Timeline.Item>
						<Timeline.Icon variant="outline">
							<User class="h-4 w-4" />
						</Timeline.Icon>
						<Timeline.Connector />
						<Timeline.Content>
							<Timeline.Header>
								<Timeline.Title>Qualification</Timeline.Title>
								<Timeline.Time>2 weeks</Timeline.Time>
							</Timeline.Header>
							<Timeline.Description>
								Budget and decision-maker confirmed
							</Timeline.Description>
						</Timeline.Content>
					</Timeline.Item>

					<Timeline.Item isLast>
						<Timeline.Icon variant="outline">
							<Building class="h-4 w-4" />
						</Timeline.Icon>
						<Timeline.Content>
							<Timeline.Header>
								<Timeline.Title>Lead Created</Timeline.Title>
								<Timeline.Time>1 month ago</Timeline.Time>
							</Timeline.Header>
							<Timeline.Description>
								Inbound lead from website contact form
							</Timeline.Description>
						</Timeline.Content>
					</Timeline.Item>
				</Timeline.Root>
			</Card.Content>
		</Card.Root>
	</div>

	<Separator />

	<!-- Usage Examples -->
	<Card.Root>
		<Card.Header>
			<Card.Title>Usage</Card.Title>
			<Card.Description>How to use the Timeline component</Card.Description>
		</Card.Header>
		<Card.Content>
			<pre class="bg-muted p-4 rounded-lg text-sm overflow-x-auto"><code>{`<script>
  import * as Timeline from '$lib/components/ui/timeline';
  import { CheckCircle } from 'lucide-svelte';
</script>

<Timeline.Root>
  <Timeline.Item variant="success">
    <Timeline.Icon variant="success">
      <CheckCircle class="h-4 w-4" />
    </Timeline.Icon>
    <Timeline.Connector variant="success" />
    <Timeline.Content>
      <Timeline.Header>
        <Timeline.Title>Event Title</Timeline.Title>
        <Timeline.Time>2 hours ago</Timeline.Time>
      </Timeline.Header>
      <Timeline.Description>
        Event description goes here.
      </Timeline.Description>
    </Timeline.Content>
  </Timeline.Item>

  <Timeline.Item isLast>
    <Timeline.Icon />
    <Timeline.Content>
      <Timeline.Title>Last Event</Timeline.Title>
    </Timeline.Content>
  </Timeline.Item>
</Timeline.Root>`}</code></pre>
		</Card.Content>
	</Card.Root>

	<!-- Component API -->
	<Card.Root>
		<Card.Header>
			<Card.Title>Component API</Card.Title>
			<Card.Description>Available components and their props</Card.Description>
		</Card.Header>
		<Card.Content class="space-y-4">
			<div class="grid gap-4 md:grid-cols-2">
				<div class="space-y-2">
					<h4 class="font-semibold">Timeline.Item</h4>
					<ul class="text-sm text-muted-foreground space-y-1">
						<li><code class="text-xs bg-muted px-1 rounded">variant</code> - default | success | warning | error | info</li>
						<li><code class="text-xs bg-muted px-1 rounded">isLast</code> - boolean (hides connector)</li>
					</ul>
				</div>
				<div class="space-y-2">
					<h4 class="font-semibold">Timeline.Icon</h4>
					<ul class="text-sm text-muted-foreground space-y-1">
						<li><code class="text-xs bg-muted px-1 rounded">variant</code> - default | success | warning | error | info | outline</li>
						<li>Children: optional icon component</li>
					</ul>
				</div>
				<div class="space-y-2">
					<h4 class="font-semibold">Timeline.Connector</h4>
					<ul class="text-sm text-muted-foreground space-y-1">
						<li><code class="text-xs bg-muted px-1 rounded">variant</code> - default | success | warning | error | info | dashed</li>
					</ul>
				</div>
				<div class="space-y-2">
					<h4 class="font-semibold">Other Components</h4>
					<ul class="text-sm text-muted-foreground space-y-1">
						<li><code class="text-xs bg-muted px-1 rounded">Timeline.Root</code> - Container</li>
						<li><code class="text-xs bg-muted px-1 rounded">Timeline.Content</code> - Content wrapper</li>
						<li><code class="text-xs bg-muted px-1 rounded">Timeline.Header</code> - Title + time row</li>
						<li><code class="text-xs bg-muted px-1 rounded">Timeline.Title</code> - Event title</li>
						<li><code class="text-xs bg-muted px-1 rounded">Timeline.Time</code> - Timestamp</li>
						<li><code class="text-xs bg-muted px-1 rounded">Timeline.Description</code> - Description text</li>
					</ul>
				</div>
			</div>
		</Card.Content>
	</Card.Root>
</div>
