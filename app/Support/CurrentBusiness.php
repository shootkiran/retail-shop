<?php

namespace App\Support;

use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class CurrentBusiness
{
    protected ?Business $resolved = null;

    public function get(): ?Business
    {
        if ($this->resolved instanceof Business) {
            return $this->resolved;
        }

        $user = Auth::user();

        if ($user instanceof User) {
            $business = $user->currentBusiness;

            if (! $business || ! $business->is_active) {
                $business = $user->businesses()
                    ->wherePivot('is_active', true)
                    ->where('businesses.is_active', true)
                    ->orderBy('businesses.name')
                    ->first();
            }

            if ($business instanceof Business) {
                return $this->resolved = $business;
            }
        }

        return $this->resolved = Business::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }

    public function id(): ?int
    {
        return $this->get()?->id;
    }

    public function clear(): void
    {
        $this->resolved = null;
    }
}
