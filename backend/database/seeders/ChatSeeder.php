<?php
namespace Database\Seeders;

use App\Models\ChatCannedResponse;
use App\Models\ChatChannel;
use App\Models\ChatContact;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

class ChatSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('code', 'KRAMA')->first();
        if (!$company) return;   // nothing to attach demo data to

        // Seeders run unauthenticated, so BelongsToCompany's creating hook never
        // fires — company_id must be set explicitly on every row below.
        $cid   = $company->id;
        $agent = User::where('company_id', $cid)->where('email', 'support@krama.local')->first()
              ?? User::where('company_id', $cid)->first();

        // Several accounts per provider, which is the normal shape for a real deployment.
        // Keyed on (company_id, type, name) to match the unique index, so re-runs update
        // rather than duplicate.
        $accounts = [
            'messenger' => [
                ['Krama Main Page',        '102338845017'],
                ['Krama Support',          '102338845018'],
                ['Krama Furniture',        '102338845019'],
                ['Krama Outlet Dubai',     '102338845020'],
                ['Krama Wholesale',        '102338845021'],
            ],
            'instagram' => [
                ['@krama.official',        '17841400001'],
                ['@krama.home',            '17841400002'],
                ['@krama.outlet',          '17841400003'],
            ],
            'telegram' => [
                ['Krama Support Bot',      'krama_support_bot'],
                ['Krama Orders Bot',       'krama_orders_bot'],
            ],
            'tiktok' => [
                ['@krama',                 'tiktok_7412001'],
                ['@krama.shop',            'tiktok_7412002'],
            ],
            'whatsapp' => [
                ['WhatsApp Business',      '971500000001'],
                ['WhatsApp Sales',         '971500000002'],
            ],
            'sms'     => [['SMS Gateway',        null]],
            'webchat' => [['Website Live Chat',  null]],
        ];

        $channels = [];   // [type][index] => ChatChannel
        foreach ($accounts as $type => $rows) {
            foreach ($rows as [$name, $externalId]) {
                $channels[$type][] = ChatChannel::updateOrCreate(
                    ['company_id' => $cid, 'type' => $type, 'name' => $name],
                    ['external_account_id' => $externalId, 'is_active' => true]
                );
            }
        }

        foreach ([
            ['greeting', 'Greeting',      'Hi! Thanks for reaching out to Krama. How can I help you today?'],
            ['hours',    'Opening hours', 'Our team is available Sunday to Thursday, 9:00–18:00 GST.'],
            ['shipping', 'Shipping info', 'Orders are dispatched within 2 business days and arrive in 3–5 days.'],
            ['closing',  'Closing',       'Happy to help! I will close this conversation now — reply any time to reopen it.'],
        ] as [$shortcut, $title, $body]) {
            ChatCannedResponse::updateOrCreate(
                ['company_id' => $cid, 'shortcut' => $shortcut],
                ['title' => $title, 'body' => $body, 'is_active' => true]
            );
        }

        // [type, accountIndex, contact, subject, status, priority, assigned, unread, messages]
        $threads = [
            ['whatsapp',  0, 'Layla Haddad',     'Order #1043 delivery date', 'open',     'high',   true,  2, [
                ['in',  'Hello, I ordered last week — when will it arrive?', 40],
                ['out', 'Hi Layla! Let me check order #1043 for you right away.', 36],
                ['in',  'Thank you. I need it before Thursday.', 20],
            ]],
            ['whatsapp',  1, 'Hassan Ali',       'Trade account setup',       'open',     'normal', false, 1, [
                ['in',  'I would like to open a trade account for my shop.', 55],
            ]],
            ['messenger', 0, 'Omar Farouk',      'Bulk pricing enquiry',      'pending',  'normal', true,  1, [
                ['in',  'Do you offer discounts on orders above 100 units?', 180],
                ['out', 'We do — I am putting a quotation together for you now.', 150],
                ['in',  'Great, please include delivery to Sharjah.', 90],
            ]],
            ['messenger', 2, 'Mariam Saleh',     'Sofa fabric options',       'open',     'normal', false, 2, [
                ['in',  'Do you have the three-seater in a darker fabric?', 70],
            ]],
            ['messenger', 3, 'Khalid Nasser',    'Outlet opening hours',      'open',     'low',    false, 1, [
                ['in',  'Is the Dubai outlet open on Friday morning?', 120],
            ]],
            ['instagram', 0, 'Nadia Kamal',      'Product availability',      'open',     'normal', false, 1, [
                ['in',  'Is the walnut dining set still in stock?', 300],
            ]],
            ['instagram', 1, 'Rania Doust',      'Story product tag',         'open',     'normal', false, 1, [
                ['in',  'What is the price of the lamp in your latest story?', 95],
            ]],
            ['telegram',  0, 'Yusuf Rahman',     'Invoice copy request',      'resolved', 'low',    true,  0, [
                ['in',  'Could you resend the invoice for last month?', 2880],
                ['out', 'Sent it to your registered email — let me know if it did not arrive.', 2820],
                ['in',  'Received, thank you!', 2800],
            ]],
            ['telegram',  1, 'Samir Aziz',       'Order tracking',            'open',     'normal', false, 1, [
                ['in',  'Can you check where order #2210 has reached?', 45],
            ]],
            ['tiktok',    0, 'Dina Youssef',     'Video product enquiry',     'open',     'high',   false, 2, [
                ['in',  'Saw your desk organiser video — do you ship to Abu Dhabi?', 30],
            ]],
            ['tiktok',    1, 'Tariq Mansour',    'Shop link broken',          'open',     'urgent', false, 1, [
                ['in',  'The product link in your TikTok shop is not opening.', 12],
            ]],
            ['sms',       0, '+971 50 118 4402', 'Delivery OTP follow-up',    'open',     'urgent', false, 3, [
                ['in',  'I did not receive the delivery code for my parcel.', 15],
            ]],
            ['webchat',   0, 'Website visitor',  'Pricing page question',     'open',     'normal', false, 1, [
                ['in',  'What is included in the Enterprise plan?', 8],
            ]],
        ];

        foreach ($threads as [$type, $idx, $name, $subject, $status, $priority, $assigned, $unread, $messages]) {
            $channel = $channels[$type][$idx];
            $contact = ChatContact::updateOrCreate(
                ['channel_id' => $channel->id, 'external_user_id' => str($name)->slug()->toString()],
                ['company_id' => $cid, 'display_name' => $name]
            );

            $lastAgo   = $messages[array_key_last($messages)][2];
            $lastBody  = $messages[array_key_last($messages)][0];
            $inboundAt = null;
            foreach ($messages as [$dir, , $ago]) if ($dir === 'in') $inboundAt = now()->subMinutes($ago);

            $conversation = ChatConversation::updateOrCreate(
                ['company_id' => $cid, 'channel_id' => $channel->id, 'contact_id' => $contact->id],
                [
                    'assigned_to'          => $assigned && $agent ? $agent->id : null,
                    'subject'              => $subject,
                    'status'               => $status,
                    'priority'             => $priority,
                    'unread_count'         => $unread,
                    'last_message_preview' => mb_substr($messages[array_key_last($messages)][1], 0, 191),
                    'last_message_at'      => now()->subMinutes($lastAgo),
                    'last_inbound_at'      => $inboundAt,
                ]
            );

            // Idempotent: rebuild this thread's messages rather than appending on re-run.
            $conversation->messages()->delete();
            foreach ($messages as [$dir, $body, $ago]) {
                ChatMessage::create([
                    'company_id'      => $cid,
                    'conversation_id' => $conversation->id,
                    'user_id'         => $dir === 'out' && $agent ? $agent->id : null,
                    'direction'       => $dir === 'in' ? 'inbound' : 'outbound',
                    'content_type'    => 'text',
                    'body'            => $body,
                    'status'          => $dir === 'in' ? 'read' : 'delivered',
                    'sent_at'         => now()->subMinutes($ago),
                    'created_at'      => now()->subMinutes($ago),
                    'updated_at'      => now()->subMinutes($ago),
                ]);
            }
        }

        $accountCount = array_sum(array_map('count', $channels));
        $this->command?->info(
            'Seeded '.count($threads).' chat conversations across '.$accountCount.
            ' accounts in '.count($channels).' channel types.'
        );
    }
}
