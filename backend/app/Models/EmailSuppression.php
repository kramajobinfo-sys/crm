<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A marketing opt-out captured by email address (e.g. via the public unsubscribe link),
 * independent of whether the address maps to a CRM Contact. Honored by the send-time
 * suppression in ContactConsent::suppression() for marketing channels.
 */
class EmailSuppression extends Model
{
    protected $fillable = ['company_id', 'email', 'source', 'campaign_id'];
}
