<?php

namespace Tests\Feature\Chat;

use App\Models\ChatChannel;
use App\Models\ChatContact;
use App\Models\ChatConversation;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatCrmHandoffTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->withoutMiddleware(); }

    public function test_social_identity_detects_and_links_same_tenant_crm_match(): void
    {
        [$company,$user,$conversation]=$this->context(); $this->actingAs($user,'api');
        $account=Customer::create(['company_id'=>$company->id,'customer_no'=>'C-1','name'=>'Acme','type'=>'company','status'=>'active']);
        $contact=Contact::create(['company_id'=>$company->id,'customer_id'=>$account->id,'name'=>'Sam Buyer','email'=>'sam@example.com']);
        $this->getJson("/api/v1/chat/conversations/{$conversation->id}/crm-context")->assertOk()
            ->assertJsonPath('data.identity.email','sam@example.com')->assertJsonPath('data.matches.contacts.0.id',$contact->id);
        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/crm-link",['type'=>'contact','id'=>$contact->id])->assertOk()
            ->assertJsonPath('data.linked.type','contact')->assertJsonPath('data.linked.id',$contact->id);
        $this->assertSame(Contact::class,$conversation->contact->fresh()->linked_type);
        $this->deleteJson("/api/v1/chat/conversations/{$conversation->id}/crm-link")->assertOk()->assertJsonPath('data.linked',null);
    }

    public function test_foreign_tenant_record_cannot_be_linked(): void
    {
        [$company,$user,$conversation]=$this->context(); $this->actingAs($user,'api');
        $other=Company::create(['name'=>'Other','code'=>'OTH','is_active'=>true]);
        $account=Customer::withoutGlobalScopes()->create(['company_id'=>$other->id,'customer_no'=>'C-X','name'=>'Foreign','type'=>'company','status'=>'active']);
        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/crm-link",['type'=>'account','id'=>$account->id])->assertUnprocessable();
    }

    public function test_conversation_creates_and_links_lead_without_duplicate_entry(): void
    {
        [$company,$user,$conversation]=$this->context(); $this->actingAs($user,'api');
        LeadStatus::create(['company_id'=>$company->id,'name'=>'New','code'=>'new','is_default'=>true]);
        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/create-lead",['name'=>'Sam Buyer','email'=>'sam@example.com','company_name'=>'Acme'])
            ->assertCreated()->assertJsonPath('data.linked.type','lead')->assertJsonPath('data.linked.name','Sam Buyer');
        $lead=Lead::firstOrFail(); $this->assertStringContainsString('conversation #'.$conversation->id,$lead->notes);
        $this->postJson("/api/v1/chat/conversations/{$conversation->id}/create-lead",['name'=>'Sam Buyer','email'=>'sam@example.com'])->assertUnprocessable();
    }

    private function context(): array
    {
        $company=Company::create(['name'=>'Tenant','code'=>'TEN','base_currency'=>'USD','is_active'=>true]);
        $user=User::create(['company_id'=>$company->id,'name'=>'Agent','email'=>'agent@test.local','password'=>'password','is_active'=>true]);
        $channel=ChatChannel::create(['company_id'=>$company->id,'type'=>'whatsapp','name'=>'Main WhatsApp','external_account_id'=>'wa-1','is_active'=>true]);
        $contact=ChatContact::create(['company_id'=>$company->id,'channel_id'=>$channel->id,'external_user_id'=>'sam-wa','display_name'=>'Sam Buyer','email'=>'sam@example.com','phone'=>'+85512345678']);
        $conversation=ChatConversation::create(['company_id'=>$company->id,'channel_id'=>$channel->id,'contact_id'=>$contact->id,'assigned_to'=>$user->id,'external_thread_id'=>'thread-1','status'=>'open','last_message_at'=>now(),'last_inbound_at'=>now()]);
        return [$company,$user,$conversation];
    }
}
