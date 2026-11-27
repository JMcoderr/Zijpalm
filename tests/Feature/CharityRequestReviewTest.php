<?php

use App\CharityRequestStatus;
use App\Mail\CharityRequestReviewedMail;
use App\Models\CharityAdmin;
use App\Models\CharityEvent;
use App\Models\CharityEventCost;
use App\Models\CharityMember;
use App\Models\CharityRequest;
use App\Services\CharitySessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    config(['services.charity_magic_links.allowed_domains' => ['example.test']]);
    Mail::fake();

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

    Mail::assertNothingSent();
});

it('requires a current superadmin to open the payment overview', function () {
    $this->get(route('charity.requests.payment-overview'))
        ->assertRedirect(route('charity.login'));

    $this->post(route('charity.requests.payment-details'))
        ->assertRedirect(route('charity.login'));

    CharityAdmin::create(['employee' => 'admin@example.test']);

    $this->withSession(charityReviewSession())
        ->get(route('charity.requests.payment-overview'))
        ->assertForbidden();

    $this->withSession(charityReviewSession())
        ->post(route('charity.requests.payment-details'))
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
        ->assertSee(route('charity.requests.review'))
        ->assertDontSee(route('charity.requests.payment-overview'));

    $superadmin = CharityAdmin::create(['employee' => 'super@example.test']);
    $superadmin->super = now();
    $superadmin->save();

    $this->withSession(charityReviewSession('super@example.test'))
        ->get(route('charity.login'))
        ->assertOk()
        ->assertSee(route('charity.requests.review'))
        ->assertSee(route('charity.requests.payment-overview'));
});

it('shows only accepted payment details on a private uncached superadmin page', function () {
    $acceptedRequest = CharityRequest::create([
        'requester_email' => 'employee@example.test',
        'charity_member_id' => $this->member->id,
        'charity_event_cost_id' => $this->eventCost->id,
        'account_name' => 'Approved Payee',
        'iban' => 'GB82WEST12345698765432',
    ]);
    $acceptedRequest->forceFill(['status' => CharityRequestStatus::Accepted])->save();

    $exportedRequest = CharityRequest::create([
        'requester_email' => 'another-employee@example.test',
        'charity_member_id' => $this->member->id,
        'charity_event_cost_id' => $this->eventCost->id,
        'account_name' => 'Already Exported Payee',
        'iban' => 'DE89370400440532013000',
    ]);
    $exportedRequest->forceFill(['status' => CharityRequestStatus::Exported])->save();

    $superadmin = CharityAdmin::create(['employee' => 'super@example.test']);
    $superadmin->super = now();
    $superadmin->save();

    $response = $this->withSession(charityReviewSession('super@example.test'))
        ->get(route('charity.requests.payment-overview'))
        ->assertOk();

    $cacheControl = (string) $response->headers->get('Cache-Control');
    $cacheDirectives = array_map('trim', explode(',', strtolower($cacheControl)));

    expect($cacheDirectives)->toContain('no-store')->toContain('private');

    $response
        ->assertSee('Aanvraag #'.$acceptedRequest->id)
        ->assertSee('Support event')
        ->assertSee('Example Recipient')
        ->assertSee('Approved Payee')
        ->assertSee('GB82WEST12345698765432')
        ->assertSee('Voorbereiding SEPA-export')
        ->assertSee(route('charity.requests.payment-details'))
        ->assertSee('125,50')
        ->assertDontSee('Aanvraag #'.$this->request->id)
        ->assertDontSee('employee@example.test')
        ->assertDontSee('private-recipient@example.test')
        ->assertDontSee('Private Account')
        ->assertDontSee('NL91ABNA0417164300')
        ->assertDontSee('Already Exported Payee')
        ->assertDontSee('DE89370400440532013000');

    expect($acceptedRequest->fresh()->status)->toBe(CharityRequestStatus::Accepted)
        ->and($exportedRequest->fresh()->status)->toBe(CharityRequestStatus::Exported);
});

it('stores validated debtor details encrypted in the superadmin session', function () {
    $superadmin = CharityAdmin::create(['employee' => 'super@example.test']);
    $superadmin->super = now();
    $superadmin->save();

    $iban = 'NL91ABNA0417164300';

    $this->withSession(charityReviewSession('super@example.test'))
        ->post(route('charity.requests.payment-details'), [
            'debtor_name' => 'Zijpalm Vereniging',
            'debtor_iban' => 'nl91 abna 0417 1643 00',
        ])
        ->assertRedirect(route('charity.requests.payment-overview'))
        ->assertSessionHas('status', 'Betalergegevens zijn tijdelijk opgeslagen.')
        ->assertSessionHas(CharitySessionService::PAYMENT_DEBTOR_SESSION_KEY, function (string $encryptedDetails) use ($iban): bool {
            $details = Crypt::decrypt($encryptedDetails);

            return is_array($details)
                && $details['name'] === 'Zijpalm Vereniging'
                && $details['iban'] === $iban
                && ! str_contains($encryptedDetails, 'Zijpalm Vereniging')
                && ! str_contains($encryptedDetails, $iban);
        });

    $this->withSession([
        ...charityReviewSession('super@example.test'),
        CharitySessionService::PAYMENT_DEBTOR_SESSION_KEY => Crypt::encrypt([
            'name' => 'Zijpalm Vereniging',
            'iban' => $iban,
        ]),
    ])
        ->get(route('charity.requests.payment-overview'))
        ->assertOk()
        ->assertSee('De betalergegevens zijn tijdelijk voor deze sessie opgeslagen.')
        ->assertDontSee($iban);
});

it('does not keep previous debtor details or flash an invalid iban', function () {
    $superadmin = CharityAdmin::create(['employee' => 'super@example.test']);
    $superadmin->super = now();
    $superadmin->save();

    $this->from(route('charity.requests.payment-overview'))
        ->withSession([
            ...charityReviewSession('super@example.test'),
            CharitySessionService::PAYMENT_DEBTOR_SESSION_KEY => Crypt::encrypt([
                'name' => 'Previous Payer',
                'iban' => 'NL91ABNA0417164300',
            ]),
        ])
        ->post(route('charity.requests.payment-details'), [
            'debtor_name' => ' ',
            'debtor_iban' => 'NL00ABNA0417164300',
        ])
        ->assertRedirect(route('charity.requests.payment-overview'))
        ->assertSessionHasErrors(['debtor_name', 'debtor_iban'])
        ->assertSessionMissing(CharitySessionService::PAYMENT_DEBTOR_SESSION_KEY)
        ->assertSessionMissing('_old_input.debtor_iban');
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

    Mail::assertSent(CharityRequestReviewedMail::class, function (CharityRequestReviewedMail $mail): bool {
        if (! $mail->hasTo('employee@example.test') || ! $mail->approved) {
            return false;
        }

        $body = $mail->render();

        return str_contains($body, 'is goedgekeurd')
            && ! str_contains($body, 'NL91ABNA0417164300')
            && ! str_contains($body, 'Private Account');
    });
    Mail::assertSentCount(1);
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

    Mail::assertSent(CharityRequestReviewedMail::class, function (CharityRequestReviewedMail $mail): bool {
        if (! $mail->hasTo('employee@example.test') || $mail->approved) {
            return false;
        }

        $body = $mail->render();

        return str_contains($body, 'De aanvraag voldoet niet aan de voorwaarden.')
            && ! str_contains($body, 'NL91ABNA0417164300')
            && ! str_contains($body, 'Private Account');
    });
    Mail::assertSentCount(1);
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
    Mail::assertSentCount(1);
});
