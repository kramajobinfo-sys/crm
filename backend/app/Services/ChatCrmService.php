<?php
namespace App\Services;

use App\Models\ChatConversation;
use App\Models\ChatContact;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\TimelineActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ChatCrmService
{
    private const TYPES=['lead'=>Lead::class,'contact'=>Contact::class,'account'=>Customer::class];
    public function __construct(private readonly LeadService $leads) {}

    public function context(ChatConversation $conversation,?string $query=null): array
    {
        $conversation->loadMissing(['contact','channel:id,type,name']); $contact=$conversation->contact;
        $term=trim((string)$query); $hasIdentity=filled($contact->email)||filled($contact->phone);
        $matches=['leads'=>[],'contacts'=>[],'accounts'=>[]];
        if($term!==''||$hasIdentity){
            $matches['leads']=Lead::query()->when($term!=='',fn($q)=>$q->search($term),fn($q)=>$this->identityWhere($q,$contact,['email','phone','mobile']))->limit(8)->get()->map(fn($m)=>$this->entityData($m))->all();
            $matches['contacts']=Contact::query()->with('customer:id,name')->when($term!=='',fn($q)=>$this->termWhere($q,$term,['name','email','phone','mobile']),fn($q)=>$this->identityWhere($q,$contact,['email','phone','mobile']))->limit(8)->get()->map(fn($m)=>$this->entityData($m))->all();
            $matches['accounts']=Customer::query()->when($term!=='',fn($q)=>$q->search($term),fn($q)=>$this->identityWhere($q,$contact,['email','phone','mobile']))->limit(8)->get()->map(fn($m)=>$this->entityData($m))->all();
        }
        $linked=$this->linkedEntity($contact);
        $history=TimelineActivity::query()->where('subject_type',ChatContact::class)->where('subject_id',$contact->id)->with('user:id,name')->latest('occurred_at')->limit(10)->get()->map(fn($e)=>['id'=>$e->id,'title'=>$e->title,'body'=>$e->body,'user'=>$e->user?->name,'occurred_at'=>$e->occurred_at?->toIso8601String()]);
        return ['identity'=>['id'=>$contact->id,'name'=>$contact->display_name,'email'=>$contact->email,'phone'=>$contact->phone,'channel'=>$conversation->channel?->type],
            'linked'=>$linked?$this->entityData($linked):null,'matches'=>$matches,'history'=>$history];
    }

    public function link(ChatConversation $conversation,string $type,int $id): array
    {
        $class=self::TYPES[$type]??null; if(!$class)throw new RuntimeException('Unsupported CRM record type.');
        $target=$class::findOrFail($id); $contact=$conversation->contact()->firstOrFail();
        DB::transaction(function()use($contact,$target,$type,$conversation){
            $contact->forceFill(['linked_type'=>$target::class,'linked_id'=>$target->getKey()])->save();
            TimelineActivity::record($contact,'system','Social identity linked to CRM',null,['entity_type'=>$type,'entity_id'=>$target->getKey(),'conversation_id'=>$conversation->id]);
            if(method_exists($target,'timeline'))TimelineActivity::record($target,'system','Social Inbox identity linked',null,['chat_contact_id'=>$contact->id,'conversation_id'=>$conversation->id]);
        });
        return $this->context($conversation->fresh(['contact','channel']));
    }

    public function unlink(ChatConversation $conversation): array
    {
        $contact=$conversation->contact()->firstOrFail();
        if($contact->linked_type&&$contact->linked_id)TimelineActivity::record($contact,'system','Social identity unlinked from CRM',null,['entity_type'=>$contact->linked_type,'entity_id'=>$contact->linked_id,'conversation_id'=>$conversation->id]);
        $contact->forceFill(['linked_type'=>null,'linked_id'=>null])->save();
        return $this->context($conversation->fresh(['contact','channel']));
    }

    public function createLead(ChatConversation $conversation,array $data): array
    {
        $conversation->loadMissing(['contact','channel']); $contact=$conversation->contact;
        if($contact->linked_type)throw new RuntimeException('This social identity is already linked to a CRM record.');
        $duplicate=empty($data['email'])&&empty($data['phone'])?null:Lead::query()->open()->where(function($q)use($data){
            if(!empty($data['email']))$q->orWhereRaw('LOWER(email) = ?',[mb_strtolower($data['email'])]);
            if(!empty($data['phone']))$q->orWhere('phone',$data['phone'])->orWhere('mobile',$data['phone']);
        })->first();
        if($duplicate)throw new RuntimeException("A matching Lead already exists ({$duplicate->lead_no}). Link it instead of creating a duplicate.");
        $source=LeadSource::firstOrCreate(['company_id'=>$conversation->company_id,'code'=>'social-inbox'],['name'=>'Social Inbox','is_active'=>true]);
        $lead=$this->leads->create(array_merge($data,['source_id'=>$source->id,'owner_id'=>$data['owner_id']??$conversation->assigned_to??auth()->id(),
            'notes'=>trim(($data['notes']??'')."\nCreated from {$conversation->channel->name} conversation #{$conversation->id}."),'currency'=>$data['currency']??auth()->user()->company?->base_currency??'USD']));
        return $this->link($conversation,'lead',$lead->id);
    }

    private function linkedEntity(ChatContact $contact): ?Model
    {
        if(!$contact->linked_type||!$contact->linked_id)return null;
        $class=collect(self::TYPES)->first(fn($candidate)=>$candidate===$contact->linked_type||class_basename($candidate)===class_basename($contact->linked_type));
        return $class?$class::find($contact->linked_id):null;
    }
    private function entityData(Model $m): array
    {
        $type=$m instanceof Lead?'lead':($m instanceof Contact?'contact':'account');
        return ['type'=>$type,'id'=>$m->id,'number'=>$m->lead_no??$m->customer_no??null,'name'=>$m->name,'company_name'=>$m->company_name??$m->customer?->name??null,
            'email'=>$m->email,'phone'=>$m->phone??$m->mobile,'url'=>match($type){'lead'=>'/app/leads','contact'=>'/app/contacts','account'=>'/app/accounts'}];
    }
    private function identityWhere($query,ChatContact $contact,array $fields)
    {
        return $query->where(function($q)use($contact,$fields){foreach($fields as $field){if($contact->email&&$field==='email')$q->orWhereRaw('LOWER(email) = ?',[mb_strtolower($contact->email)]);if($contact->phone&&in_array($field,['phone','mobile'],true))$q->orWhere($field,$contact->phone);}});
    }
    private function termWhere($query,string $term,array $fields)
    {
        $like='%'.$term.'%'; return $query->where(fn($q)=>collect($fields)->each(fn($field)=>$q->orWhere($field,'like',$like)));
    }
}
