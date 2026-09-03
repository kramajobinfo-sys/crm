<?php
namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Database\Seeder;

class WorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('code', 'KRAMA')->first();
        if (!$company) return;
        $cid = $company->id;
        $mgr = User::where('company_id', $cid)->where('email', 'sales.mgr@krama.local')->value('id');

        // [name, desc, entity, trigger_type, trigger_event, conditions, actions]
        $rows = [
            ['Follow up on hot leads', 'Create a follow-up task when a lead becomes hot', 'leads', 'event', 'lead.updated',
             [['field' => 'rating', 'op' => 'eq', 'value' => 'hot']],
             [
                ['type' => 'create_task', 'config' => ['title' => 'Call this hot lead', 'priority' => 'high', 'due_in_days' => 1]],
                ['type' => 'log', 'config' => ['message' => 'Hot lead follow-up triggered']],
             ]],
            ['Welcome new customers', 'Send a welcome email when a customer is created', 'customers', 'event', 'customer.created',
             [],
             [
                ['type' => 'send_email', 'config' => ['subject' => 'Welcome to Krama', 'body' => '<p>Thank you for choosing Krama.</p>']],
             ]],
            ['Escalate urgent tickets', 'Bump priority to urgent for high-value tickets', 'tickets', 'manual', null,
             [],
             [
                ['type' => 'update_field', 'config' => ['field' => 'priority', 'value' => 'urgent']],
                ['type' => 'notify', 'config' => ['message' => 'Ticket escalated by workflow']],
             ]],
        ];

        foreach ($rows as [$name,$desc,$entity,$trigger,$event,$conditions,$actions]) {
            $wf = Workflow::updateOrCreate(
                ['company_id' => $cid, 'name' => $name],
                ['description' => $desc, 'entity' => $entity, 'trigger_type' => $trigger,
                 'trigger_event' => $event, 'conditions' => $conditions ?: null, 'is_active' => true,
                 'created_by' => $mgr]
            );
            $wf->actions()->delete();
            foreach (array_values($actions) as $i => $a) {
                $wf->actions()->create(['company_id' => $cid, 'order' => $i, 'type' => $a['type'], 'config' => $a['config']]);
            }
        }

        $this->command?->info('Seeded '.count($rows).' workflows with actions.');
    }
}
