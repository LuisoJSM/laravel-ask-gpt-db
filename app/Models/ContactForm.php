<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactForm extends Model
{
    /** @use HasFactory<ContactFormFactory> */
    use HasFactory;



    protected $fillable = [
        'name',
        'email',
        'subject',
        'message',
        'is_responded'
    ];


    protected function casts(): array
    {
        return [
            'is_responded' => 'boolean',
        ];
    }


}
