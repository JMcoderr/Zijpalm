<?php

use App\CharityRequestStatus;
use App\Models\CharityAdmin;
use App\Models\CharityEvent;
use App\Models\CharityEventCost;
use App\Models\CharityMember;
use App\Models\CharityRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    config(['services.charity_magic_links.allowed_domains' => ['example.test']]);

    $event = CharityEvent::create([
        'title' => 'Support event',
    ]);

    $this->eventCost = CharityEventCost::create([
        'charity_event_id' => $event->id,
        'amount' => '125.50',
        'start_date' => '2026-01-01 00:00:00',
    ]);

    $this->member = CharityMember::create([
        'email' => 'private-recipient@example.test',
        'full_name' => 'Example Recipient',
        'dob' => '1985-04-12',
    ]);

    $this->request = CharityRequest::create([
        'requester_email' => 'employee@example.test',
        'charity_member_id' => $this->member->id,
        'charity_event_cost_id' => $this->eventCost->id,
        'account_name' => 'Private Account',
        'iban' => 'NL91ABNA0417164300',
    ]);
});

function charityReviewSession(string $email = 'admin@example.test'): array
{
    return [
        'charity_magic' => [
            'email' => $email,
            'expires_at' => now()->addHour()->timestamp,
        ],
    ];
}

it('requires an active charity admin to open the review list', function () {
    $this->get(route('charity.requests.review'))
        ->assertRedirect(route('charity.login'));

    $this->withSession(charityReviewSession())
        ->get(route('charity.requests.review'))
        ->assertForbidden();
});

it('shows only pending requests and leaves private member and bank details off the page', function () {
    $this->member->delete();

    $acceptedRequest = CharityRequest::create([
        'requester_email' => 'another-employee@example.test',
        'charity_member_id' => $this->member->id,
        'charity_event_cost_id' => $this->eventCost->id,
        'account_name' => 'Another Private Account',
        'iban' => 'GB82WEST12345698765432',
    ]);
    $acceptedRequest->forceFill(['status' => CharityRequestStatus::Accepted])->save();

    CharityAdmin::create(['employee' => 'admin@example.test']);

    $this->withSession(charityReviewSession())
        ->get(route('charity.requests.review'))
        ->assertOk()
        ->assertSee('Aanvraag #'.$this->request->id)
        ->assertSee('Support event')
        ->assertSee('employee@example.test')
        ->assertSee('Example Recipient')
        ->assertSee('125,50')
        ->assertDontSee('Aanvraag #'.$acceptedRequest->id)
        ->assertDontSee('private-recipient@example.test')
        ->assertDontSee('1985-04-12')
        ->assertDontSee('Private Account')
        ->assertDontSee('NL91ABNA0417164300')
        ->assertDontSee('Another Private Account')
        ->assertDontSee('GB82WEST12345698765432');
});

it('shows the review link only to an active charity admin', function () {
    $this->withSession(charityReviewSession('employee@example.test'))
        ->get(route('charity.login'))
        ->assertOk()
        ->assertDontSee(route('charity.requests.review'));

    CharityAdmin::create(['employee' => 'admin@example.test']);

    $this->withSession(charityReviewSession())
        ->get(route('charity.login'))
        ->assertOk()
        ->assertSee(route('charity.requests.review'));
});

it('allows an admin to accept a pending request', function () {
    CharityAdmin::create(['employee' => 'admin@example.test']);

    $this->withSession(charityReviewSession())
        ->patch(route('charity.requests.review.update', $this->request), [
            'status' => CharityRequestStatus::Accepted->value,
        ])
        ->assertRedirect(route('charity.requests.review'))
        ->assertSessionHas('status', 'Aanvraag goedgekeurd.');

    expect($this->request->fresh()->status)->toBe(CharityRequestStatus::Accepted)
        ->and($this->request->fresh()->iban)->toBe('NL91ABNA0417164300')
        ->and($this->request->fresh()->account_name)->toBe('Private Account');
});

it('rejects a pending request and clears its bank details', function () {
    CharityAdmin::create(['employee' => 'admin@example.test']);

    $this->withSession(charityReviewSession())
        ->patch(route('charity.requests.review.update', $this->request), [
            'status' => CharityRequestStatus::Rejected->value,
            'reason' => 'De aanvraag voldoet niet aan de voorwaarden.',
        ])
        ->assertRedirect(route('charity.requests.review'))
        ->assertSessionHas('status', 'Aanvraag afgewezen.');

    $reviewedRequest = $this->request->fresh();

    expect($reviewedRequest->status)->toBe(CharityRequestStatus::Rejected)
        ->and($reviewedRequest->reason)->toBe('De aanvraag voldoet niet aan de voorwaarden.')
        ->and($reviewedRequest->iban)->toBeNull()
        ->and($reviewedRequest->account_name)->toBeNull();
});

it('does not allow unsupported or repeated review decisions', function () {
    CharityAdmin::create(['employee' => 'admin@example.test']);

    $this->withSession(charityReviewSession())
        ->patch(route('charity.requests.review.update', $this->request), [
            'status' => CharityRequestStatus::Paid->value,
        ])
        ->assertSessionHasErrors('status');

    $this->withSession(charityReviewSession())
        ->patch(route('charity.requests.review.update', $this->request), [
            'status' => CharityRequestStatus::Accepted->value,
        ])
        ->assertRedirect(route('charity.requests.review'));

    $this->withSession(charityReviewSession())
        ->patch(route('charity.requests.review.update', $this->request), [
            'status' => CharityRequestStatus::Rejected->value,
        ])
        ->assertSessionHasErrors('status');

    expect($this->request->fresh()->status)->toBe(CharityRequestStatus::Accepted);
});
