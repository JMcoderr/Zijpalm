<?php

namespace App\Http\Controllers;

use App\Models\CharityEventCost;
use App\Models\CharityMember;
use Illuminate\Contracts\View\View;

class CharityRequestController extends Controller
{
    public function create(): View
    {
        $eventCosts = CharityEventCost::query()
            ->active()
            ->with('event:id,title')
            ->orderBy('charity_event_id')
            ->get(['id', 'charity_event_id'])
            ->map(static fn (CharityEventCost $eventCost): array => [
                'id' => $eventCost->id,
                'title' => $eventCost->event->title,
            ]);

        $members = CharityMember::query()
            ->availableForRequests()
            ->orderBy('full_name')
            ->orderBy('id')
            ->get(['id', 'full_name', 'name'])
            ->map(static function (CharityMember $member): array {
                $name = trim((string) ($member->full_name ?: $member->name));

                if ($name === '') {
                    $name = 'Lid '.$member->id;
                }

                return [
                    'id' => $member->id,
                    'label' => $name.' (ID '.$member->id.')',
                ];
            });

        return view('charity.requests.create', [
            'eventCosts' => $eventCosts,
            'members' => $members,
        ]);
    }
}
