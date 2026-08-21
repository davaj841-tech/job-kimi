<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterSubscription extends Model
{
    protected $fillable = [
        'contact_type',
        'contact_value',
        'contact_hash',
        'ip_address',
    ];
}
