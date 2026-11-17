<?php

namespace App\Http\Requests;

class UpdateEventRequest extends StoreEventRequest
{
    // Same validation rules as creating an event. Kept as a separate class so
    // future update-only rules have an obvious home.
}
